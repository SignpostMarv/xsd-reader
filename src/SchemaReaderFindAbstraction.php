<?php

namespace GoetasWebservices\XML\XSDReader;

use DOMElement;
use GoetasWebservices\XML\XSDReader\Schema\SchemaItem;

abstract class SchemaReaderFindAbstraction extends SchemaReaderCallbackAbstraction
{
    protected function findSomeType(
        SchemaItem $fromThis,
        DOMElement $node,
        string $attributeName
    ): SchemaItem {
        return $this->findSomeTypeFromAttribute(
            $fromThis,
            $node,
            $node->getAttribute($attributeName)
        );
    }

    protected function findSomeTypeFromAttribute(
        SchemaItem $fromThis,
        DOMElement $node,
        string $attributeName
    ): SchemaItem {
        /**
         * @var SchemaItem
         */
        $out = $this->findSomething(
            'findType',
            $fromThis->getSchema(),
            $node,
            $attributeName
        );

        return $out;
    }
}
