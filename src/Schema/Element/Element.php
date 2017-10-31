<?php

declare(strict_types=1);

namespace GoetasWebservices\XML\XSDReader\Schema\Element;

use DOMElement;
use GoetasWebservices\XML\XSDReader\Schema\Item;
use GoetasWebservices\XML\XSDReader\Schema\Schema;
use GoetasWebservices\XML\XSDReader\SchemaReader;

class Element extends Item implements ElementItem, ElementSingle
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
    protected $qualified = false;

    /**
     * @var bool
     */
    protected $nil = false;

    public function getMin(): int
    {
        return $this->min;
    }

    /**
     * {@inheritdoc}
     */
    public function setMin(int $min): self
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
    public function setMax(int $max): self
    {
        $this->max = $max;

        return $this;
    }

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

    public static function loadElement(
        SchemaReader $reader,
        Schema $schema,
        DOMElement $node
    ): Element {
        $element = new self($schema, $node->getAttribute('name'));
        $element->setDoc(SchemaReader::getDocumentation($node));

        $reader->fillItem($element, $node);

        SchemaReader::maybeSetMax($element, $node);
        SchemaReader::maybeSetMin($element, $node);

        $xp = new \DOMXPath($node->ownerDocument);
        $xp->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');

        if ($xp->query('ancestor::xs:choice', $node)->length) {
            $element->setMin(0);
        }

        if ($node->hasAttribute('nillable')) {
            $element->setNil($node->getAttribute('nillable') == 'true');
        }
        if ($node->hasAttribute('form')) {
            $element->setQualified($node->getAttribute('form') == 'qualified');
        }

        return $element;
    }
}
