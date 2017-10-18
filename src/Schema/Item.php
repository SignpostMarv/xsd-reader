<?php
declare(strict_types = 1);
namespace GoetasWebservices\XML\XSDReader\Schema;

use GoetasWebservices\XML\XSDReader\Schema\Attribute\AttributeItemTrait;
use GoetasWebservices\XML\XSDReader\Schema\Type\Type;
use GoetasWebservices\XML\XSDReader\Schema\Schema;
use GoetasWebservices\XML\XSDReader\Schema\SchemaItem;
use GoetasWebservices\XML\XSDReader\Schema\SchemaItemTrait;

abstract class Item implements SchemaItem
{
    use AttributeItemTrait;
    use SchemaItemTrait;

    /**
    * @var Type|null
    */
    protected $type;

    public function __construct(Schema $schema, string $name)
    {
        $this->schema = $schema;
        $this->name = $name;
    }

    public function getType() : ? Type
    {
        return $this->type;
    }

    /**
    * @return $this
    */
    public function setType(Type $type) : self
    {
        $this->type = $type;
        return $this;
    }
}
