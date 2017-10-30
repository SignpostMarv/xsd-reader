<?php

namespace GoetasWebservices\XML\XSDReader;

use Closure;
use DOMDocument;
use DOMElement;
use DOMNode;
use GoetasWebservices\XML\XSDReader\Exception\IOException;
use GoetasWebservices\XML\XSDReader\Exception\TypeException;
use GoetasWebservices\XML\XSDReader\Schema\Attribute\Attribute;
use GoetasWebservices\XML\XSDReader\Schema\Attribute\AttributeItem;
use GoetasWebservices\XML\XSDReader\Schema\Attribute\Group as AttributeGroup;
use GoetasWebservices\XML\XSDReader\Schema\Element\Element;
use GoetasWebservices\XML\XSDReader\Schema\Element\ElementContainer;
use GoetasWebservices\XML\XSDReader\Schema\Element\ElementItem;
use GoetasWebservices\XML\XSDReader\Schema\Element\Group;
use GoetasWebservices\XML\XSDReader\Schema\Element\InterfaceSetMinMax;
use GoetasWebservices\XML\XSDReader\Schema\Exception\TypeNotFoundException;
use GoetasWebservices\XML\XSDReader\Schema\Inheritance\Base;
use GoetasWebservices\XML\XSDReader\Schema\Item;
use GoetasWebservices\XML\XSDReader\Schema\Schema;
use GoetasWebservices\XML\XSDReader\Schema\SchemaItem;
use GoetasWebservices\XML\XSDReader\Schema\Type\BaseComplexType;
use GoetasWebservices\XML\XSDReader\Schema\Type\ComplexType;
use GoetasWebservices\XML\XSDReader\Schema\Type\SimpleType;
use GoetasWebservices\XML\XSDReader\Schema\Type\Type;

abstract class AbstractSchemaReader
{
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


    abstract protected function loadAttributeOrElementDef(
        Schema $schema,
        DOMElement $node,
        bool $attributeDef
    ): Closure;

    abstract protected function loadAttributeDef(
        Schema $schema,
        DOMElement $node
    ): Closure;

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
            'include' => (Schema::class.'::loadImport'),
            'import' => (Schema::class.'::loadImport'),
            'attributeGroup' => (
                AttributeGroup::class.
                '::loadAttributeGroup'
            ),
        ];

        $thisMethods = [
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

    abstract protected static function loadSequenceNormaliseMax(
        DOMElement $node,
        ? int $max
    ): ? int;

    abstract protected function loadSequence(
        ElementContainer $elementContainer,
        DOMElement $node,
        int $max = null
    ): void;

    abstract protected function loadSequenceChildNode(
        ElementContainer $elementContainer,
        DOMElement $node,
        DOMElement $childNode,
        ? int $max
    ): void;

    /**
     * @param mixed[][] $methods
     *
     * @return mixed
     */
    abstract protected function maybeCallCallableWithArgs(
        DOMElement $childNode,
        array $commonMethods = [],
        array $methods = [],
        array $commonArguments = []
    );

    abstract protected function loadSequenceChildNodeLoadSequence(
        ElementContainer $elementContainer,
        DOMElement $childNode,
        ? int $max
    ): void;

    abstract protected function loadSequenceChildNodeLoadElement(
        ElementContainer $elementContainer,
        DOMElement $node,
        DOMElement $childNode,
        ? int $max
    ): void;

    abstract protected function loadSequenceChildNodeLoadGroup(
        ElementContainer $elementContainer,
        DOMElement $node,
        DOMElement $childNode
    ): void;

    abstract protected function addGroupAsElement(
        Schema $schema,
        DOMElement $node,
        DOMElement $childNode,
        ElementContainer $elementContainer
    ): void;

    abstract protected function maybeLoadSequenceFromElementContainer(
        BaseComplexType $type,
        DOMElement $childNode
    ): void;

    abstract protected function loadGroup(
        Schema $schema,
        DOMElement $node
    ): Closure;

    abstract protected function loadComplexTypeBeforeCallbackCallback(
        Schema $schema,
        DOMElement $node
    ): BaseComplexType;

    abstract protected function loadComplexType(
        Schema $schema,
        DOMElement $node,
        Closure $callback = null
    ): Closure;

    abstract protected function makeCallbackCallback(
        Type $type,
        DOMElement $node,
        Closure $callbackCallback,
        Closure $callback = null
    ): Closure;

    abstract protected function runCallbackAgainstDOMNodeList(
        Type $type,
        DOMElement $node,
        Closure $againstNodeList,
        Closure $callback = null
    ): void;

    abstract protected function loadComplexTypeFromChildNode(
        BaseComplexType $type,
        DOMElement $node,
        DOMElement $childNode,
        Schema $schema
    ): void;

    abstract protected function loadSimpleType(
        Schema $schema,
        DOMElement $node,
        Closure $callback = null
    ): Closure;

    abstract protected function loadList(
        SimpleType $type,
        DOMElement $node
    ): void;

    abstract protected function findSomeType(
        SchemaItem $fromThis,
        DOMElement $node,
        string $attributeName
    ): SchemaItem;

    abstract protected function findSomeTypeFromAttribute(
        SchemaItem $fromThis,
        DOMElement $node,
        string $attributeName
    ): SchemaItem;

    abstract protected function loadUnion(
        SimpleType $type,
        DOMElement $node
    ): void;

    abstract protected function fillTypeNode(
        Type $type,
        DOMElement $node,
        bool $checkAbstract = false
    ): void;

    abstract protected function loadExtension(
        BaseComplexType $type,
        DOMElement $node
    ): void;

    public function findAndSetSomeBase(
        Type $type,
        Base $setBaseOnThis,
        DOMElement $node
    ): void {
        /**
         * @var Type
         */
        $parent = $this->findSomeType($type, $node, 'base');
        $setBaseOnThis->setBase($parent);
    }

    abstract protected function maybeLoadExtensionFromBaseComplexType(
        Type $type,
        DOMElement $childNode
    ): void;

    abstract protected function loadRestriction(
        Type $type,
        DOMElement $node
    ): void;

    /**
     * @return mixed[]
     */
    abstract protected static function splitParts(
        DOMElement $node,
        string $typeName
    ): array;

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

    abstract protected function loadElementDef(
        Schema $schema,
        DOMElement $node
    ): Closure;

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
                    Type::loadTypeWithCallback(
                        $this,
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

    abstract protected function fillItemNonLocalType(
        Item $element,
        DOMElement $node
    ): void;

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
}
