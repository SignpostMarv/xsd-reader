<?php

declare(strict_types=1);

namespace GoetasWebservices\XML\XSDReader\Schema\Type;

use GoetasWebservices\XML\XSDReader\Schema\Inheritance\Restriction;

class SimpleType extends Type
{
    /**
     * @var Restriction|null
     */
    protected $restriction;

    /**
     * @var SimpleType[]
     */
    protected $unions = array();

    /**
     * @var SimpleType|null
     */
    protected $list;

    public function getRestriction(): ? Restriction
    {
        return $this->restriction;
    }

    /**
     * {@inheritdoc}
     */
    public function setRestriction(Restriction $restriction): Type
    {
        $this->restriction = $restriction;

        return $this;
    }

    /**
     * @return $this
     */
    public function addUnion(SimpleType $type): self
    {
        $this->unions[] = $type;

        return $this;
    }

    /**
     * @return SimpleType[]
     */
    public function getUnions(): array
    {
        return $this->unions;
    }

    public function getList(): ? SimpleType
    {
        return $this->list;
    }

    /**
     * @return $this
     */
    public function setList(SimpleType $list)
    {
        $this->list = $list;

        return $this;
    }
}
