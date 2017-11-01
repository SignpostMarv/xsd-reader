<?php

declare(strict_types=1);

namespace GoetasWebservices\XML\XSDReader\Schema\Element;

use Closure;
use DOMElement;
use GoetasWebservices\XML\XSDReader\Schema\Attribute\AttributeItemTrait;
use GoetasWebservices\XML\XSDReader\Schema\Schema;
use GoetasWebservices\XML\XSDReader\SchemaReader;

class Group implements ElementItem, ElementContainer
{
    use AttributeItemTrait;
    use ElementContainerTrait;

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
