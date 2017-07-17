<?php

namespace gathercontent\gathercontent\records;

use gathercontent\gathercontent\Gathercontent;

use Craft;
use craft\db\ActiveRecord;
use yii\db\ActiveQuery;

class MappingFieldRecord extends ActiveRecord
{
    const TABLE = "{{%gathercontent_mapping_fields}}";

    public static function tableName()
    {
        return self::TABLE;
    }

    /**
     * @return ActiveQuery|MappingRecord
     */
    public function getTab(): ActiveQuery
    {
        return $this->hasOne(MappingTabRecord::className(), ['id' => 'tabId']);
    }
}
