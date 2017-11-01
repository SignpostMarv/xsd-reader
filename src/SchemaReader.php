<?php

declare(strict_types=1);

namespace GoetasWebservices\XML\XSDReader;

use Closure;
use DOMDocument;
use DOMElement;
use DOMNode;
use GoetasWebservices\XML\XSDReader\Exception\IOException;
use GoetasWebservices\XML\XSDReader\Exception\TypeException;
use GoetasWebservices\XML\XSDReader\Schema\Attribute\Attribute;
use GoetasWebservices\XML\XSDReader\Schema\Attribute\AttributeItem;
use GoetasWebservices\XML\XSDReader\Schema\Attribute\AttributeDef;
use GoetasWebservices\XML\XSDReader\Schema\Attribute\Group as AttributeGroup;
use GoetasWebservices\XML\XSDReader\Schema\Element\Element;
use GoetasWebservices\XML\XSDReader\Schema\Element\ElementContainer;
use GoetasWebservices\XML\XSDReader\Schema\Element\ElementDef;
use GoetasWebservices\XML\XSDReader\Schema\Element\ElementItem;
use GoetasWebservices\XML\XSDReader\Schema\Element\ElementRef;
use GoetasWebservices\XML\XSDReader\Schema\Element\Group;
use GoetasWebservices\XML\XSDReader\Schema\Element\GroupRef;
use GoetasWebservices\XML\XSDReader\Schema\Element\InterfaceSetMinMax;
use GoetasWebservices\XML\XSDReader\Schema\Exception\TypeNotFoundException;
use GoetasWebservices\XML\XSDReader\Schema\Inheritance\Base;
use GoetasWebservices\XML\XSDReader\Schema\Inheritance\Extension;
use GoetasWebservices\XML\XSDReader\Schema\Inheritance\Restriction;
use GoetasWebservices\XML\XSDReader\Schema\Item;
use GoetasWebservices\XML\XSDReader\Schema\Schema;
use GoetasWebservices\XML\XSDReader\Schema\SchemaItem;
use GoetasWebservices\XML\XSDReader\Schema\Type\BaseComplexType;
use GoetasWebservices\XML\XSDReader\Schema\Type\ComplexType;
use GoetasWebservices\XML\XSDReader\Schema\Type\ComplexTypeSimpleContent;
use GoetasWebservices\XML\XSDReader\Schema\Type\SimpleType;
use GoetasWebservices\XML\XSDReader\Schema\Type\Type;
use GoetasWebservices\XML\XSDReader\Utils\UrlUtils;
use RuntimeException;

class SchemaReader
{
    /**
     * @return mixed[]
     */
    protected static function splitParts(
        DOMElement $node,
        string $typeName
    ): array {
        $prefix = null;
        $name = $typeName;
        if (strpos($typeName, ':') !== false) {
            list($prefix, $name) = explode(':', $typeName);
        }

        $namespace = $node->lookupNamespaceUri($prefix ?: '');

        return array(
            $name,
            $namespace,
            $prefix,
        );
    }

    protected function loadAttributeOrElementDef(
        Schema $schema,
        DOMElement $node,
        bool $attributeDef
    ): Closure {
        $name = $node->getAttribute('name');
        if ($attributeDef) {
            $attribute = new AttributeDef($schema, $name);
            $schema->addAttribute($attribute);
        } else {
            $attribute = new ElementDef($schema, $name);
            $schema->addElement($attribute);
        }

        return function () use ($attribute, $node): void {
            $this->fillItem($attribute, $node);
        };
    }

    protected function loadAttributeDef(
        Schema $schema,
        DOMElement $node
    ): Closure {
        return $this->loadAttributeOrElementDef($schema, $node, true);
    }

    protected static function loadSequenceNormaliseMax(
        DOMElement $node,
        ? int $max
    ): ? int {
        return
        (
            (is_int($max) && (bool) $max) ||
            $node->getAttribute('maxOccurs') == 'unbounded' ||
            $node->getAttribute('maxOccurs') > 1
        )
            ? 2
            : null;
    }

    protected function loadSequence(
        ElementContainer $elementContainer,
        DOMElement $node,
        int $max = null
    ): void {
        $max = static::loadSequenceNormaliseMax($node, $max);

        static::againstDOMNodeList(
            $node,
            function (
                DOMElement $node,
                DOMElement $childNode
            ) use (
                $elementContainer,
                $max
            ): void {
                $this->loadSequenceChildNode(
                    $elementContainer,
                    $node,
                    $childNode,
                    $max
                );
            }
        );
    }

