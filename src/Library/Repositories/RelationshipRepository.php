<?php

namespace SolspaceMigration\Library\Repositories;

use Craft\BaseElementModel;
use Craft\BaseModel;
use craft\services\Elements;
use gathercontent\gathercontent\records\RelationTableRecord;

class RelationshipRepository
{
    /**
     * If a relationship exists between a Craft element and Salesforce
     * We get that element's ID here
     *
     * Null otherwise
     *
     * @param string $sfId
     * @param string $sfObjectType
     * @param string $craftElementTable
     *
     * @return int|null
     */
    public static function getElementId($sfId, $sfObjectType, $craftElementTable)
    {
        $elementId = null;

        $records = RelationTableRecord::find()
            ->where(
                [
                    "craftElementTable" => $craftElementTable,
                    "sfEntityId"        => $sfId,
                    "sfObject"          => $sfObjectType,
                ]
            )
            ->orderBy(['id' => SORT_ASC])
            ->all();

        if ($records) {

            $record = array_pop($records);
            $elementId = $record->craftElementId;


            // Delte Relationship Records with Non-Existent Entries
            if (count($records) > 0) {
                foreach ($records as $deletedRecord) {

                    /** @var Elements $elementsService */
                    $elementsService = \Craft::$app->get('elements');
                    $entry = $elementsService->getElementById($deletedRecord->craftElementId);

                    if (!$entry) {
                        $deletedRecord->delete();
                    }
                }
            }
        }



//        $elementId = \Craft\craft()
//            ->db
//            ->createCommand()
//            ->select("craftElementId")
//            ->from(_RelationTableRecord::TABLE)
//            ->where(
//                [
//                    "craftElementTable" => $craftElementTable,
//                    "sfEntityId"        => $sfId,
//                    "sfObject"          => $sfObjectType,
//                ]
//            )
//            ->queryScalar();

        if (!$elementId) {
            return null;
        }

        return (int)$elementId;
    }

    /**
     * If a relationship exists between a Craft element and Salesforce
     * We get that element's Salesforce Entity ID here
     *
     * Null otherwise
     *
     * @param string $sfObjectType
     * @param int    $craftElementId
     * @param string $craftElementTable
     *
     * @return int|null
     */
    public static function getSalesforceEntityId(string $sfObjectType, int $craftElementId, string $craftElementTable)
    {
        $salesforceEntityId = null;

        $record = RelationTableRecord::find()
            ->where(
                [
                    "craftElementTable" => $craftElementTable,
                    "craftElementId"    => $craftElementId,
                    "sfObject"          => $sfObjectType,
                ]
            )
            ->one();

        if ($record) {
            $salesforceEntityId = $record->sfEntityId;
        }

//        $salesforceEntityId = \Craft\craft()
//            ->db
//            ->createCommand()
//            ->select("sfEntityId")
//            ->from(_RelationTableRecord::TABLE)
//            ->where(
//                [
//                    "craftElementTable" => $craftElementTable,
//                    "craftElementId"    => $craftElementId,
//                    "sfObject"          => $sfObjectType,
//                ]
//            )
//            ->queryScalar();

        if (!$salesforceEntityId) {
            return null;
        }

        return $salesforceEntityId;
    }

    /**
     * @param string $sfObjectType
     * @param string $craftElementTable
     *
     * @return array
     */
    public static function getAllSalesforceEntityIds(string $sfObjectType, string $craftElementTable)
    {
        $entityIds = \Craft\craft()
            ->db
            ->createCommand()
            ->select("sfEntityId")
            ->from(_RelationTableRecord::TABLE)
            ->where(
                [
                    "craftElementTable" => $craftElementTable,
                    "sfObject"          => $sfObjectType,
                ]
            )
            ->queryColumn();

        return $entityIds;
    }

