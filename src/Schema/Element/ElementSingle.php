<?php
declare(strict_types = 1);
namespace GoetasWebservices\XML\XSDReader\Schema\Element;

use GoetasWebservices\XML\XSDReader\Schema\Type\Type;

interface ElementSingle extends ElementItem, InterfaceSetMinMax
{
    public function getType() : ? Type;

    public function isQualified() : bool;

    /**
    * @return $this
    */
    public function setQualified(bool $qualified) : self;

    public function isNil() : bool;

    /**
    * @return $this
    */
    public function setNil(bool $nil) : self;
}
