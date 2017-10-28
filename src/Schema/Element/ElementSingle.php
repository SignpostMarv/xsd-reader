<?php

namespace GoetasWebservices\XML\XSDReader\Schema\Element;

interface ElementSingle extends ElementItem, InterfaceSetMinMax
{
    /**
     * @return \GoetasWebservices\XML\XSDReader\Schema\Type\Type
     */
    public function getType();

    /**
     * @return bool
     */
    public function isQualified();

    /**
     * @param bool $qualified
     */
    public function setQualified($qualified);

    /**
     * @return bool
     */
    public function isNil();

    /**
     * @param bool $nil
     */
    public function setNil($nil);
}
