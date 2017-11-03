<?php

namespace GoetasWebservices\XML\XSDReader\Schema\Attribute;

use GoetasWebservices\XML\XSDReader\Schema\Item;

abstract class AbstractAttributeItem extends Item implements AttributeItem
{
    /**
     * @var string|null
     */
    protected $fixed;

    /**
     * @var string|null
     */
    protected $default;

    /**
     * @return string|null
     */
    public function getFixed(): ? string
    {
        return $this->fixed;
    }

    /**
     * @return $this
     */
    public function setFixed(string $fixed): self
    {
        $this->fixed = $fixed;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getDefault(): ? string
    {
        return $this->default;
    }

    /**
     * @return $this
     */
    public function setDefault(string $default): self
    {
        $this->default = $default;

        return $this;
    }
}
