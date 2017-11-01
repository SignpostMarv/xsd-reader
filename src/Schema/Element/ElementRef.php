<?php

declare(strict_types=1);

namespace GoetasWebservices\XML\XSDReader\Schema\Element;

use DOMElement;
use GoetasWebservices\XML\XSDReader\Schema\Item;
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

    public static function loadElementRef(
        ElementDef $referenced,
        DOMElement $node
    ): ElementRef {
        $ref = new self($referenced);
        $ref->setDoc(SchemaReader::getDocumentation($node));

        SchemaReader::maybeSetMax($ref, $node);
        SchemaReader::maybeSetMin($ref, $node);
        if ($node->hasAttribute('nillable')) {
            $ref->setNil($node->getAttribute('nillable') == 'true');
        }
        if ($node->hasAttribute('form')) {
            $ref->setQualified($node->getAttribute('form') == 'qualified');
        }

        return $ref;
    }
}
