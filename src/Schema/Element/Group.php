<?php
declare(strict_types = 1);
namespace GoetasWebservices\XML\XSDReader\Schema\Element;

use Closure;
use DOMElement;
use GoetasWebservices\XML\XSDReader\Schema\Attribute\AttributeItemTrait;
use GoetasWebservices\XML\XSDReader\Schema\Schema;
use GoetasWebservices\XML\XSDReader\SchemaReader;

class Group implements ElementItem, ElementContainer
{
    use AttributeItemTrait;
    use ElementContainerTrait;

    /**
     *
     * @var Schema
     */
    protected $schema;

    /**
    * @var string
    */
    protected $doc = '';

    public function __construct(Schema $schema, string $name)
    {
        $this->schema = $schema;
        $this->name = $name;
    }

    public function getDoc() : string
    {
        return $this->doc;
    }

    /**
    * @return $this
    */
    public function setDoc(string $doc) : self
    {
        $this->doc = $doc;
        return $this;
    }

    public function getSchema() : Schema
    {
        return $this->schema;
    }

    public static function loadGroup(
        SchemaReader $reader,
        Schema $schema,
        DOMElement $node
    ) : Closure {
        $group = new Group($schema, $node->getAttribute("name"));
        $group->setDoc(SchemaReader::getDocumentation($node));

        if ($node->hasAttribute("maxOccurs")) {
            /**
            * @var GroupRef $group
            */
            $group = SchemaReader::maybeSetMax(new GroupRef($group), $node);
        }
        if ($node->hasAttribute("minOccurs")) {
            /**
            * @var GroupRef $group
            */
            $group = SchemaReader::maybeSetMin(
                $group instanceof GroupRef ? $group : new GroupRef($group),
                $node
            );
        }

        $schema->addGroup($group);

        static $methods = [
            'sequence' => 'loadSequence',
            'choice' => 'loadSequence',
            'all' => 'loadSequence',
        ];

        return function () use ($reader, $group, $node, $methods) : void {
            foreach ($node->childNodes as $childNode) {
                $reader->maybeCallMethod(
                    $methods,
                    (string) $childNode->localName,
                    $childNode,
                    $group,
                    $childNode
                );
            }
        };
    }
}
