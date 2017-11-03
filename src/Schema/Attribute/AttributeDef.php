<?php

declare(strict_types=1);

namespace GoetasWebservices\XML\XSDReader\Schema\Attribute;

use GoetasWebservices\XML\XSDReader\Schema\Item;

class AttributeDef extends Item implements AttributeItem
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
    public function setFixed(self $fixed): self
    {
        $this->fixed = $fixed;

        return $this;
    }

    /**
     * @return static|null
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
