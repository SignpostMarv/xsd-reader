<?php

declare(strict_types=1);

namespace GoetasWebservices\XML\XSDReader\Schema;

use DOMElement;
use RuntimeException;
use GoetasWebservices\XML\XSDReader\SchemaReader;
use GoetasWebservices\XML\XSDReader\Schema\Type\Type;
use GoetasWebservices\XML\XSDReader\Schema\Attribute\Group as AttributeGroup;
use GoetasWebservices\XML\XSDReader\Schema\Element\Group;
use GoetasWebservices\XML\XSDReader\Schema\Element\ElementDef;
use GoetasWebservices\XML\XSDReader\Schema\Element\ElementItem;
use GoetasWebservices\XML\XSDReader\Schema\Exception\TypeNotFoundException;
use GoetasWebservices\XML\XSDReader\Schema\Exception\SchemaException;
use GoetasWebservices\XML\XSDReader\Schema\Attribute\AttributeItem;
use GoetasWebservices\XML\XSDReader\Schema\Attribute\AttributeDef;

class Schema
{
    /**
     * @param bool[] $calling
     */
    protected function findSomethingNoThrow(
        string $getter,
        string $name,
        string $namespace = null,
        array &$calling = array()
    ): ? SchemaItem {
        $calling[spl_object_hash($this)] = true;
        $cid = "$getter, $name, $namespace";

        if (isset($this->typeCache[$cid])) {
            return $this->typeCache[$cid];
        } elseif (
            $this->getTargetNamespace() === $namespace
        ) {
            /**
             * @var SchemaItem|null
             */
            $item = $this->$getter($name);

            if ($item instanceof SchemaItem) {
                return $this->typeCache[$cid] = $item;
            }
        }

        return $this->findSomethingNoThrowSchemas(
            $this->getSchemas(),
            $cid,
            $getter,
            $name,
            $namespace,
            $calling
        );
    }

    /**
     * @param Schema[] $schemas
     * @param bool[]   $calling
     */
    protected function findSomethingNoThrowSchemas(
        array $schemas,
        string $cid,
        string $getter,
        string $name,
        string $namespace = null,
        array &$calling = array()
    ): ? SchemaItem {
        foreach ($schemas as $childSchema) {
            if (!isset($calling[spl_object_hash($childSchema)])) {
                /**
                 * @var SchemaItem|null
                 */
                $in = $childSchema->findSomethingNoThrow($getter, $name, $namespace, $calling);

                if ($in instanceof SchemaItem) {
                    return $this->typeCache[$cid] = $in;
                }
            }
        }

        return null;
    }

    /**
     * @param bool[] $calling
     *
     * @throws TypeNotFoundException
     */
    protected function findSomething(
        string $getter,
        string $name,
        string $namespace = null,
        array &$calling = array()
    ): SchemaItem {
        $in = $this->findSomethingNoThrow(
            $getter,
            $name,
            $namespace,
            $calling
        );

        if ($in instanceof SchemaItem) {
            return $in;
        }

        throw new TypeNotFoundException(
            sprintf("Can't find the %s named {%s}#%s.", substr($getter, 3), (string) $namespace, $name)
        );
    }

    /**
     * @var bool
     */
    protected $elementsQualification = false;

    /**
     * @var bool
     */
    protected $attributesQualification = false;

    /**
     * @var null|string
     */
    protected $targetNamespace;

    /**
     * @var Schema[]
     */
    protected $schemas = array();

    /**
     * @var Type[]
     */
    protected $types = array();

    /**
     * @var ElementDef[]
     */
    protected $elements = array();

    /**
     * @var Group[]
     */
    protected $groups = array();

    /**
     * @var AttributeGroup[]
     */
    protected $attributeGroups = array();

    /**
     * @var AttributeDef[]
     */
    protected $attributes = array();

    /**
     * @var string|null
     */
    protected $doc;

    /**
     * @var \GoetasWebservices\XML\XSDReader\Schema\SchemaItem[]
     */
    protected $typeCache = array();

    public function getElementsQualification(): bool
    {
        return $this->elementsQualification;
    }

    public function setElementsQualification(
        bool $elementsQualification
    ): void {
        $this->elementsQualification = $elementsQualification;
    }

    public function getAttributesQualification(): bool
    {
        return $this->attributesQualification;
    }

    public function setAttributesQualification(
        bool $attributesQualification
    ): void {
        $this->attributesQualification = $attributesQualification;
    }

    public function getTargetNamespace(): ? string
    {
        return $this->targetNamespace;
    }

    public function setTargetNamespace(? string $targetNamespace): void
    {
        $this->targetNamespace = $targetNamespace;
    }

    /**
     * @return Type[]
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * @return ElementDef[]
     */
    public function getElements(): array
    {
        return $this->elements;
    }

    /**
     * @return Schema[]
     */
    public function getSchemas(): array
    {
        return $this->schemas;
    }

