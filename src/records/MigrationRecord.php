<?php

namespace gathercontent\gathercontent\records;

use gathercontent\gathercontent\Gathercontent;

use Craft;
use craft\db\ActiveRecord;
use yii\db\ActiveQuery;

class MigrationRecord extends ActiveRecord
{
    const TABLE = "{{%gathercontent_migrations}}";

    public static function tableName()
    {
        return self::TABLE;
    }

    /**
     * @return ActiveQuery|MigrationItemRecord
     */
    public function getItems(): ActiveQuery
    {
        return $this->hasMany(MigrationItemRecord::className(), ['migrationId' => 'id']);
    }
}
