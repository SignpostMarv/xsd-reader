<?php

namespace GoetasWebservices\XML\XSDReader\Schema;

abstract class AbstractNamedGroupItem
{
    use NamedItemTrait;
    /**
     * @var Schema
     */
    protected $schema;

    /**
     * @var string
     */
    protected $doc = '';

    public function __construct(Schema $schema, string $name)
    {
        $this->schema = $schema;
        $this->name = $name;
    }

    public function getDoc(): string
    {
        return $this->doc;
    }

    /**
     * @return $this
     */
    public function setDoc(string $doc): self
    {
        $this->doc = $doc;

        return $this;
    }

    public function getSchema(): Schema
    {
        return $this->schema;
    }
}
