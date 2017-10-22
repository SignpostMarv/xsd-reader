<?php
declare(strict_types = 1);
namespace GoetasWebservices\XML\XSDReader\Schema\Element;

use Closure;
use DOMElement;
use GoetasWebservices\XML\XSDReader\Schema\Attribute\AttributeItemTrait;
use GoetasWebservices\XML\XSDReader\Schema\Schema;
use GoetasWebservices\XML\XSDReader\SchemaReader;
use GoetasWebservices\XML\XSDReader\SchemaReaderLoadAbstraction;

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

    protected static function loadGroupBeforeCheckingChildNodes(
        Schema $schema,
        DOMElement $node
    ) : Group {
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

        return $group;
    }

    public static function loadGroup(
        SchemaReaderLoadAbstraction $reader,
        Schema $schema,
        DOMElement $node
    ) : Closure {
        $group = static::loadGroupBeforeCheckingChildNodes(
            $schema,
            $node
        );
        static $methods = [
            'sequence' => 'loadSequence',
            'choice' => 'loadSequence',
            'all' => 'loadSequence',
        ];

        return function () use ($reader, $group, $node, $methods) : void {
            /**
            * @var string[] $methods
            */
            $methods = $methods;
            $reader->maybeCallMethodAgainstDOMNodeList(
                $node,
                $group,
                $methods
            );
        };
    }
}
