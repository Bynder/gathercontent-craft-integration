<?php

namespace SolspaceMigration\Library\Mappings;

use ChiSfSync\Library\DateTimeUTC;
use ChiSfSync\Library\Exceptions\CraftFieldMappingException;
use ChiSfSync\Library\Repositories\RelationshipRepository;
use Craft\BaseModel;
use Craft\DateTime;
use Craft\ElementCriteriaModel;
use function Craft\returnIfSet;
use gathercontent\gathercontent\Gathercontent;
use gathercontent\gathercontent\services\GatherContent_AssetService;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyPath;

class CraftFieldMapping
{
    const KEY_RELATIONSHIP_TYPE = "relates_to";
    const KEY_FIELD             = "field";
    const KEY_VALUE_MAP         = "value_map";

    const TYPE_NUMBER = "number";
    const TYPE_TAGS = "tags";
    const TYPE_DROPDOWN = "dropdown";
    const TYPE_MULTI_SELECT = "multiselect";
    const TYPE_CHECKBOXES = "checkboxes";
    const TYPE_RADIO_BUTTONS = "radio";
    const TYPE_RICH_TEXT = "richText";
    const TYPE_TEXT      = "text";
    const TYPE_IMAGE     = "image";
    const TYPE_DATE      = "date";
    const TYPE_DATE_STRING = "date_string";
    const TYPE_ARRAY     = "array";
    const TYPE_INTEGER   = "integer";
    const TYPE_BOOL      = "bool";
    const TYPE_STATIC    = "static";
    const TYPE_READ_ONLY = "read_only";
    const TYPE_SETTING   = "setting";

    /** @var array */
    private static $allowedTypes = [
        self::TYPE_TEXT,
        self::TYPE_DATE,
        self::TYPE_DATE_STRING,
        self::TYPE_ARRAY,
        self::TYPE_INTEGER,
        self::TYPE_BOOL,
        self::TYPE_STATIC,
        self::TYPE_READ_ONLY,
        self::TYPE_SETTING,
    ];

    /** @var string */
    private $salesforceFieldName;

    /** @var string */
    private $craftFieldName;

    /** @var SalesforceObjectMapping */
    private $relatesTo;

    /** @var string */
    private $type = self::TYPE_TEXT;

    /** @var mixed */
    private $staticValue;

    /** @var array */
    private $valueMap;

    /** @var string */
    private $format;

    /**
     * @param string       $salesforceFieldName
     * @param string|array $config
     *
     * @return CraftFieldMapping
     * @throws CraftFieldMappingException
     */
    public static function create($salesforceFieldName, $config)
    {
        $mapping = new CraftFieldMapping();

        $mapping->salesforceFieldName = $salesforceFieldName;

        if (is_array($config)) {
            $relationshipType = Gathercontent::returnIfSet($config[self::KEY_RELATIONSHIP_TYPE]);
            if ($relationshipType) {
                $mapping->relatesTo = \Craft\chisfsync()->getSalesforceObjectMapping($relationshipType);
            }

            $type = Gathercontent::returnIfSet($config["type"]);
            if ($type) {
                if (!in_array($type, self::$allowedTypes)) {
                    throw new CraftFieldMappingException(
                        sprintf(
                            "Craft 'field' type of '%s' is not allowed. Allowed field types are '%s'",
                            $type,
                            implode("', '", self::$allowedTypes)
                        )
                    );
                }

                $mapping->type = $type;
            }

            $field = Gathercontent::returnIfSet($config[self::KEY_FIELD]);
            if (!$field && $type !== self::TYPE_STATIC) {
                throw new CraftFieldMappingException(
                    sprintf("Craft 'field' name not specified for '%s' field", $salesforceFieldName)
                );
            }

            if ($type === self::TYPE_STATIC) {
                $mapping->staticValue = Gathercontent::returnIfSet($config["value"]);
            }

            $mapping->valueMap       = Gathercontent::returnIfSet($config["value_map"]);
            $mapping->craftFieldName = $field;
            $mapping->format         = Gathercontent::returnIfSet($config["format"]);
        } else {
            $mapping->craftFieldName = $config;
        }

        return $mapping;
    }

    /**
     * Private CraftFieldMapping constructor.
     */
    private function __construct()
    {
    }

    /**
     * @return string|null
     */
    public function getCraftFieldName()
    {
        return $this->craftFieldName;
    }

    /**
     * @return string
     */
    public function getSalesforceFieldName()
    {
        $name = $this->salesforceFieldName;

        if ($this->isRelationshipField()) {
            return substr($name, 0, strpos($name, "."));
        }

        return $name;
    }

    /**
     * @return array
     */
    public function getValueMap()
    {
        return $this->valueMap;
    }

