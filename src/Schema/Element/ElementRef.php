<?php
declare(strict_types = 1);
namespace GoetasWebservices\XML\XSDReader\Schema\Element;

use DOMElement;
use GoetasWebservices\XML\XSDReader\Schema\Item;
use GoetasWebservices\XML\XSDReader\Schema\Schema;
use GoetasWebservices\XML\XSDReader\Schema\Type\Type;
use GoetasWebservices\XML\XSDReader\SchemaReader;

class ElementRef extends Item implements ElementSingle
{
    /**
    * @var ElementDef
    */
    protected $wrapped;

    /**
    * @var int
    */
    protected $min = 1;

    /**
    * @var int
    */
    protected $max = 1;

    /**
    * @var bool
    */
    protected $qualified = true;

    /**
    * @var bool
    */
    protected $nil = false;

    public function __construct(ElementDef $element)
    {
        parent::__construct($element->getSchema(), $element->getName());
        $this->wrapped = $element;
    }

    public function getReferencedElement() : ElementDef
    {
        return $this->wrapped;
    }

    public function getType() : ? Type
    {
        return $this->wrapped->getType();
    }

    public function getMin() : int
    {
        return $this->min;
    }

    /**
    * {@inheritdoc}
    */
    public function setMin(int $min) : self
    {
        $this->min = $min;
        return $this;
    }

    public function getMax() : int
    {
        return $this->max;
    }

    /**
    * {@inheritdoc}
    */
    public function setMax(int $max) : self
    {
        $this->max = $max;
        return $this;
    }

    public function isQualified() : bool
    {
        return $this->qualified;
    }

    /**
    * {@inheritdoc}
    */
    public function setQualified(bool $qualified) : ElementSingle
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
    public function setNil(bool $nil) : ElementSingle
    {
        $this->nil = $nil;
        return $this;
    }

    public static function loadElementRef(
        ElementDef $referenced,
        DOMElement $node
    ) : ElementRef {
        $ref = new ElementRef($referenced);
        $ref->setDoc(SchemaReader::getDocumentation($node));

        SchemaReader::maybeSetMax($ref, $node);
        SchemaReader::maybeSetMin($ref, $node);
        if ($node->hasAttribute("nillable")) {
            $ref->setNil($node->getAttribute("nillable") == "true");
        }
        if ($node->hasAttribute("form")) {
            $ref->setQualified($node->getAttribute("form") == "qualified");
        }

        return $ref;
    }
}
