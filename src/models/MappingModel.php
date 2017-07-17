<?php

namespace gathercontent\gathercontent\models;

use craft\services\Sections;
use gathercontent\gathercontent\Gathercontent;

use Craft;
use craft\base\Model;

class MappingModel extends Model
{
    const STATUS__ENABLED = 'enabled';
    const STATUS__DISABLED = 'disabled';
    const STATUS__DRAFT = 'draft';

    public $id;
    public $craftSectionId;
    public $gatherContentTemplateId;
    public $gatherContentProjectId;
    public $craftEntryTypeId;
    public $gatherContentTemplateName;
    public $gatherContentProjectName;
    public $lastOffset;
    public $lastImportTimestamp;
    public $migrating;
    public $fields;
    public $gatherContentAccountId;
    public $deactive;
    public $craftStatus;

    public function rules()
    {
        return [];
    }

    public static function getCraftStatuses()
    {
        return [
            self::STATUS__ENABLED => 'Enabled',
            self::STATUS__DISABLED => 'Disabled',
        ];
    }
}