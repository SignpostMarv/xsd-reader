<?php
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

    /**
    * @return int
    */
    public function getMin()
    {
        return $this->min;
    }

    /**
    * @param int $min
    *
    * @return $this
    */
    public function setMin($min)
    {
        $this->min = $min;
        return $this;
    }

    /**
    * @return int
    */
    public function getMax()
    {
        return $this->max;
    }

    /**
    * @param int $max
    *
    * @return $this
    */
    public function setMax($max)
    {
        $this->max = $max;
        return $this;
    }

    /**
    * @return string
    */
    public function getName()
    {
        return $this->wrapped->getName();
    }

    /**
    * @return ElementItem[]
    */
    public function getElements()
    {
        $elements = $this->wrapped->getElements();
        if($this->getMax()>0 || $this->getMax()===-1){
            foreach ($elements as $k => $element) {
                /**
                * @var Element|ElementRef|ElementSingle|GroupRef $e
                */
                $e = clone $element;
                $e->setMax($this->getMax());
                $elements[$k] = $e;
            }
        }
        return $elements;
    }

    public function addElement(ElementItem $element)
    {
        throw new BadMethodCallException("Can't add an element for a ref group");
    }

    /**
    * @return GroupRef
    */
    public static function loadGroupRef(Group $referenced, DOMElement $node)
    {
        $ref = new GroupRef($referenced);
        $ref->setDoc(SchemaReader::getDocumentation($node));

        SchemaReader::maybeSetMax($ref, $node);
        SchemaReader::maybeSetMin($ref, $node);

        return $ref;
    }
}
