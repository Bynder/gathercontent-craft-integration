<?php

namespace gathercontent\gathercontent\services;

use gathercontent\gathercontent\Gathercontent;
use Craft;
use craft\base\Component;
use gathercontent\gathercontent\models\MappingModel;
use gathercontent\gathercontent\records\MappingRecord;
use SolspaceMigration\Library\Exceptions\SolspaceMigratorFeedServiceException;
use GuzzleHttp\Client;
use function GuzzleHttp\Promise\settle;

class GatherContent_MappingService extends Component
{
    public function getAllMappings()
    {
        return MappingRecord::find()->all();
    }

    public function getAllTemplates()
    {
        $result = [];

        $mappingsRecords = $this->getAllMappings();

        if (!$mappingsRecords) {
            return $result;
        }

        foreach ($mappingsRecords as $mappingRecord) {

            /** @var MappingRecord v */
            $result[] = $mappingRecord->gatherContentTemplateId;
        }

        return $result;
    }

    public function getAllEntrTypes()
    {
        $result = [];

        $mappingsRecords = $this->getAllMappings();

        if (!$mappingsRecords) {
            return $result;
        }

        foreach ($mappingsRecords as $mappingRecord) {

            /** @var MappingRecord $mappingRecord */
            $result[] = $mappingRecord->craftEntryTypeId;
        }

        return $result;
    }

    public function notUsedTemplates($gatherContentTemplates, $keepUsedEntryTypes = false, $currentTemplateId = null)
    {
        $result = [];
        $usedTemplates = $this->getAllTemplates();

        if (!$keepUsedEntryTypes) {
            if (!$usedTemplates) {
                return $gatherContentTemplates;
            }

            if ($gatherContentTemplates) {
                foreach ($gatherContentTemplates as $gatherContentTemplate) {

                    if (!in_array($gatherContentTemplate['id'], $usedTemplates)) {
                        $result[] = $gatherContentTemplate;
                    }
                }
            }
        } else {
            $nothingIsUsed = false;

            if (!$usedTemplates) {
                $nothingIsUsed = true;
            }

            if ($gatherContentTemplates) {
                foreach ($gatherContentTemplates as $key => $template) {

                    $type['id'] = $template['id'];
                    $type['name'] = $template['name'];
                    $type['used'] = false;

                    if (!$nothingIsUsed && in_array($template['id'], $usedTemplates) && $currentTemplateId != $template['id']) {
                        $type['used'] = true;
                    }

                    $result[] = $type;
                }
            }
        }



        return $result;
    }

    public function notUsedEntryTypes($entryTypes, $keepUsedEntryTypes = false, $currentEntryTypeId = null)
    {
        $result = [];
        $usedEntryTypes = $this->getAllEntrTypes();

        if (!$keepUsedEntryTypes) {
            if (!$usedEntryTypes) {
                return $entryTypes;
            }

            if ($entryTypes) {
                foreach ($entryTypes as $entryType) {

                    if (!in_array($entryType->id, $usedEntryTypes)) {
                        $result[] = $entryType;
                    }
                }
            }
        } else {
            $nothingIsUsed = false;

            if (!$usedEntryTypes) {
                $nothingIsUsed = true;
            }

            if ($entryTypes) {
                foreach ($entryTypes as $key => $entryType) {

                    $type['id'] = $entryType->id;
                    $type['name'] = $entryType->name;
                    $type['used'] = false;

                    if (!$nothingIsUsed && in_array($entryType->id, $usedEntryTypes) && $currentEntryTypeId != $entryType->id) {
                        $type['used'] = true;
                    }

                    $result[] = $type;
                }
            }
        }

        return $result;
    }

    public function updateJustImported(MappingRecord $mappingRecord, $newOffest, $isFinishedMigration)
    {
        $mappingRecord->lastImportTimestamp = GatherContent::getCurrentDateTime();
        $mappingRecord->lastOffset = $newOffest;
        $mappingRecord->migrating = !$isFinishedMigration;

        return $mappingRecord->save();
    }
}