    protected function loadSequenceChildNode(
        ElementContainer $elementContainer,
        DOMElement $node,
        DOMElement $childNode,
        ? int $max
    ): void {
        $commonMethods = [
            [
                ['sequence', 'choice', 'all'],
                [$this, 'loadSequenceChildNodeLoadSequence'],
                [
                    $elementContainer,
                    $childNode,
                    $max,
                ],
            ],
        ];
        $methods = [
            'element' => [
                [$this, 'loadSequenceChildNodeLoadElement'],
                [
                    $elementContainer,
                    $node,
                    $childNode,
                    $max,
                ],
            ],
            'group' => [
                [$this, 'loadSequenceChildNodeLoadGroup'],
                [
                    $elementContainer,
                    $node,
                    $childNode,
                ],
            ],
        ];

        $this->maybeCallCallableWithArgs($childNode, $commonMethods, $methods);
    }

    protected function loadSequenceChildNodeLoadSequence(
        ElementContainer $elementContainer,
        DOMElement $childNode,
        ? int $max
    ): void {
        $this->loadSequence($elementContainer, $childNode, $max);
    }

    protected function loadSequenceChildNodeLoadElement(
        ElementContainer $elementContainer,
        DOMElement $node,
        DOMElement $childNode,
        ? int $max
    ): void {
        if ($childNode->hasAttribute('ref')) {
            /**
             * @var ElementDef $referencedElement
             */
            $referencedElement = $this->findSomeElementDef(
                $elementContainer->getSchema(),
                $node,
                $childNode->getAttribute('ref')
            );
            $element = ElementRef::loadElementRef(
                $referencedElement,
                $childNode
            );
        } else {
            $element = Element::loadElement(
                $this,
                $elementContainer->getSchema(),
                $childNode
            );
        }
        if (is_int($max) && (bool) $max) {
            $element->setMax($max);
        }
        $elementContainer->addElement($element);
    }

    protected function loadSequenceChildNodeLoadGroup(
        ElementContainer $elementContainer,
        DOMElement $node,
        DOMElement $childNode
    ): void {
        $this->addGroupAsElement(
            $elementContainer->getSchema(),
            $node,
            $childNode,
            $elementContainer
        );
    }

    protected function addGroupAsElement(
        Schema $schema,
        DOMElement $node,
        DOMElement $childNode,
        ElementContainer $elementContainer
    ): void {
        /**
         * @var Group
         */
        $referencedGroup = $this->findSomething(
            'findGroup',
            $schema,
            $node,
            $childNode->getAttribute('ref')
        );

        $group = $this->loadGroupRef($referencedGroup, $childNode);
        $elementContainer->addElement($group);
    }

    protected function loadGroup(Schema $schema, DOMElement $node): Closure
    {
        $group = static::loadGroupBeforeCheckingChildNodes(
            $schema,
            $node
        );
        static $methods = [
            'sequence' => 'loadSequence',
            'choice' => 'loadSequence',
            'all' => 'loadSequence',
        ];

        return function () use ($group, $node, $methods): void {
            /**
             * @var string[]
             */
            $methods = $methods;
            $this->maybeCallMethodAgainstDOMNodeList(
                $node,
                $group,
                $methods
            );
        };
    }

    protected static function loadGroupBeforeCheckingChildNodes(
        Schema $schema,
        DOMElement $node
    ): Group {
        $group = new Group($schema, $node->getAttribute('name'));
        $group->setDoc(SchemaReader::getDocumentation($node));

        if ($node->hasAttribute('maxOccurs')) {
            /**
             * @var GroupRef
             */
            $group = SchemaReader::maybeSetMax(new GroupRef($group), $node);
        }
        if ($node->hasAttribute('minOccurs')) {
            /**
             * @var GroupRef
             */
            $group = SchemaReader::maybeSetMin(
                $group instanceof GroupRef ? $group : new GroupRef($group),
                $node
            );
        }

        $schema->addGroup($group);

        return $group;
    }

    public function loadGroupRef(
        Group $referenced,
        DOMElement $node
    ): GroupRef {
        $ref = new GroupRef($referenced);
        $ref->setDoc(self::getDocumentation($node));

        self::maybeSetMax($ref, $node);
        self::maybeSetMin($ref, $node);

        return $ref;
    }

    protected function loadComplexTypeBeforeCallbackCallback(
        Schema $schema,
        DOMElement $node
    ): BaseComplexType {
        /**
         * @var bool
         */
        $isSimple = false;

        static::againstDOMNodeList(
            $node,
            function (
                DOMElement $node,
                DOMElement $childNode
            ) use (
                &$isSimple
            ): void {
                if ($isSimple) {
                    return;
                }
                if ($childNode->localName === 'simpleContent') {
                    $isSimple = true;
                }
            }
        );

        $type = $isSimple ? new ComplexTypeSimpleContent($schema, $node->getAttribute('name')) : new ComplexType($schema, $node->getAttribute('name'));

        $type->setDoc(static::getDocumentation($node));
        if ($node->getAttribute('name')) {
            $schema->addType($type);
        }

        return $type;
    }

