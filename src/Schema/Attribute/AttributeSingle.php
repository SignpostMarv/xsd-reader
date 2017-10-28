<?php

declare(strict_types=1);

namespace GoetasWebservices\XML\XSDReader\Schema\Attribute;

use GoetasWebservices\XML\XSDReader\Schema\Type\Type;

interface AttributeSingle extends AttributeItem
{
    const USE_OPTIONAL = 'optional';

    const USE_PROHIBITED = 'prohibited';

    const USE_REQUIRED = 'required';

    public function getType(): ? Type;

    public function isQualified(): bool;

    /**
     * @return $this
     */
    public function setQualified(bool $qualified): self;

    public function isNil(): bool;

    /**
     * @return $this
     */
    public function setNil(bool $nil): self;

    public function getUse(): string;

    /**
     * @return $this
     */
    public function setUse(string $use): self;
}
