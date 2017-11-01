<?php

declare(strict_types=1);

namespace GoetasWebservices\XML\XSDReader\Schema\Element;

use DOMElement;
use GoetasWebservices\XML\XSDReader\Schema\Type\Type;
use GoetasWebservices\XML\XSDReader\SchemaReader;

class ElementRef extends AbstractElementSingle
{
    /**
     * @var ElementDef
     */
    protected $wrapped;

    public function __construct(ElementDef $element)
    {
        parent::__construct($element->getSchema(), $element->getName());
        $this->wrapped = $element;
    }

    public function getReferencedElement(): ElementDef
    {
        return $this->wrapped;
    }

    public function getType(): ? Type
    {
        return $this->wrapped->getType();
    }
}
