<?php
declare(strict_types = 1);
namespace GoetasWebservices\XML\XSDReader\Schema\Inheritance;

use DOMElement;
use GoetasWebservices\XML\XSDReader\Schema\Type\Type;
use GoetasWebservices\XML\XSDReader\SchemaReader;

class Restriction extends Base
{
    /**
    * @var mixed[][]
    */
    protected $checks = array();

    /**
    * @param string $type
    * @param mixed[] $value
    *
    * @return $this
    */
    public function addCheck(string $type, array $value)
    {
        $this->checks[$type][] = $value;
        return $this;
    }

    /**
    * @return mixed[][]
    */
    public function getChecks() : array
    {
        return $this->checks;
    }

    /**
    * @param string $type
    *
    * @return mixed[]
    */
    public function getChecksByType(string $type) : array
    {
        return isset($this->checks[$type])?$this->checks[$type]:array();
    }

    public static function loadRestriction(
        SchemaReader $reader,
        Type $type,
        DOMElement $node
    ) : void {
        $restriction = new Restriction();
        $type->setRestriction($restriction);
        if ($node->hasAttribute("base")) {
            $reader->findAndSetSomeBase($type, $restriction, $node);
        } else {
            $addCallback = function (Type $restType) use (
                $restriction
            ) : void {
                $restriction->setBase($restType);
            };

            Type::loadTypeWithCallbackOnChildNodes(
                $reader,
                $type->getSchema(),
                $node,
                $addCallback
            );
        }
        foreach ($node->childNodes as $childNode) {
            if (
                in_array(
                    $childNode->localName,
                    [
                        'enumeration',
                        'pattern',
                        'length',
                        'minLength',
                        'maxLength',
                        'minInclusive',
                        'maxInclusive',
                        'minExclusive',
                        'maxExclusive',
                        'fractionDigits',
                        'totalDigits',
                        'whiteSpace'
                    ],
                    true
                )
            ) {
                $restriction->addCheck(
                    $childNode->localName,
                    [
                        'value' => $childNode->getAttribute("value"),
                        'doc' => SchemaReader::getDocumentation($childNode)
                    ]
                );
            }
        }
    }
}