    protected function loadComplexType(
        Schema $schema,
        DOMElement $node,
        Closure $callback = null
    ): Closure {
        $type = $this->loadComplexTypeBeforeCallbackCallback($schema, $node);

        return $this->makeCallbackCallback(
            $type,
            $node,
            function (
                DOMElement $node,
                DOMElement $childNode
            ) use (
                $schema,
                $type
            ): void {
                $this->loadComplexTypeFromChildNode(
                    $type,
                    $node,
                    $childNode,
                    $schema
                );
            },
            $callback
        );
    }

    protected function loadComplexTypeFromChildNode(
        BaseComplexType $type,
        DOMElement $node,
        DOMElement $childNode,
        Schema $schema
    ): void {
        $commonMethods = [
            [
                ['sequence', 'choice', 'all'],
                [$this, 'maybeLoadSequenceFromElementContainer'],
                [
                    $type,
                    $childNode,
                ],
            ],
        ];
        $methods = [
            'attribute' => [
                [$type, 'addAttributeFromAttributeOrRef'],
                [
                    $this,
                    $childNode,
                    $schema,
                    $node,
                ],
            ],
            'attributeGroup' => [
                (AttributeGroup::class.'::findSomethingLikeThis'),
                [
                    $this,
                    $schema,
                    $node,
                    $childNode,
                    $type,
                ],
            ],
        ];
        if (
            $type instanceof ComplexType
        ) {
            $methods['group'] = [
                [$this, 'addGroupAsElement'],
                [
                    $schema,
                    $node,
                    $childNode,
                    $type,
                ],
            ];
        }

        $this->maybeCallCallableWithArgs($childNode, $commonMethods, $methods);
    }

    protected function loadSimpleType(
        Schema $schema,
        DOMElement $node,
        Closure $callback = null
    ): Closure {
        $type = new SimpleType($schema, $node->getAttribute('name'));
        $type->setDoc(static::getDocumentation($node));
        if ($node->getAttribute('name')) {
            $schema->addType($type);
        }

        return $this->makeCallbackCallback(
            $type,
            $node,
            $this->CallbackGeneratorMaybeCallMethodAgainstDOMNodeList(
                $type,
                [
                    'union' => 'loadUnion',
                    'list' => 'loadList',
                ]
            ),
            $callback
        );
    }

    protected function loadList(SimpleType $type, DOMElement $node): void
    {
        if ($node->hasAttribute('itemType')) {
            $listType = $this->findSomeSimpleType($type, $node);
            $type->setList($listType);
        } else {
            $addCallback = function (SimpleType $list) use ($type): void {
                $type->setList($list);
            };

            $this->loadTypeWithCallbackOnChildNodes(
                $type->getSchema(),
                $node,
                $addCallback
            );
        }
    }

    protected function loadUnion(SimpleType $type, DOMElement $node): void
    {
        if ($node->hasAttribute('memberTypes')) {
            $types = preg_split('/\s+/', $node->getAttribute('memberTypes'));
            foreach ($types as $typeName) {
                $unionType = $this->findSomeSimpleTypeFromAttribute(
                    $type,
                    $node,
                    $typeName
                );
                $type->addUnion($unionType);
            }
        }
        $addCallback = function (SimpleType $unType) use ($type): void {
            $type->addUnion($unType);
        };

        $this->loadTypeWithCallbackOnChildNodes(
            $type->getSchema(),
            $node,
            $addCallback
        );
    }

    protected function loadExtensionChildNodes(
        BaseComplexType $type,
        DOMElement $node
    ): void {
        static::againstDOMNodeList(
            $node,
            function (
                DOMElement $node,
                DOMElement $childNode
            ) use (
                $type
            ): void {
                $commonMethods = [
                    [
                        ['sequence', 'choice', 'all'],
                        [$this, 'maybeLoadSequenceFromElementContainer'],
                        [
                            $type,
                            $childNode,
                        ],
                    ],
                ];
                $methods = [
                    'attribute' => [
                        [$type, 'addAttributeFromAttributeOrRef'],
                        [
                            $this,
                            $childNode,
                            $type->getSchema(),
                            $node,
                        ],
                    ],
                    'attributeGroup' => [
                        (AttributeGroup::class.'::findSomethingLikeThis'),
                        [
                            $this,
                            $type->getSchema(),
                            $node,
                            $childNode,
                            $type,
                        ],
                    ],
                ];

                $this->maybeCallCallableWithArgs(
                    $childNode,
                    $commonMethods,
                    $methods
                );
            }
        );
    }

    protected function loadExtension(
        BaseComplexType $type,
        DOMElement $node
    ): void {
        $extension = new Extension();
        $type->setExtension($extension);

        if ($node->hasAttribute('base')) {
            $this->findAndSetSomeBase(
                $type,
                $extension,
                $node
            );
        }
        $this->loadExtensionChildNodes($type, $node);
    }