    /**
     * Inserts a relationship if one doesn't exist
     * Updates it's "dateUpdated", if it does
     *
     * @param int    $craftElementId
     * @param string $craftElementTable
     * @param string $sfId
     * @param string $sfObjectType
     * @param int    $lastModifiedDate
     */
    public static function insertOrUpdateRelationship(
        $craftElementId,
        $craftElementTable,
        $sfId,
        $sfObjectType,
        $lastModifiedDate
    ) {
        $lastModifiedDate = date("Y-m-d H:i:s", $lastModifiedDate);

//        $id = \Craft\craft()
//            ->db
//            ->createCommand()
//            ->select("id")
//            ->from(_RelationTableRecord::TABLE)
//            ->where(
//                [
//                    "craftElementId"    => $craftElementId,
//                    "craftElementTable" => $craftElementTable,
//                    "sfEntityId"        => $sfId,
//                    "sfObject"          => $sfObjectType,
//                ]
//            )
//            ->queryScalar();

        $id = null;

        $record = RelationTableRecord::find()
            ->where(
                [
                    "craftElementId"    => $craftElementId,
                    "craftElementTable" => $craftElementTable,
                    "sfEntityId"        => $sfId,
                    "sfObject"          => $sfObjectType,
                ]
            )
            ->one();

        if ($record) {


//            \Craft\craft()
//                ->db
//                ->createCommand()
//                ->update(
//                    _RelationTableRecord::TABLE,
//                    ["sfLastModifiedDate" => $lastModifiedDate],
//                    "id = :id",
//                    ["id" => (int)$id]
//                );

            $record->sfLastModifiedDate = $lastModifiedDate;
            $record->save();

        } else {

            $record = new RelationTableRecord();
            $record->craftElementId = $craftElementId;
            $record->craftElementTable = $craftElementTable;
            $record->sfEntityId = $sfId;
            $record->sfObject = $sfObjectType;
            $record->sfLastModifiedDate = $lastModifiedDate;
            $record->save();

//            \Craft\craft()
//                ->db
//                ->createCommand()
//                ->insert(
//                    _RelationTableRecord::TABLE,
//                    [
//                        "craftElementId"     => $craftElementId,
//                        "craftElementTable"  => $craftElementTable,
//                        "sfEntityId"         => $sfId,
//                        "sfObject"           => $sfObjectType,
//                        "sfLastModifiedDate" => $lastModifiedDate,
//                    ]
//                );
        }

        return $record->id;
    }

    /**
     * @param BaseModel   $model
     * @param string|null $salesforceId
     */
    public static function updateSalesforceEntryId(BaseModel $model, string $salesforceId = null)
    {
        if ($salesforceId && $model instanceof BaseElementModel) {
            // Update the salesforce entity ID
            \Craft\craft()
                ->db
                ->createCommand()
                ->update(
                    "content",
                    [
                        "field_salesforceEntityId" => $salesforceId
                    ],
                    "elementId = :elementId",
                    ["elementId" => $model->id]
                );
        }
    }

    /**
     * Get the latest update timestamp for a specific mapping
     *
     * @param string $sfObjectType
     * @param string $craftElementTable
     *
     * @return int|null
     */
    public static function getLatestTimestamp($sfObjectType, $craftElementTable)
    {
        $timestamp = null;

//        $timestamp = \Craft\craft()
//            ->db
//            ->createCommand()
//            ->select("sfLastModifiedDate")
//            ->from(_RelationTableRecord::TABLE)
//            ->where(
//                [
//                    "craftElementTable" => $craftElementTable,
//                    "sfObject"          => $sfObjectType,
//                ]
//            )
//            ->order("sfLastModifiedDate DESC")
//            ->queryScalar();

        $relationTable = RelationTableRecord::find()
            ->where(
                [
                    "craftElementTable" => $craftElementTable,
                    "sfObject"          => $sfObjectType,
                ]
            )
            ->orderBy(['sfLastModifiedDate' => SORT_DESC])
            ->one();

        if ($relationTable) {
            $timestamp = $relationTable->sfLastModifiedDate;
        }

        if ($timestamp) {
            return strtotime($timestamp);
        }

        return null;
    }
}
