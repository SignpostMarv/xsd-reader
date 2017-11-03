<?php

declare(strict_types=1);

namespace GoetasWebservices\XML\XSDReader\Schema\Attribute;

use GoetasWebservices\XML\XSDReader\Schema\Item;

class Attribute extends Item implements AttributeSingle
{
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

    public function isQualified(): bool
    {
        return $this->qualified;
    }

    /**
     * {@inheritdoc}
     */
    public function setQualified(bool $qualified): AttributeSingle
    {
        $this->qualified = $qualified;

        return $this;
    }

    public function isNil(): bool
    {
        return $this->nil;
    }

    /**
     * {@inheritdoc}
     */
    public function setNil(bool $nil): AttributeSingle
    {
        $this->nil = $nil;

        return $this;
    }

    public function getUse(): string
    {
        return $this->use;
    }

    /**
     * {@inheritdoc}
     */
    public function setUse(string $use): AttributeSingle
    {
        $this->use = $use;

        return $this;
    }
}