    /**
     * @return AttributeDef[]
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return Group[]
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    public function getDoc(): ? string
    {
        return $this->doc;
    }

    public function setDoc(string $doc): void
    {
        $this->doc = $doc;
    }

    public function addType(Type $type): void
    {
        $this->types[$type->getName()] = $type;
    }

    public function addElement(ElementDef $element): void
    {
        $this->elements[$element->getName()] = $element;
    }

    public function addSchema(Schema $schema, string $namespace = null): void
    {
        if ($namespace !== null) {
            if ($schema->getTargetNamespace() !== $namespace) {
                throw new SchemaException(
                    sprintf(
                        "The target namespace ('%s') for schema, does not match the declared namespace '%s'",
                        (string) $schema->getTargetNamespace(),
                        $namespace
                    )
                );
            }
            $this->schemas[$namespace] = $schema;
        } else {
            $this->schemas[] = $schema;
        }
    }

    public function addAttribute(AttributeDef $attribute): void
    {
        $this->attributes[$attribute->getName()] = $attribute;
    }

    public function addGroup(Group $group): void
    {
        $this->groups[$group->getName()] = $group;
    }

    public function addAttributeGroup(AttributeGroup $group): void
    {
        $this->attributeGroups[$group->getName()] = $group;
    }

    /**
     * @return AttributeGroup[]
     */
    public function getAttributeGroups(): array
    {
        return $this->attributeGroups;
    }

    public function getGroup(string $name): ? Group
    {
        if (isset($this->groups[$name])) {
            return $this->groups[$name];
        }

        return null;
    }

    /**
     * @param string $name
     *
     * @return ElementItem|false
     */
    public function getElement($name)
    {
        if (isset($this->elements[$name])) {
            return $this->elements[$name];
        }

        return false;
    }

    /**
     * @param string $name
     *
     * @return Type|false
     */
    public function getType($name)
    {
        if (isset($this->types[$name])) {
            return $this->types[$name];
        }

        return false;
    }

    /**
     * @param string $name
     *
     * @return AttributeItem|false
     */
    public function getAttribute($name)
    {
        if (isset($this->attributes[$name])) {
            return $this->attributes[$name];
        }

        return false;
    }

    /**
     * @param string $name
     *
     * @return AttributeGroup|false
     */
    public function getAttributeGroup($name)
    {
        if (isset($this->attributeGroups[$name])) {
            return $this->attributeGroups[$name];
        }

        return false;
    }

    public function __toString()
    {
        return sprintf('Target namespace %s', (string) $this->getTargetNamespace());
    }

    public function findType(string $name, string $namespace = null): Type
    {
        /**
         * @var Type
         */
        $out = $this->findSomething('getType', $name, $namespace);

        return $out;
    }

    public function findGroup(string $name, string $namespace = null): Group
    {
        /**
         * @var Group
         */
        $out = $this->findSomething('getGroup', $name, $namespace);

        return $out;
    }

    public function findElement(string $name, string $namespace = null): ElementDef
    {
        /**
         * @var ElementDef
         */
        $out = $this->findSomething('getElement', $name, $namespace);

        return $out;
    }

    public function findAttribute(string $name, string $namespace = null): AttributeItem
    {
        /**
         * @var AttributeItem
         */
        $out = $this->findSomething('getAttribute', $name, $namespace);

        return $out;
    }

    public function findAttributeGroup(string $name, string $namespace = null): AttributeGroup
    {
        /**
         * @var AttributeGroup
         */
        $out = $this->findSomething('getAttributeGroup', $name, $namespace);

        return $out;
    }

    /**
     * @var Schema[]
     */
    protected static $loadedFiles = array();

    public static function hasLoadedFile(string ...$keys): bool
    {
        foreach ($keys as $key) {
            if (isset(self::$loadedFiles[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws RuntimeException if loaded file not found
     */
    public static function getLoadedFile(string ...$keys): Schema
    {
        foreach ($keys as $key) {
            if (isset(self::$loadedFiles[$key])) {
                return self::$loadedFiles[$key];
            }
        }

        throw new RuntimeException('Loaded file was not found!');
    }

    public static function setLoadedFile(string $key, Schema $schema): Schema
    {
        self::$loadedFiles[$key] = $schema;

        return $schema;
    }

    public function setSchemaThingsFromNode(
        DOMElement $node,
        Schema $parent = null
    ): void {
        $this->setDoc(SchemaReader::getDocumentation($node));

        if ($node->hasAttribute('targetNamespace')) {
            $this->setTargetNamespace($node->getAttribute('targetNamespace'));
        } elseif ($parent) {
            $this->setTargetNamespace($parent->getTargetNamespace());
        }
        $this->setElementsQualification($node->getAttribute('elementFormDefault') == 'qualified');
        $this->setAttributesQualification($node->getAttribute('attributeFormDefault') == 'qualified');
        $this->setDoc(SchemaReader::getDocumentation($node));
    }
}
