<?php

declare(strict_types=1);

namespace GoetasWebservices\XML\XSDReader;

use DOMElement;

class SchemaReader extends SchemaReaderLoadAbstraction
{
    /**
     * @return mixed[]
     */
    protected static function splitParts(
        DOMElement $node,
        string $typeName
    ): array {
        $prefix = null;
        $name = $typeName;
        if (strpos($typeName, ':') !== false) {
            list($prefix, $name) = explode(':', $typeName);
        }

        $namespace = $node->lookupNamespaceUri($prefix ?: '');

        return array(
            $name,
            $namespace,
            $prefix,
        );
    }
}
