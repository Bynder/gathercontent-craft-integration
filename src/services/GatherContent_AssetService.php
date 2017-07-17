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
//        $path = Gathercontent::$plugin->basePath . '/' . self::GC_FOLDER_PATH;
        $path = $downloadPath;

        set_time_limit(0);
        //This is the file where we save the    information
        $fp = fopen ($path . '/' . $urlInfo->filename, 'w+');
        //Here is the file we are downloading, replace spaces with %20
        $ch = curl_init(str_replace(" ","%20",$urlInfo->url));
        curl_setopt($ch, CURLOPT_TIMEOUT, 50);
        // write curl response to file
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        // get curl response
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);

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