    protected function loadRestriction(Type $type, DOMElement $node): void
    {
        $restriction = new Restriction();
        $type->setRestriction($restriction);
        if ($node->hasAttribute('base')) {
            $this->findAndSetSomeBase($type, $restriction, $node);
        } else {
            $addCallback = function (Type $restType) use (
                $restriction
            ): void {
                $restriction->setBase($restType);
            };

            $this->loadTypeWithCallbackOnChildNodes(
                $type->getSchema(),
                $node,
                $addCallback
            );
        }
        self::againstDOMNodeList(
            $node,
            function (
                DOMElement $node,
                DOMElement $childNode
            ) use (
                $restriction
            ): void {
                static::maybeLoadRestrictionOnChildNode(
                    $restriction,
                    $childNode
                );
            }
        );
    }

    protected static function maybeLoadRestrictionOnChildNode(
        Restriction $restriction,
        DOMElement $childNode
    ): void {
        if (
            in_array(
                $childNode->localName,
                [
                    'enumeration',
                    'pattern',
                    'length',
                    'minLength',
                    'maxLength',
                    'minInclusive',
                    'maxInclusive',
                    'minExclusive',
                    'maxExclusive',
                    'fractionDigits',
                    'totalDigits',
                    'whiteSpace',
                ],
                true
            )
        ) {
            static::definitelyLoadRestrictionOnChildNode(
                $restriction,
                $childNode
            );
        }
    }

    protected static function definitelyLoadRestrictionOnChildNode(
        Restriction $restriction,
        DOMElement $childNode
    ): void {
        $restriction->addCheck(
            $childNode->localName,
            [
                'value' => $childNode->getAttribute('value'),
                'doc' => self::getDocumentation($childNode),
            ]
        );
    }

    protected function loadElementDef(
        Schema $schema,
        DOMElement $node
    ): Closure {
        return $this->loadAttributeOrElementDef($schema, $node, false);
    }

    protected function fillTypeNode(
        Type $type,
        DOMElement $node,
        bool $checkAbstract = false
    ): void {
        if ($checkAbstract) {
            $type->setAbstract($node->getAttribute('abstract') === 'true' || $node->getAttribute('abstract') === '1');
        }
        static $methods = [
            'restriction' => 'loadRestriction',
            'extension' => 'maybeLoadExtensionFromBaseComplexType',
            'simpleContent' => 'fillTypeNode',
            'complexContent' => 'fillTypeNode',
        ];

        /**
         * @var string[]
         */
        $methods = $methods;

        $this->maybeCallMethodAgainstDOMNodeList($node, $type, $methods);
    }

    protected function fillItemNonLocalType(
        Item $element,
        DOMElement $node
    ): void {
        if ($node->getAttribute('type')) {
            $type = $this->findSomeTypeType($element, $node, 'type');
        } else {
            $type = $this->findSomeTypeTypeFromAttribute(
                $element,
                $node
            );
        }

        $element->setType($type);
    }

    protected function findSomeType(
        SchemaItem $fromThis,
        DOMElement $node,
        string $attributeName
    ): SchemaItem {
        return $this->findSomeTypeFromAttribute(
            $fromThis,
            $node,
            $node->getAttribute($attributeName)
        );
    }

    protected function findSomeTypeType(SchemaItem $element, DOMElement $node, string $attributeName): Type
    {
        /**
         * @var Type $out
         */
        $out = $this->findSomeType($element, $node, $attributeName);

        return $out;
    }

    protected function findSomeTypeTypeFromAttribute(
        SchemaItem $element,
        DOMElement $node
    ): Type {
        /**
         * @var Type $out
         */
        $out = $this->findSomeTypeFromAttribute(
            $element,
            $node,
            ($node->lookupPrefix(self::XSD_NS).':anyType')
        );

        return $out;
    }

    protected function findSomeSimpleType(SchemaItem $type, DOMElement $node): SimpleType
    {
        /**
         * @var SimpleType $out
         */
        $out = $this->findSomeType($type, $node, 'itemType');

        return $out;
    }

    protected function findSomeTypeFromAttribute(
        SchemaItem $fromThis,
        DOMElement $node,
        string $attributeName
    ): SchemaItem {
        /**
         * @var SchemaItem
         */
        $out = $this->findSomething(
            'findType',
            $fromThis->getSchema(),
            $node,
            $attributeName
        );

        return $out;
    }

    protected function findSomeSimpleTypeFromAttribute(
        SchemaItem $type,
        DOMElement $node,
        string $typeName
    ): SimpleType {
        /**
         * @var SimpleType $out
         */
        $out = $this->findSomeTypeFromAttribute(
            $type,
            $node,
            $typeName
        );

        return $out;
    }

