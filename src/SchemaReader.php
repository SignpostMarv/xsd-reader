<?php
declare(strict_types = 1);
namespace GoetasWebservices\XML\XSDReader;

use Closure;
use DOMDocument;
use DOMElement;
use DOMNode;
use GoetasWebservices\XML\XSDReader\Exception\IOException;
use GoetasWebservices\XML\XSDReader\Exception\TypeException;
use GoetasWebservices\XML\XSDReader\Schema\Attribute\Attribute;
use GoetasWebservices\XML\XSDReader\Schema\Attribute\AttributeDef;
use GoetasWebservices\XML\XSDReader\Schema\Attribute\AttributeItem;
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

    const XSD_NS = "http://www.w3.org/2001/XMLSchema";

    const XML_NS = "http://www.w3.org/XML/1998/namespace";

    /**
    * @var string[]
    */
    private $knownLocationSchemas = [
        'http://www.w3.org/2001/xml.xsd' => (
            __DIR__ . '/Resources/xml.xsd'
        ),
        'http://www.w3.org/2001/XMLSchema.xsd' => (
            __DIR__ . '/Resources/XMLSchema.xsd'
        ),
        'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd' => (
            __DIR__ . '/Resources/oasis-200401-wss-wssecurity-secext-1.0.xsd'
        ),
        'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd' => (
            __DIR__ . '/Resources/oasis-200401-wss-wssecurity-utility-1.0.xsd'
        ),
        'https://www.w3.org/TR/xmldsig-core/xmldsig-core-schema.xsd' => (
            __DIR__ . '/Resources/xmldsig-core-schema.xsd'
        ),
        'http://www.w3.org/TR/xmldsig-core/xmldsig-core-schema.xsd' => (
            __DIR__ . '/Resources/xmldsig-core-schema.xsd'
        ),
    ];

    /**
    * @var string[]
    */
    private static $globalSchemaInfo = array(
        self::XML_NS => 'http://www.w3.org/2001/xml.xsd',
        self::XSD_NS => 'http://www.w3.org/2001/XMLSchema.xsd'
    );

    public function __construct()
    {
    }

    public function addKnownSchemaLocation(
        string $remote,
        string $local
    ) : void {
        $this->knownLocationSchemas[$remote] = $local;
    }

    private function loadAttributeGroup(
        Schema $schema,
        DOMElement $node
    ) : Closure {
        return AttributeGroup::loadAttributeGroup($this, $schema, $node);
    }

    private function loadAttributeOrElementDef(
        Schema $schema,
        DOMElement $node,
        bool $attributeDef
    ) : Closure {
        $name = $node->getAttribute('name');
        if ($attributeDef) {
            $attribute = new AttributeDef($schema, $name);
            $schema->addAttribute($attribute);
        } else {
            $attribute = new ElementDef($schema, $name);
            $schema->addElement($attribute);
        }


        return function () use ($attribute, $node) : void {
            $this->fillItem($attribute, $node);
        };
    }

    private function loadAttributeDef(
        Schema $schema,
        DOMElement $node
    ) : Closure {
        return $this->loadAttributeOrElementDef($schema, $node, true);
    }

    public static function getDocumentation(DOMElement $node) : string
    {
        $doc = '';
        foreach ($node->childNodes as $childNode) {
            if ($childNode->localName == "annotation") {
                $doc .= static::getDocumentation($childNode);
            } elseif ($childNode->localName == 'documentation') {
                $doc .= ($childNode->nodeValue);
            }
        }
        $doc = preg_replace('/[\t ]+/', ' ', $doc);
        return trim($doc);
    }

    private function setSchemaThingsFromNode(
        Schema $schema,
        DOMElement $node,
        ? Schema $parent
    ) : void {
        $schema->setDoc(static::getDocumentation($node));

        if ($node->hasAttribute("targetNamespace")) {
            $schema->setTargetNamespace($node->getAttribute("targetNamespace"));
        } elseif ($parent) {
            $schema->setTargetNamespace($parent->getTargetNamespace());
        }
        $schema->setElementsQualification($node->getAttribute("elementFormDefault") == "qualified");
        $schema->setAttributesQualification($node->getAttribute("attributeFormDefault") == "qualified");
        $schema->setDoc(static::getDocumentation($node));
    }

    public function maybeCallMethod(
        array $methods,
        string $key,
        DOMNode $childNode,
        ...$args
    ) : ? Closure {
        if ($childNode instanceof DOMElement && isset($methods[$key])) {
            $method = $methods[$key];

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
    private function schemaNode(
        Schema $schema,
        DOMElement $node,
        Schema $parent = null
    ) : array {
        $this->setSchemaThingsFromNode($schema, $node, $parent);
        $functions = array();

        static $methods = [
            'include' => 'loadImport',
            'import' => 'loadImport',
            'element' => 'loadElementDef',
            'attribute' => 'loadAttributeDef',
            'attributeGroup' => 'loadAttributeGroup',
            'group' => 'loadGroup',
            'complexType' => 'loadComplexType',
            'simpleType' => 'loadSimpleType',
        ];

        foreach ($node->childNodes as $childNode) {
            $callback = $this->maybeCallMethod(
                $methods,
                (string) $childNode->localName,
                $childNode,
                $schema,
                $childNode
            );

            if ($callback instanceof Closure) {
                $functions[] = $callback;
            }
        }

        return $functions;
    }

    public static function maybeSetMax(
        InterfaceSetMinMax $ref,
        DOMElement $node
    ) : InterfaceSetMinMax {
        if (
            $node->hasAttribute("maxOccurs")
        ) {
            $ref->setMax($node->getAttribute("maxOccurs") == "unbounded" ? -1 : (int)$node->getAttribute("maxOccurs"));
        }

        return $ref;
    }

    public static function maybeSetMin(
        InterfaceSetMinMax $ref,
        DOMElement $node
    ) : InterfaceSetMinMax {
        if ($node->hasAttribute("minOccurs")) {
            $ref->setMin((int) $node->getAttribute("minOccurs"));
        }

        return $ref;
    }

    private static function loadSequenceNormaliseMax(
        DOMElement $node,
        ? int $max
    ) : ? int {
        return
        (
            (is_int($max) && (bool) $max) ||
            $node->getAttribute("maxOccurs") == "unbounded" ||
            $node->getAttribute("maxOccurs") > 1
        )
            ? 2
            : null;
    }

    private function loadSequence(
        ElementContainer $elementContainer,
        DOMElement $node,
        int $max = null
    ) : void {
        $max = static::loadSequenceNormaliseMax($node, $max);

        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $this->loadSequenceChildNode(
                    $elementContainer,
                    $node,
                    $childNode,
                    $max
                );
            }
        }
    }

    private function loadSequenceChildNode(
        ElementContainer $elementContainer,
        DOMElement $node,
        DOMElement $childNode,
        ? int $max
    ) : void {
        $loadSeq = $this->makeLoadSequenceChildNodeLoadSequence(
            $elementContainer,
            $childNode,
            $max
        );
        $methods = [
            'choice' => $loadSeq,
            'sequence' => $loadSeq,
            'all' => $loadSeq,
            'element' => $this->makeLoadSequenceChildNodeLoadElement(
                $elementContainer,
                $node,
                $childNode,
                $max
            ),
            'group' => $this->makeLoadSequenceChildNodeLoadGroup(
                $elementContainer,
                $node,
                $childNode
            ),
        ];

        if (isset($methods[$childNode->localName])) {
            $method = $methods[$childNode->localName];
            $method();
        }
    }

    private function makeLoadSequenceChildNodeLoadSequence(
        ElementContainer $elementContainer,
        DOMElement $childNode,
        ? int $max
    ) : Closure {
        return function () use ($elementContainer, $childNode, $max) : void {
            $this->loadSequence($elementContainer, $childNode, $max);
        };
    }

    private function makeLoadSequenceChildNodeLoadElement(
        ElementContainer $elementContainer,
        DOMElement $node,
        DOMElement $childNode,
        ? int $max
    ) : Closure {
        return function () use (
                $elementContainer,
                $node,
                $childNode,
                $max
            ) : void {
                if ($childNode->hasAttribute("ref")) {
                    /**
                    * @var ElementDef $referencedElement
                    */
                    $referencedElement = $this->findSomething('findElement', $elementContainer->getSchema(), $node, $childNode->getAttribute("ref"));
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
        };
    }

    private function makeLoadSequenceChildNodeLoadGroup(
        ElementContainer $elementContainer,
        DOMElement $node,
        DOMElement $childNode
    ) : Closure {
        return function () use (
                $elementContainer,
                $node,
                $childNode
            ) : void {
                $this->addGroupAsElement(
                    $elementContainer->getSchema(),
                    $node,
                    $childNode,
                    $elementContainer
                );
        };
    }

    private function addGroupAsElement(
        Schema $schema,
        DOMElement $node,
        DOMElement $childNode,
        ElementContainer $elementContainer
    ) : void {
        /**
        * @var Group $referencedGroup
        */
        $referencedGroup = $this->findSomething(
            'findGroup',
            $schema,
            $node,
            $childNode->getAttribute("ref")
        );

        $group = GroupRef::loadGroupRef($referencedGroup, $childNode);
        $elementContainer->addElement($group);
    }

    private function maybeLoadSequenceFromElementContainer(
        BaseComplexType $type,
        DOMElement $childNode
    ) : void {
        if (! ($type instanceof ElementContainer)) {
            throw new RuntimeException(
                '$type passed to ' .
                __FUNCTION__ .
                'expected to be an instance of ' .
                ElementContainer::class .
                ' when child node localName is "group", ' .
                get_class($type) .
                ' given.'
            );
        }
        $this->loadSequence($type, $childNode);
    }

    private function loadGroup(Schema $schema, DOMElement $node) : Closure
    {
        return Group::loadGroup($this, $schema, $node);
    }

    private function loadComplexType(
        Schema $schema,
        DOMElement $node,
        Closure $callback = null
    ) : Closure {
        $isSimple = false;

        foreach ($node->childNodes as $childNode) {
            if ($childNode->localName === "simpleContent") {
                $isSimple = true;
                break;
            }
        }

        $type = $isSimple ? new ComplexTypeSimpleContent($schema, $node->getAttribute("name")) : new ComplexType($schema, $node->getAttribute("name"));

        $type->setDoc(static::getDocumentation($node));
        if ($node->getAttribute("name")) {
            $schema->addType($type);
        }

        return $this->makeCallbackCallback(
            $type,
            $node,
                function (
                    DOMElement $node,
                    DOMElement $childNode
                ) use(
                    $schema,
                    $type
                ) : void {
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

    /**
    * @param Closure|null $callback
    *
    * @return Closure
    */
    private function makeCallbackCallback(
        Type $type,
        DOMElement $node,
        Closure $callbackCallback,
        Closure $callback = null
    ) : Closure {
        return function (
        ) use (
            $type,
            $node,
            $callbackCallback,
            $callback
        ) : void {
            $this->runCallbackAgainstDOMNodeList(
                $type,
                $node,
                $callbackCallback,
                $callback
            );
        };
    }

    private function runCallbackAgainstDOMNodeList(
        Type $type,
        DOMElement $node,
        Closure $againstNodeList,
        Closure $callback = null
    ) : void {
        $this->fillTypeNode($type, $node, true);

        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $againstNodeList(
                    $node,
                    $childNode
                );
            }
        }

        if ($callback) {
            call_user_func($callback, $type);
        }
    }

    private function loadComplexTypeFromChildNode(
        BaseComplexType $type,
        DOMElement $node,
        DOMElement $childNode,
        Schema $schema
    ) : void {
        $maybeLoadSeq = function () use ($type, $childNode) : void {
            $this->maybeLoadSequenceFromElementContainer(
                $type,
                $childNode
            );
        };
        $methods = [
            'sequence' => $maybeLoadSeq,
            'choice' => $maybeLoadSeq,
            'all' => $maybeLoadSeq,
            'attribute' => function () use (
                $childNode,
                $schema,
                $node,
                $type
            ) : void {
                $attribute = Attribute::getAttributeFromAttributeOrRef(
                    $this,
                    $childNode,
                    $schema,
                    $node
                );

                $type->addAttribute($attribute);
            },
            'attributeGroup' => function() use (
                $schema,
                $node,
                $childNode,
                $type
            ) : void {
                AttributeGroup::findSomethingLikeThis(
                    $this,
                    $schema,
                    $node,
                    $childNode,
                    $type
                );
            },
        ];
        if (
            $type instanceof ComplexType
        ) {
            $methods['group'] = function() use (
                $schema,
                $node,
                $childNode,
                $type
            ) : void {
                $this->addGroupAsElement(
                    $schema,
                    $node,
                    $childNode,
                    $type
                );
            };
        }

        if (isset($methods[$childNode->localName])) {
            $method = $methods[$childNode->localName];
            $method();
        }
    }

    private function loadSimpleType(
        Schema $schema,
        DOMElement $node,
        Closure $callback = null
    ) : Closure {
        $type = new SimpleType($schema, $node->getAttribute("name"));
        $type->setDoc(static::getDocumentation($node));
        if ($node->getAttribute("name")) {
            $schema->addType($type);
        }

        static $methods = [
            'union' => 'loadUnion',
            'list' => 'loadList',
        ];

        return $this->makeCallbackCallback(
            $type,
            $node,
            function (
                DOMElement $node,
                DOMElement $childNode
            ) use (
                $methods,
                $type
            ) : void {
                $this->maybeCallMethod(
                    $methods,
                    $childNode->localName,
                    $childNode,
                    $type,
                    $childNode
                );
            },
            $callback
        );
    }

    private function loadList(SimpleType $type, DOMElement $node) : void
    {
        if ($node->hasAttribute("itemType")) {
            /**
            * @var SimpleType $listType
            */
            $listType = $this->findSomeType($type, $node, 'itemType');
            $type->setList($listType);
        } else {
            $addCallback = function (SimpleType $list) use ($type) : void {
                $type->setList($list);
            };

            Type::loadTypeWithCallbackOnChildNodes(
                $this,
                $type->getSchema(),
                $node,
                $addCallback
            );
        }
    }

    private function findSomeType(
        SchemaItem $fromThis,
        DOMElement $node,
        string $attributeName
    ) : SchemaItem {
        return $this->findSomeTypeFromAttribute(
            $fromThis,
            $node,
            $node->getAttribute($attributeName)
        );
    }

    /**
    * @param string $attributeName
    *
    * @return SchemaItem
    */
    private function findSomeTypeFromAttribute(
        SchemaItem $fromThis,
        DOMElement $node,
        string $attributeName
    ) : SchemaItem {
        /**
        * @var SchemaItem $out
        */
        $out = $this->findSomething(
            'findType',
            $fromThis->getSchema(),
            $node,
            $attributeName
        );

        return $out;
    }

    private function loadUnion(SimpleType $type, DOMElement $node) : void
    {
        if ($node->hasAttribute("memberTypes")) {
            $types = preg_split('/\s+/', $node->getAttribute("memberTypes"));
            foreach ($types as $typeName) {
                /**
                * @var SimpleType $unionType
                */
                $unionType = $this->findSomeTypeFromAttribute(
                    $type,
                    $node,
                    $typeName
                );
                $type->addUnion($unionType);
            }
        }
        $addCallback = function (SimpleType $unType) use ($type) : void {
            $type->addUnion($unType);
        };

        Type::loadTypeWithCallbackOnChildNodes(
            $this,
            $type->getSchema(),
            $node,
            $addCallback
        );
    }

    private function fillTypeNode(
        Type $type,
        DOMElement $node,
        bool $checkAbstract = false
    ) : void {

        if ($checkAbstract) {
            $type->setAbstract($node->getAttribute("abstract") === "true" || $node->getAttribute("abstract") === "1");
        }

        static $methods = [
            'restriction' => 'loadRestriction',
            'extension' => 'maybeLoadExtensionFromBaseComplexType',
            'simpleContent' => 'fillTypeNode',
            'complexContent' => 'fillTypeNode',
        ];

        foreach ($node->childNodes as $childNode) {
            $this->maybeCallMethod(
                $methods,
                (string) $childNode->localName,
                $childNode,
                $type,
                $childNode
            );
        }
    }

    private function loadExtension(
        BaseComplexType $type,
        DOMElement $node
    ) : void {
        $extension = new Extension();
        $type->setExtension($extension);

        if ($node->hasAttribute("base")) {
            $this->findAndSetSomeBase(
                $type,
                $extension,
                $node
            );
        }

        $seqFromElement = function (DOMElement $childNode) use ($type) : void {
            $this->maybeLoadSequenceFromElementContainer(
                $type,
                $childNode
            );
        };

        $methods = [
            'sequence' => $seqFromElement,
            'choice' => $seqFromElement,
            'all' => $seqFromElement,
            'attribute' => function (
                DOMElement $childNode
            ) use (
                $node,
                $type
            ) : void {
                $attribute = Attribute::getAttributeFromAttributeOrRef(
                    $this,
                    $childNode,
                    $type->getSchema(),
                    $node
                );
                $type->addAttribute($attribute);
            },
            'attributeGroup' => function (
                DOMElement $childNode
            ) use (
                $node,
                $type
            ) : void {
                AttributeGroup::findSomethingLikeThis(
                    $this,
                    $type->getSchema(),
                    $node,
                    $childNode,
                    $type
                );
            },
        ];

        foreach ($node->childNodes as $childNode) {
            if (isset($methods[$childNode->localName])) {
                $method = $methods[$childNode->localName];
                $method($childNode);
            }
        }
    }

    public function findAndSetSomeBase(
        Type $type,
        Base $setBaseOnThis,
        DOMElement $node
    ) : void {
        /**
        * @var Type $parent
        */
        $parent = $this->findSomeType($type, $node, 'base');
        $setBaseOnThis->setBase($parent);
    }

    private function maybeLoadExtensionFromBaseComplexType(
        Type $type,
        DOMElement $childNode
    ) : void {
        if (! ($type instanceof BaseComplexType)) {
            throw new RuntimeException(
                'Argument 1 passed to ' .
                __METHOD__ .
                ' needs to be an instance of ' .
                BaseComplexType::class .
                ' when passed onto ' .
                static::class .
                '::loadExtension(), ' .
                get_class($type) .
                ' given.'
            );
        }
        $this->loadExtension($type, $childNode);
    }

    private function loadRestriction(Type $type, DOMElement $node) : void
    {
        Restriction::loadRestriction($this, $type, $node);
    }

    /**
    * @return mixed[]
    */
    private static function splitParts(
        DOMElement $node,
        string $typeName
    ) : array {
        $prefix = null;
        $name = $typeName;
        if (strpos($typeName, ':') !== false) {
            list ($prefix, $name) = explode(':', $typeName);
        }

        $namespace = $node->lookupNamespaceUri($prefix ?: '');
        return array(
            $name,
            $namespace,
            $prefix
        );
    }

    /**
     *
     * @param Schema $schema
     * @param DOMElement $node
     * @throws TypeException
     * @return ElementItem|Group|AttributeItem|AttributeGroup|Type
     */
    public function findSomething(
        string $finder,
        Schema $schema,
        DOMElement $node,
        string $typeName
    ) {
        list ($name, $namespace) = self::splitParts($node, $typeName);

        $namespace = $namespace ?: $schema->getTargetNamespace();

        try {
            return $schema->$finder($name, $namespace);
        } catch (TypeNotFoundException $e) {
            throw new TypeException(sprintf("Can't find %s named {%s}#%s, at line %d in %s ", strtolower(substr($finder, 4)), $namespace, $name, $node->getLineNo(), $node->ownerDocument->documentURI), 0, $e);
        }
    }

    private function loadElementDef(Schema $schema, DOMElement $node) : Closure
    {
        return $this->loadAttributeOrElementDef($schema, $node, false);
    }

    public function fillItem(Item $element, DOMElement $node) : void
    {
        foreach ($node->childNodes as $childNode) {
            if (
                in_array(
                    $childNode->localName,
                    [
                        'complexType',
                        'simpleType',
                    ]
                )
            ) {
                Type::loadTypeWithCallback(
                    $this,
                    $element->getSchema(),
                    $childNode,
                    function (Type $type) use ($element) : void {
                        $element->setType($type);
                    }
                );
                return;
            }
        }

        $this->fillItemNonLocalType($element, $node);
    }

    private function fillItemNonLocalType(
        Item $element,
        DOMElement $node
    ) : void {
        if ($node->getAttribute("type")) {
            /**
            * @var Type $type
            */
            $type = $this->findSomeType($element, $node, 'type');
        } else {
            /**
            * @var Type $type
            */
            $type = $this->findSomeTypeFromAttribute(
                $element,
                $node,
                ($node->lookupPrefix(self::XSD_NS) . ':anyType')
            );
        }

        $element->setType($type);
    }

    private function loadImport(Schema $schema, DOMElement $node) : Closure
    {
        $base = urldecode($node->ownerDocument->documentURI);
        $file = UrlUtils::resolveRelativeUrl($base, $node->getAttribute("schemaLocation"));

        $namespace = $node->getAttribute("namespace");

        if (
            (
                isset(self::$globalSchemaInfo[$namespace]) &&
                Schema::hasLoadedFile(
                    $loadedFilesKey = self::$globalSchemaInfo[$namespace]
                )
            ) ||
            Schema::hasLoadedFile(
                $loadedFilesKey = $this->getNamespaceSpecificFileIndex(
                    $file,
                    $namespace
                )
            ) ||
            Schema::hasLoadedFile($loadedFilesKey = $file)
        ) {
            $schema->addSchema(Schema::getLoadedFile($loadedFilesKey));

            return function() : void {
            };
        }

        return $this->loadImportFresh($schema, $node, $file, $namespace);
    }

    private function loadImportFresh(
        Schema $schema,
        DOMElement $node,
        string $file,
        string $namespace
    ) : Closure {
        if (! $namespace) {
            $newSchema = Schema::setLoadedFile($file, $schema);
        } else {
            $newSchema = Schema::setLoadedFile($file, new Schema());
            $newSchema->addSchema($this->getGlobalSchema());
        }

        $xml = $this->getDOM(isset($this->knownLocationSchemas[$file]) ? $this->knownLocationSchemas[$file] : $file);

        $callbacks = $this->schemaNode($newSchema, $xml->documentElement, $schema);

        if ($namespace) {
            $schema->addSchema($newSchema);
        }


        return function () use ($callbacks) : void {
            foreach ($callbacks as $callback) {
                $callback();
            }
        };
    }

    /**
    * @var Schema|null
    */
    private $globalSchema;

    public function getGlobalSchema() : Schema
    {
        if (!$this->globalSchema) {
            $callbacks = array();
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

            $globalSchemas[self::XSD_NS]->addType(new SimpleType($globalSchemas[self::XSD_NS], "anySimpleType"));
            $globalSchemas[self::XSD_NS]->addType(new SimpleType($globalSchemas[self::XSD_NS], "anyType"));

            $globalSchemas[self::XML_NS]->addSchema($globalSchemas[self::XSD_NS], self::XSD_NS);
            $globalSchemas[self::XSD_NS]->addSchema($globalSchemas[self::XML_NS], self::XML_NS);

            foreach ($callbacks as $callback) {
                $callback();
            }
        }

        /**
        * @var Schema $out
        */
        $out = $this->globalSchema;

        return $out;
    }

    /**
     * @param DOMElement $node
     * @param string  $file
     *
     * @return Schema
     */
    public function readNode(
        DOMElement $node,
        string $file = 'schema.xsd'
    ) : Schema {
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
    private function getNamespaceSpecificFileIndex(
        string $file,
        string $targetNamespace
    ) : string {
        return $file . '#' . $targetNamespace;
    }

    /**
     * @throws IOException
     */
    public function readString(
        string $content,
        string $file = 'schema.xsd'
    ) : Schema {
        $xml = new DOMDocument('1.0', 'UTF-8');
        if (!$xml->loadXML($content)) {
            throw new IOException("Can't load the schema");
        }
        $xml->documentURI = $file;

        return $this->readNode($xml->documentElement, $file);
    }

    public function readFile(string $file) : Schema
    {
        $xml = $this->getDOM($file);
        return $this->readNode($xml->documentElement, $file);
    }

    /**
     * @throws IOException
     */
    private function getDOM(string $file) : DOMDocument
    {
        $xml = new DOMDocument('1.0', 'UTF-8');
        if (!$xml->load($file)) {
            throw new IOException("Can't load the file $file");
        }
        return $xml;
    }
}