    /**
     * @return bool
     */
    public function isReadOnly()
    {
        return $this->type === self::TYPE_READ_ONLY;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param \SObject $SObject
     *
     * @return int|mixed|null
     */
    public function transformToCraftValue($SObject, $fieldType = 'text', $options = [])
    {
        $accessor = PropertyAccess::createPropertyAccessor();

        if ($this->relatesTo) {
            $craftId = RelationshipRepository::getElementId(
                $accessor->getValue($SObject, $this->salesforceFieldName),
                $this->relatesTo->getObjectType(),
                $this->relatesTo->getEntityMapping()->getTable()
            );

            if (!$craftId) {
                return null;
            }

            if ($this->type === self::TYPE_ARRAY) {
                return [$craftId];
            }

            return $craftId;
        }


        if (!property_exists($SObject, $this->salesforceFieldName)) {
            return false;
        }

        $value = $accessor->getValue($SObject, $this->salesforceFieldName);

//        $value = $SObject->{$this->salesforceFieldName};

        switch ($fieldType) {
            case self::TYPE_DATE:
                $value = DateTime::createFromFormat("Y-m-d", $value);
                break;

            case self::TYPE_DATE_STRING:
                $value = date(Gathercontent::returnIfSet($this->format, "Y-m-d"), strtotime($value));
                break;

            case self::TYPE_INTEGER:
                $value = (int)$value;
                break;

            case self::TYPE_ARRAY:
                $value = explode(";", $value);
                break;

            case self::TYPE_TEXT:
                $value = \strip_tags((string)$value);
                break;

            case self::TYPE_RICH_TEXT:
                $value = (string)$value;
                break;

            case self::TYPE_RADIO_BUTTONS:
                $value = $this->findSelectedOptions($value, $options);
                break;

            case self::TYPE_DROPDOWN:
                $value = $this->findSelectedOptions($value, $options);
                break;

            case self::TYPE_CHECKBOXES:
                $value = $this->findSelectedOptions($value, $options, true);
                break;

            case self::TYPE_MULTI_SELECT:
                $value = $this->findSelectedOptions($value, $options, true);
                break;

            case self::TYPE_TAGS:
                break;

            case self::TYPE_NUMBER:
                $value = strip_tags($value);
                $value = trim(preg_replace('/\s+/', '', $value));

                if (!is_numeric($value)) {
                    $value = null;
                }

                break;

            case self::TYPE_IMAGE:
                /** @var GatherContent_AssetService $assetService */
                $assetService = Gathercontent::$plugin->gatherContent_asset;
                $value = $assetService->getValue($value);
                break;

            case self::TYPE_BOOL:
                $value = (bool)$value;
                break;

            case self::TYPE_SETTING:
                $value = null;
                break;
        }

        if ($value && !empty($this->valueMap)) {
            if ($this->type === self::TYPE_ARRAY) {
                $value = array_intersect_key($this->valueMap, array_flip($value));
                $value = array_values($value);
            } else {
                $value = $this->valueMap[$value];
            }
        }

        return $value;
    }

    public function findSelectedOptions($needle, $options, $multiple = false)
    {
        $values = null;

        if (!$needle) {
            return $values;
        }

        if (count($options) > 0) {
            foreach ($options as $key => $option) {

                if (!$multiple) {
                    if ($option['label'] == $needle) {
                        $values = $option['value'];
                        break;
                    }
                } else {
                    if (in_array($option['label'], $needle)) {
                        $values[] = $option['value'];
                    }
                }

            }
        }

        return $values;
    }

    /**
     * @param BaseModel $model
     *
     * @return mixed
     */
    public function transformToSalesforceValue(BaseModel $model)
    {
        $value = null;

        if (is_null($this->getCraftFieldName())) {
            return $value;
        }

        if ($this->type === self::TYPE_STATIC) {
            $value = $this->staticValue;
        } else if ($this->type === self::TYPE_SETTING) {
            $value = \Craft\craft()->plugins->getPlugin("chiSfSync")->getSettings()->{$this->getCraftFieldName()};
        } else {
            $accessor   = PropertyAccess::createPropertyAccessor();
            $isReadable = $accessor->isReadable($model, $this->getCraftFieldName());

            if ($isReadable) {
                $value = $accessor->getValue($model, $this->getCraftFieldName());
            } else if (method_exists($model, "getContent")) {
                $value = $accessor->getValue($model->getContent(), $this->getCraftFieldName());
            }

            if ($this->relatesTo && $value instanceof ElementCriteriaModel) {
                $value = $value->first()->id;
            }

            if ($value && !empty($this->valueMap)) {
                $valueMap = array_flip($this->valueMap);
                if ($this->type === self::TYPE_ARRAY) {
                    $value = array_intersect($value, $valueMap);
                } else {
                    $value = $valueMap[$value];
                }
            }

            switch ($this->type) {
                case self::TYPE_INTEGER:
                    $value = (int)$value;
                    break;

                case self::TYPE_ARRAY:
                    if (is_array($value)) {
                        $value = implode(";", $value);
                    }
                    break;
            }
        }

        $value = Gathercontent::returnIfSet($value, htmlspecialchars($value));

        if ($this->relatesTo && $value) {
            $salesforceId = RelationshipRepository::getSalesforceEntityId(
                $this->relatesTo->getObjectType(),
                $value,
                $this->relatesTo->getEntityMapping()->getTable()
            );

            if (!$salesforceId) {
                return null;
            }

            return $salesforceId;
        }


        return $value;
    }

    /**
     * @return bool
     */
    private function isRelationshipField()
    {
        return strpos($this->salesforceFieldName, ".") !== false;
    }
}