    /**
     * @param mixed[][] $commonMethods
     * @param mixed[][] $methods
     * @param mixed[][] $commonArguments
     *
     * @return mixed
     */
    protected function maybeCallCallableWithArgs(
        DOMElement $childNode,
        array $commonMethods = [],
        array $methods = [],
        array $commonArguments = []
    ) {
        foreach ($commonMethods as $commonMethodsSpec) {
            list($localNames, $callable, $args) = $commonMethodsSpec;

            /**
             * @var string[]
             */
            $localNames = $localNames;

            /**
             * @var callable
             */
            $callable = $callable;

            /**
             * @var mixed[]
             */
            $args = $args;

            if (in_array($childNode->localName, $localNames)) {
                return call_user_func_array($callable, $args);
            }
        }
        foreach ($commonArguments as $commonArgumentSpec) {
            /*
            * @var mixed[] $commonArgumentSpec
            */
            list($callables, $args) = $commonArgumentSpec;

            /**
             * @var callable[]
             */
            $callables = $callables;

            /**
             * @var mixed[]
             */
            $args = $args;

            if (isset($callables[$childNode->localName])) {
                return call_user_func_array(
                    $callables[$childNode->localName],
                    $args
                );
            }
        }
        if (isset($methods[$childNode->localName])) {
            list($callable, $args) = $methods[$childNode->localName];

            /**
             * @var callable
             */
            $callable = $callable;

            /**
             * @var mixed[]
             */
            $args = $args;

            return call_user_func_array($callable, $args);
        }
    }

    protected function maybeLoadSequenceFromElementContainer(
        BaseComplexType $type,
        DOMElement $childNode
    ): void {
        $this->maybeLoadThingFromThing(
            $type,
            $childNode,
            ElementContainer::class,
            'loadSequence'
        );
    }

    /**
     * @param string $instanceof
     * @param string $passTo
     */
    protected function maybeLoadThingFromThing(
        Type $type,
        DOMElement $childNode,
        $instanceof,
        $passTo
    ): void {
        if (!is_a($type, $instanceof, true)) {
            /**
             * @var string
             */
            $class = static::class;
            throw new RuntimeException(
                'Argument 1 passed to '.
                __METHOD__.
                ' needs to be an instance of '.
                $instanceof.
                ' when passed onto '.
                $class.
                '::'.
                $passTo.
                '(), '.
                (string) get_class($type).
                ' given.'
            );
        }

        $this->$passTo($type, $childNode);
    }

    protected function makeCallbackCallback(
        Type $type,
        DOMElement $node,
        Closure $callbackCallback,
        Closure $callback = null
    ): Closure {
        return function (
        ) use (
            $type,
            $node,
            $callbackCallback,
            $callback
        ): void {
            $this->runCallbackAgainstDOMNodeList(
                $type,
                $node,
                $callbackCallback,
                $callback
            );
        };
    }

    protected function runCallbackAgainstDOMNodeList(
        Type $type,
        DOMElement $node,
        Closure $againstNodeList,
        Closure $callback = null
    ): void {
        $this->fillTypeNode($type, $node, true);

        static::againstDOMNodeList($node, $againstNodeList);

        if ($callback) {
            call_user_func($callback, $type);
        }
    }

    protected function maybeLoadExtensionFromBaseComplexType(
        Type $type,
        DOMElement $childNode
    ): void {
        $this->maybeLoadThingFromThing(
            $type,
            $childNode,
            BaseComplexType::class,
            'loadExtension'
        );
    }

    const XSD_NS = 'http://www.w3.org/2001/XMLSchema';

    const XML_NS = 'http://www.w3.org/XML/1998/namespace';

    /**
     * @var string[]
     */
    protected $knownLocationSchemas = [
        'http://www.w3.org/2001/xml.xsd' => (
            __DIR__.'/Resources/xml.xsd'
        ),
        'http://www.w3.org/2001/XMLSchema.xsd' => (
            __DIR__.'/Resources/XMLSchema.xsd'
        ),
        'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd' => (
            __DIR__.'/Resources/oasis-200401-wss-wssecurity-secext-1.0.xsd'
        ),
        'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd' => (
            __DIR__.'/Resources/oasis-200401-wss-wssecurity-utility-1.0.xsd'
        ),
        'https://www.w3.org/TR/xmldsig-core/xmldsig-core-schema.xsd' => (
            __DIR__.'/Resources/xmldsig-core-schema.xsd'
        ),
        'http://www.w3.org/TR/xmldsig-core/xmldsig-core-schema.xsd' => (
            __DIR__.'/Resources/xmldsig-core-schema.xsd'
        ),
    ];

