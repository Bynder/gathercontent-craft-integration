<?php

namespace gathercontent\gathercontent\services;

use gathercontent\gathercontent\Gathercontent;
use Craft;
use craft\base\Component;
use gathercontent\gathercontent\records\MappingRecord;
use SolspaceMigration\Library\Exceptions\SolspaceMigratorFeedServiceException;
use GuzzleHttp\Client;
use function GuzzleHttp\Promise\settle;

class GatherContent_ConfigService extends Component
{
    public $config;

    public function __construct()
    {
        $configFile = GatherContent::getConfig();
        $this->config = $configFile['migrate'];
    }

    public function getConfig($mappingId = null, $migrateMappingsCount = 1)
    {
        $config = [];

        if ($mappingId === null) {
            $mappingsRecords = MappingRecord::find()
                ->where(['migrating' => true])
                ->limit($migrateMappingsCount)
                ->all();

            if (!$mappingsRecords) {

                $mappingsRecords = MappingRecord::find()
                    ->limit($migrateMappingsCount)
                    ->orderBy(['lastImportTimestamp'=> SORT_ASC])
                    ->all();
            }
        } else {
            $mappingsRecords = MappingRecord::find()
                ->where(['gatherContentTemplateId' => $mappingId])
                ->limit(1)
                ->all();
        }

        if (!$mappingsRecords) {
            return $config;
        }

        foreach ($mappingsRecords as $key => $mappingRecord) {

            $fields = [];

            /** @var MappingRecord $mappingRecord */

            if (array_key_exists('legacyIdFieldHandle', $this->config)) {
                $fields = [
                    'id' => 'content.' . $this->config['legacyIdFieldHandle'],
                ];
            }

            foreach ($mappingRecord->tabs as $tab) {
                foreach ($tab->fields as $field) {
                    $fields[$field->gatherContentElementName] = $field->craftFieldHandle;
                }
            }

            $config[$mappingRecord->gatherContentTemplateId] = [
                'craft_entity' => [
                    'model' => 'craft\elements\Entry',
                    'record' => 'craft\records\Entry',
                    'service' => 'elements::saveElement',
                    'table' => 'craft\elements\Entry',
                    'attributes' => [
                        'sectionId' => $mappingRecord->craftSectionId,
                        'typeId' => $mappingRecord->craftEntryTypeId,
                        'status' => $mappingRecord->craftStatus,
                        'slug' => 'default-slug-' . time(),
                        'title' => 'Empty title',
                        ],
                    ],
                'fields' => $fields,
            ];
        }

        return $config;
    }
}
