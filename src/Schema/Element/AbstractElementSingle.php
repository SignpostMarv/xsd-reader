<?php

namespace GoetasWebservices\XML\XSDReader\Schema\Element;

use GoetasWebservices\XML\XSDReader\Schema\Item;

class AbstractElementSingle extends Item implements ElementSingle
{
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

    public function isQualified(): bool
    {
        return $this->qualified;
    }

    /**
     * {@inheritdoc}
     */
    public function setQualified(bool $qualified): ElementSingle
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
    public function setNil(bool $nil): ElementSingle
    {
        $this->nil = $nil;

        return $this;
    }

    public function getMin(): int
    {
        return $this->min;
    }

    /**
     * {@inheritdoc}
     */
    public function setMin(int $min): ElementSingle
    {
        $this->min = $min;

        return $this;
    }

    public function getMax(): int
    {
        return $this->max;
    }

    /**
     * {@inheritdoc}
     */
    public function setMax(int $max): ElementSingle
    {
        $this->max = $max;

        return $this;
    }
}
