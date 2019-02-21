<?php

namespace gathercontent\gathercontent\services;

use craft\base\Volume;
use craft\db\Query;
use craft\services\Volumes;
use gathercontent\gathercontent\Gathercontent;
use Craft;
use craft\base\Component;
use gathercontent\gathercontent\records\MappingRecord;
use SolspaceMigration\Library\Mappings\SalesforceObjectMapping;

class GatherContent_AssetService extends Component
{
    const GC_FOLDER_PATH = 'temp-files';
    const GC_FOLDER_ID = 6;

    /** @var  GatherContent_GatherContentService $gatherContentService */
    private $gatherContentService;

    public function init()
    {
        $this->gatherContentService = Gathercontent::$plugin->gatherContent_gatherContent;

        parent::init();
    }

    public function getValue($urls)
    {
        $result = [];

        if (empty($urls)) {
            return $result;
        }

        $testUrl = '';

        /** @var \craft\services\Assets $assetsService */
        $assetsService = \Craft::$app->get('assets');

        /** @var Volume $volume */
        $volume = $this->getVolume();

        if (!$volume) {
            return $result;
        }

        $path = $volume->getSettings()['path'];

        /** @var \craft\services\AssetIndexer $assetIndexer */
        $assetIndexer = \Craft::$app->get('assetIndexer');

        foreach ($urls as $urlInfo) {
            $filename = $this->downloadUrl($urlInfo, $path);

            $newAsset = $assetIndexer->indexFile($volume, $filename);

            if ($newAsset) {
                $result[] = $newAsset->id;
            }
        }


        return $result;
    }

    public function downloadUrl($urlInfo, $downloadPath)
    {
        $path = $downloadPath;

        $this->gatherContentService->downloadAsset($urlInfo->id, Craft::getAlias($path) . '/' . $urlInfo->filename);

        return $urlInfo->filename;
    }

    public function getVolume()
    {
        $volumeHandle =  Gathercontent::$plugin->getSettings()->volumeHandle;

        $volumeRecord = \craft\records\Volume::find()->select(['id'])->andWhere(['handle' => $volumeHandle])->one();

        if (!$volumeRecord) {
            return false;
        }

        /** @var Volumes $volumeService*/
        $volumeService = \Craft::$app->get('volumes');
        $volume = $volumeService->getVolumeById($volumeRecord->id);

        if (!$volume) {
            return false;
        }

        return $volume;
    }
}
