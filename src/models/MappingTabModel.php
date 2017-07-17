<?php

namespace gathercontent\gathercontent\models;

use gathercontent\gathercontent\Gathercontent;

use Craft;
use craft\base\Model;

class MappingTabModel extends Model
{
    public $title;
    public $gatherContentTabId;
    public $mappingId;

    public function rules()
    {
        return [];
    }
}