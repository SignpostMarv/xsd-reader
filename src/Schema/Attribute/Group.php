<?php
declare(strict_types = 1);
namespace GoetasWebservices\XML\XSDReader\Schema\Attribute;

use Closure;
use DOMElement;
use GoetasWebservices\XML\XSDReader\SchemaReader;
use GoetasWebservices\XML\XSDReader\Schema\Schema;

class Group implements AttributeItem, AttributeContainer
{
    use AttributeItemTrait;
    use AttributeContainerTrait;

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

    public static function findSomethingLikeThis(
        SchemaReader $useThis,
        Schema $schema,
        DOMElement $node,
        DOMElement $childNode,
        AttributeContainer $addToThis
    ) : void {
        /**
        * @var AttributeItem $attribute
        */
        $attribute = $useThis->findSomething('findAttributeGroup', $schema, $node, $childNode->getAttribute("ref"));
        $addToThis->addAttribute($attribute);
    }

    public static function loadAttributeGroup(
        SchemaReader $schemaReader,
        Schema $schema,
        DOMElement $node
    ) : Closure {
        $attGroup = new self($schema, $node->getAttribute("name"));
        $attGroup->setDoc(SchemaReader::getDocumentation($node));
        $schema->addAttributeGroup($attGroup);

        return function () use (
            $schemaReader,
            $schema,
            $node,
            $attGroup
        ) : void {
            foreach ($node->childNodes as $childNode) {
                switch ($childNode->localName) {
                    case 'attribute':
                        $attribute = Attribute::getAttributeFromAttributeOrRef(
                            $schemaReader,
                            $childNode,
                            $schema,
                            $node
                        );
                        $attGroup->addAttribute($attribute);
                        break;
                    case 'attributeGroup':
                        self::findSomethingLikeThis(
                            $schemaReader,
                            $schema,
                            $node,
                            $childNode,
                            $attGroup
                        );
                        break;
                }
            }
        };
    }
}