    /**
     * @var string[]
     */
    protected static $globalSchemaInfo = array(
        self::XML_NS => 'http://www.w3.org/2001/xml.xsd',
        self::XSD_NS => 'http://www.w3.org/2001/XMLSchema.xsd',
    );

    public function addKnownSchemaLocation(
        string $remote,
        string $local
    ): void {
        $this->knownLocationSchemas[$remote] = $local;
    }

    public function hasKnownSchemaLocation(string $remote): bool
    {
        return isset($this->knownLocationSchemas[$remote]);
    }

    public function getKnownSchemaLocation(string $remote): string
    {
        return $this->knownLocationSchemas[$remote];
    }

    public static function getDocumentation(DOMElement $node): string
    {
        $doc = '';
        static::againstDOMNodeList(
            $node,
            function (
                DOMElement $node,
                DOMElement $childNode
            ) use (
                &$doc
            ): void {
                if ($childNode->localName == 'annotation') {
                    $doc .= static::getDocumentation($childNode);
                } elseif ($childNode->localName == 'documentation') {
                    $doc .= $childNode->nodeValue;
                }
            }
        );
        $doc = preg_replace('/[\t ]+/', ' ', $doc);

        return trim($doc);
    }

    /**
     * @param mixed    ...$args
     * @param string[] $methods
     */
    public function maybeCallMethod(
        array $methods,
        string $key,
        DOMNode $childNode,
        ...$args
    ): ? Closure {
        if ($childNode instanceof DOMElement && isset($methods[$key])) {
            $method = $methods[$key];

            /**
             * @var Closure|null
             */
            $append = $this->$method(...$args);

            if ($append instanceof Closure) {
                return $append;
            }
        }

        return null;
    }

    /**
     * @return Closure[]
     */
    public function schemaNode(
        Schema $schema,
        DOMElement $node,
        Schema $parent = null
    ): array {
        $schema->setSchemaThingsFromNode($node, $parent);
        $functions = array();

        $schemaReaderMethods = [
            'attributeGroup' => (
                AttributeGroup::class.
                '::loadAttributeGroup'
            ),
        ];

        $thisMethods = [
            'include' => [$this, 'loadImport'],
            'import' => [$this, 'loadImport'],
            'element' => [$this, 'loadElementDef'],
            'attribute' => [$this, 'loadAttributeDef'],
            'group' => [$this, 'loadGroup'],
            'complexType' => [$this, 'loadComplexType'],
            'simpleType' => [$this, 'loadSimpleType'],
        ];

        static::againstDOMNodeList(
            $node,
            function (
                DOMElement $node,
                DOMElement $childNode
            ) use (
                $schemaReaderMethods,
                $schema,
                $thisMethods,
                &$functions
            ): void {
                /**
                 * @var Closure|null
                 */
                $callback = $this->maybeCallCallableWithArgs(
                    $childNode,
                    [],
                    [],
                    [
                        [
                            $schemaReaderMethods,
                            [
                                $this,
                                $schema,
                                $childNode,
                            ],
                        ],
                        [
                            $thisMethods,
                            [
                                $schema,
                                $childNode,
                            ],
                        ],
                    ]
                );

                if ($callback instanceof Closure) {
                    $functions[] = $callback;
                }
            }
        );

        return $functions;
    }

    public static function maybeSetMax(
        InterfaceSetMinMax $ref,
        DOMElement $node
    ): InterfaceSetMinMax {
        if (
            $node->hasAttribute('maxOccurs')
        ) {
            $ref->setMax($node->getAttribute('maxOccurs') == 'unbounded' ? -1 : (int) $node->getAttribute('maxOccurs'));
        }

        return $ref;
    }

    public static function maybeSetMin(
        InterfaceSetMinMax $ref,
        DOMElement $node
    ): InterfaceSetMinMax {
        if ($node->hasAttribute('minOccurs')) {
            $ref->setMin((int) $node->getAttribute('minOccurs'));
        }

        return $ref;
    }

    public function findAndSetSomeBase(
        Type $type,
        Base $setBaseOnThis,
        DOMElement $node
    ): void {
        $parent = $this->findSomeTypeType($type, $node, 'base');
        $setBaseOnThis->setBase($parent);
    }

    /**
     * @throws TypeException
     *
     * @return ElementItem|Group|AttributeItem|AttributeGroup|Type
     */
    public function findSomething(
        string $finder,
        Schema $schema,
        DOMElement $node,
        string $typeName
    ) {
        list($name, $namespace) = static::splitParts($node, $typeName);

        /**
         * @var string|null
         */
        $namespace = $namespace ?: $schema->getTargetNamespace();

        try {
            /**
             * @var ElementItem|Group|AttributeItem|AttributeGroup|Type
             */
            $out = $schema->$finder($name, $namespace);

            return $out;
        } catch (TypeNotFoundException $e) {
            throw new TypeException(sprintf("Can't find %s named {%s}#%s, at line %d in %s ", strtolower(substr($finder, 4)), $namespace, $name, $node->getLineNo(), $node->ownerDocument->documentURI), 0, $e);
        }
    }

