<?php
declare(strict_types = 1);
namespace GoetasWebservices\XML\XSDReader\Schema\Attribute;

use DOMElement;
use GoetasWebservices\XML\XSDReader\Schema\Item;
use GoetasWebservices\XML\XSDReader\Schema\Schema;
use GoetasWebservices\XML\XSDReader\SchemaReader;

class Attribute extends Item implements AttributeSingle
{
    /**
    * @var static|null
    */
    protected $fixed;

    /**
    * @var static|null
    */
    protected $default;

    /**
    * @var bool
    */
    protected $qualified = true;

    /**
    * @var bool
    */
    protected $nil = false;

    /**
    * @var string
    */
    protected $use = self::USE_OPTIONAL;

    /**
    * @return static|null
    */
    public function getFixed() : ? self
    {
        return $this->fixed;
    }

    /**
    * @param static $fixed
    *
    * @return $this
    */
    public function setFixed(Attribute $fixed) : self
    {
        $this->fixed = $fixed;
        return $this;
    }

    /**
    * @return static|null
    */
    public function getDefault() : ? self
    {
        return $this->default;
    }

    /**
    * @param static $default
    *
    * @return $this
    */
    public function setDefault(Attribute $default) : self
    {
        $this->default = $default;
        return $this;
    }

    public function isQualified() : bool
    {
        return $this->qualified;
    }

    /**
    * {@inheritdoc}
    */
    public function setQualified(bool $qualified) : AttributeSingle
    {
        $this->qualified = $qualified;
        return $this;
    }

    public function isNil() : bool
    {
        return $this->nil;
    }

    /**
    * {@inheritdoc}
    */
    public function setNil(bool $nil) : AttributeSingle
    {
        $this->nil = $nil;
        return $this;
    }

    public function getUse() : string
    {
        return $this->use;
    }

    /**
    * {@inheritdoc}
    */
    public function setUse(string $use) : AttributeSingle
    {
        $this->use = $use;
        return $this;
    }

    public static function loadAttribute(
        SchemaReader $schemaReader,
        Schema $schema,
        DOMElement $node
    ) : Attribute {
        $attribute = new Attribute($schema, $node->getAttribute("name"));
        $attribute->setDoc(SchemaReader::getDocumentation($node));
        $schemaReader->fillItem($attribute, $node);

        if ($node->hasAttribute("nillable")) {
            $attribute->setNil($node->getAttribute("nillable") == "true");
        }
        if ($node->hasAttribute("form")) {
            $attribute->setQualified($node->getAttribute("form") == "qualified");
        }
        if ($node->hasAttribute("use")) {
            $attribute->setUse($node->getAttribute("use"));
        }
        return $attribute;
    }

    public static function getAttributeFromAttributeOrRef(
        SchemaReader $schemaReader,
        DOMElement $childNode,
        Schema $schema,
        DOMElement $node
    ) : AttributeItem {
        if ($childNode->hasAttribute("ref")) {
            /**
            * @var AttributeItem $attribute
            */
            $attribute = $schemaReader->findSomething('findAttribute', $schema, $node, $childNode->getAttribute("ref"));
        } else {
            /**
            * @var Attribute $attribute
            */
            $attribute = Attribute::loadAttribute($schemaReader, $schema, $childNode);
        }

        return $attribute;
    }
}
