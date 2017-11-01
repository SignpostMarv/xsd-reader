<?php

declare(strict_types=1);

namespace GoetasWebservices\XML\XSDReader\Schema\Element;

use BadMethodCallException;
use DOMElement;
use GoetasWebservices\XML\XSDReader\SchemaReader;

class GroupRef extends Group implements InterfaceSetMinMax
{
    /**
     * @var Group
     */
    protected $wrapped;

    /**
     * @var int
     */
    protected $min = 1;

    /**
     * @var int
     */
    protected $max = 1;

    public function __construct(Group $group)
    {
        parent::__construct($group->getSchema(), '');
        $this->wrapped = $group;
    }

    public function getMin(): int
    {
        return $this->min;
    }

    /**
     * @return $this
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
     * @return $this
     */
    public function setMax(int $max): self
    {
        $this->max = $max;

        return $this;
    }

    public function getName(): string
    {
        return $this->wrapped->getName();
    }

    /**
     * @return ElementItem[]
     */
    public function getElements(): array
    {
        $elements = $this->wrapped->getElements();
        if ($this->getMax() > 0 || $this->getMax() === -1) {
            foreach ($elements as $k => $element) {
                if (! ($element instanceof InterfaceSetMinMax)) {
                    continue;
                }
                $e = clone $element;
                $e->setMax($this->getMax());

                /**
                 * @var Element|ElementRef|ElementSingle|GroupRef $e
                 */
                $elements[$k] = $e;
            }
        }

        return $elements;
    }

    public function addElement(ElementItem $element): void
    {
        throw new BadMethodCallException("Can't add an element for a ref group");
    }

    public static function loadGroupRef(
        Group $referenced,
        DOMElement $node
    ): self {
        $ref = new self($referenced);
        $ref->setDoc(SchemaReader::getDocumentation($node));

        SchemaReader::maybeSetMax($ref, $node);
        SchemaReader::maybeSetMin($ref, $node);

        return $ref;
    }
}
