<?php

namespace gathercontent\gathercontent\models;

use gathercontent\gathercontent\Gathercontent;

use Craft;
use craft\base\Model;

class MappingFieldModel extends Model
{
    public $craftFieldHandle;
    public $gatherContentElementName;
    public $mappingId;

    public function rules()
    {
        return [];
    }
}