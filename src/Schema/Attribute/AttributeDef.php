<?php

declare(strict_types=1);

namespace GoetasWebservices\XML\XSDReader\Schema\Attribute;

use GoetasWebservices\XML\XSDReader\Schema\Item;

class AttributeDef extends Item implements AttributeItem
{
    /**
     * @var self|null
     */
    protected $fixed;

    /**
     * @var self|null
     */
    protected $default;

    /**
     * @return self|null
     */
    public function getFixed(): ? self
    {
        return $this->fixed;
    }

    /**
     * @return $this
     */
    public function setFixed(AttributeDef $fixed): self
    {
        $this->fixed = $fixed;

        return $this;
    }

    /**
     * @return self|null
     */
    public function getDefault(): ? self
    {
        return $this->default;
    }

    /**
     * @return $this
     */
    public function setDefault(AttributeDef $default): self
    {
        $this->default = $default;

        return $this;
    }
}
