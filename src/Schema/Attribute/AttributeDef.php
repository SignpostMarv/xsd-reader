<?php
declare(strict_types = 1);
namespace GoetasWebservices\XML\XSDReader\Schema\Attribute;

use GoetasWebservices\XML\XSDReader\Schema\Item;

class AttributeDef extends Item implements AttributeItem
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
    public function setFixed(AttributeDef $fixed) : self
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
    public function setDefault(AttributeDef $default) : self
    {
        $this->default = $default;
        return $this;
    }
}