    protected function findSomeElementDef(Schema $schema, DOMElement $node, string $typeName): ElementDef
    {
        /**
         * @var ElementDef $out
         */
        $out = $this->findSomething('findElement', $schema, $node, $typeName);

        return $out;
    }

    public function fillItem(Item $element, DOMElement $node): void
    {
        /**
         * @var bool
         */
        $skip = false;
        static::againstDOMNodeList(
            $node,
            function (
                DOMElement $node,
                DOMElement $childNode
            ) use (
                $element,
                &$skip
            ): void {
                if (
                    !$skip &&
                    in_array(
                        $childNode->localName,
                        [
                            'complexType',
                            'simpleType',
                        ]
                    )
                ) {
                    $this->loadTypeWithCallback(
                        $element->getSchema(),
                        $childNode,
                        function (Type $type) use ($element): void {
                            $element->setType($type);
                        }
                    );
                    $skip = true;
                }
            }
        );
        if ($skip) {
            return;
        }
        $this->fillItemNonLocalType($element, $node);
    }

    /**
     * @var Schema|null
     */
    protected $globalSchema;

    /**
     * @return Schema[]
     */
    protected function setupGlobalSchemas(array &$callbacks): array
    {
        $globalSchemas = array();
        foreach (self::$globalSchemaInfo as $namespace => $uri) {
            Schema::setLoadedFile(
                $uri,
                $globalSchemas[$namespace] = $schema = new Schema()
            );
            if ($namespace === self::XSD_NS) {
                $this->globalSchema = $schema;
            }
            $xml = $this->getDOM($this->knownLocationSchemas[$uri]);
            $callbacks = array_merge($callbacks, $this->schemaNode($schema, $xml->documentElement));
        }

        return $globalSchemas;
    }

    /**
     * @return string[]
     */
    public function getGlobalSchemaInfo(): array
    {
        return self::$globalSchemaInfo;
    }

    public function getGlobalSchema(): Schema
    {
        if (!$this->globalSchema) {
            $callbacks = array();
            $globalSchemas = $this->setupGlobalSchemas($callbacks);

            $globalSchemas[static::XSD_NS]->addType(new SimpleType($globalSchemas[static::XSD_NS], 'anySimpleType'));
            $globalSchemas[static::XSD_NS]->addType(new SimpleType($globalSchemas[static::XSD_NS], 'anyType'));

            $globalSchemas[static::XML_NS]->addSchema(
                $globalSchemas[static::XSD_NS],
                (string) static::XSD_NS
            );
            $globalSchemas[static::XSD_NS]->addSchema(
                $globalSchemas[static::XML_NS],
                (string) static::XML_NS
            );

            /**
             * @var Closure
             */
            foreach ($callbacks as $callback) {
                $callback();
            }
        }

        /**
         * @var Schema
         */
        $out = $this->globalSchema;

        return $out;
    }

    public function readNode(
        DOMElement $node,
        string $file = 'schema.xsd'
    ): Schema {
        $fileKey = $node->hasAttribute('targetNamespace') ? $this->getNamespaceSpecificFileIndex($file, $node->getAttribute('targetNamespace')) : $file;
        Schema::setLoadedFile($fileKey, $rootSchema = new Schema());

        $rootSchema->addSchema($this->getGlobalSchema());
        $callbacks = $this->schemaNode($rootSchema, $node);

        foreach ($callbacks as $callback) {
            call_user_func($callback);
        }

        return $rootSchema;
    }

    /**
     * It is possible that a single file contains multiple <xsd:schema/> nodes, for instance in a WSDL file.
     *
     * Each of these  <xsd:schema/> nodes typically target a specific namespace. Append the target namespace to the
     * file to distinguish between multiple schemas in a single file.
     */
    public function getNamespaceSpecificFileIndex(
        string $file,
        string $targetNamespace
    ): string {
        return $file.'#'.$targetNamespace;
    }

    /**
     * @throws IOException
     */
    public function readString(
        string $content,
        string $file = 'schema.xsd'
    ): Schema {
        $xml = new DOMDocument('1.0', 'UTF-8');
        if (!$xml->loadXML($content)) {
            throw new IOException("Can't load the schema");
        }
        $xml->documentURI = $file;

        return $this->readNode($xml->documentElement, $file);
    }

    public function readFile(string $file): Schema
    {
        $xml = $this->getDOM($file);

        return $this->readNode($xml->documentElement, $file);
    }

    /**
     * @throws IOException
     */
    public function getDOM(string $file): DOMDocument
    {
        $xml = new DOMDocument('1.0', 'UTF-8');
        if (!$xml->load($file)) {
            throw new IOException("Can't load the file $file");
        }

        return $xml;
    }

