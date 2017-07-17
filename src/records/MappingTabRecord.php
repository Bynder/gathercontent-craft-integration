<?php

namespace gathercontent\gathercontent\records;

use gathercontent\gathercontent\Gathercontent;

use Craft;
use craft\db\ActiveRecord;
use yii\db\ActiveQuery;

class MappingTabRecord extends ActiveRecord
{
    const TABLE = "{{%gathercontent_mapping_tabs}}";

    public static function tableName()
    {
        return self::TABLE;
    }

    /**
     * @return ActiveQuery|MappingRecord
     */
    public function getMapping(): ActiveQuery
    {
        return $this->hasOne(MappingRecord::className(), ['id' => 'mappingId']);
    }

    /**
     * @return ActiveQuery|MappingFieldRecord
     */
    public function getFields(): ActiveQuery
    {
        return $this->hasMany(MappingFieldRecord::className(), ['tabId' => 'id']);
    }
}
