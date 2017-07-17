<?php

namespace gathercontent\gathercontent\models;

use gathercontent\gathercontent\Gathercontent;

use Craft;
use craft\base\Model;

class MigrationItemModel extends Model
{
    const STATUSS__SUCCESS = 'success';
    const STATUSS__FAIL = 'fail';

    const STATUSES = [
        self::STATUSS__SUCCESS,
        self::STATUSS__FAIL,
    ];

    public $id;
    public $migrationId;
    public $mappingId;
    public $relationshipId;
    public $itemId;
    public $status;

    public function rules()
    {
        return [];
    }

    public function getStatuses()
    {
        return self::STATUSES;
    }
}