    public static function againstDOMNodeList(
        DOMElement $node,
        Closure $againstNodeList
    ): void {
        $limit = $node->childNodes->length;
        for ($i = 0; $i < $limit; $i += 1) {
            /**
             * @var DOMNode
             */
            $childNode = $node->childNodes->item($i);

            if ($childNode instanceof DOMElement) {
                $againstNodeList(
                    $node,
                    $childNode
                );
            }
        }
    }

    public function maybeCallMethodAgainstDOMNodeList(
        DOMElement $node,
        SchemaItem $type,
        array $methods
    ): void {
        static::againstDOMNodeList(
            $node,
            $this->CallbackGeneratorMaybeCallMethodAgainstDOMNodeList(
                $type,
                $methods
            )
        );
    }

    /**
     * @return Closure
     */
    public function CallbackGeneratorMaybeCallMethodAgainstDOMNodeList(
        SchemaItem $type,
        array $methods
    ) {
        return function (
            DOMElement $node,
            DOMElement $childNode
        ) use (
            $methods,
            $type
        ): void {
            /**
             * @var string[]
             */
            $methods = $methods;

            $this->maybeCallMethod(
                $methods,
                $childNode->localName,
                $childNode,
                $type,
                $childNode
            );
        };
    }

    public function loadTypeWithCallbackOnChildNodes(
        Schema $schema,
        DOMElement $node,
        Closure $callback
    ): void {
        self::againstDOMNodeList(
            $node,
            function (
                DOMElement $node,
                DOMElement $childNode
            ) use (
                $schema,
                $callback
            ): void {
                $this->loadTypeWithCallback(
                    $schema,
                    $childNode,
                    $callback
                );
            }
        );
    }

    public function loadTypeWithCallback(
        Schema $schema,
        DOMElement $childNode,
        Closure $callback
    ): void {
        $methods = [
            'complexType' => 'loadComplexType',
            'simpleType' => 'loadSimpleType',
        ];

        /**
         * @var Closure|null
         */
        $func = $this->maybeCallMethod(
            $methods,
            $childNode->localName,
            $childNode,
            $schema,
            $childNode,
            $callback
        );

        if ($func instanceof Closure) {
            call_user_func($func);
        }
    }

    public function loadImport(
        Schema $schema,
        DOMElement $node
    ): Closure {
        $base = urldecode($node->ownerDocument->documentURI);
        $file = UrlUtils::resolveRelativeUrl($base, $node->getAttribute('schemaLocation'));

        $namespace = $node->getAttribute('namespace');

        $keys = $this->loadImportFreshKeys($namespace, $file);

        if (
            Schema::hasLoadedFile(...$keys)
        ) {
            $schema->addSchema(Schema::getLoadedFile(...$keys));

            return function (): void {
            };
        }

        return $this->loadImportFresh($namespace, $schema, $file);
    }

    protected function loadImportFreshKeys(
        string $namespace,
        string $file
    ): array {
        $globalSchemaInfo = $this->getGlobalSchemaInfo();

        $keys = [];

        if (isset($globalSchemaInfo[$namespace])) {
            $keys[] = $globalSchemaInfo[$namespace];
        }

        $keys[] = $this->getNamespaceSpecificFileIndex(
            $file,
            $namespace
        );

        $keys[] = $file;

        return $keys;
    }

    protected function loadImportFreshCallbacksNewSchema(
        string $namespace,
        Schema $schema,
        string $file
    ): Schema {
        /**
         * @var Schema $newSchema
         */
        $newSchema = Schema::setLoadedFile(
            $file,
            ($namespace ? new Schema() : $schema)
        );

        if ($namespace) {
            $newSchema->addSchema($this->getGlobalSchema());
            $schema->addSchema($newSchema);
        }

        return $newSchema;
    }

    /**
     * @return Closure[]
     */
    protected function loadImportFreshCallbacks(
        string $namespace,
        Schema $schema,
        string $file
    ): array {
        /**
         * @var string
         */
        $file = $file;

        return $this->schemaNode(
            $this->loadImportFreshCallbacksNewSchema(
                $namespace,
                $schema,
                $file
            ),
            $this->getDOM(
                $this->hasKnownSchemaLocation($file)
                    ? $this->getKnownSchemaLocation($file)
                    : $file
            )->documentElement,
            $schema
        );
    }

    protected function loadImportFresh(
        string $namespace,
        Schema $schema,
        string $file
    ): Closure {
        return function () use ($namespace, $schema, $file): void {
            foreach (
                $this->loadImportFreshCallbacks(
                    $namespace,
                    $schema,
                    $file
                ) as $callback
            ) {
                $callback();
            }
        };
    }
}
