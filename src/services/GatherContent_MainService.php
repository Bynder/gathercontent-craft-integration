<?php

namespace gathercontent\gathercontent\services;

use gathercontent\gathercontent\Gathercontent;
use Craft;
use craft\base\Component;
use SolspaceMigration\Library\Mappings\SalesforceObjectMapping;
use SolspaceMigration\Library\Repositories\RelationshipRepository;
use Symfony\Component\Yaml\Yaml;

Gathercontent::loadDependencies();

class GatherContent_MainService extends Component
{
    /** @var array */
    private $loadedConfig;

    /** @var GatherContent_DatabaseMigrationService */
    public $databaseMigrate;

    /** @var  GatherContent_GatherContentService $dataService*/
    public $dataService;

    /** @var $nextPageNumber - Next data page number */
    public $nextPageNumber;

    /** @var GatherContent_ConfigService $configService */
    public $configService;

    /** @var $newOffset */
    private $newOffset;

    /** @var $finishedMigration */
    private $finishedMigration;

    /**
     * Loads the CHI Sf Sync services
     */
    public function init()
    {
        parent::init();
    }

    public function __construct(array $config = [])
    {
        $this->dataService = Gathercontent::$plugin->gatherContent_gatherContent;
        $this->configService = Gathercontent::$plugin->gatherContent_config;

        parent::__construct($config);
    }

    /**
     * @return array
     */
    public function getSfToCraftConfig($mappingId = null)
    {
        $config = $this->getConfig($mappingId);

        if (isset($config["gc_to_craft"])) {
            return $config["gc_to_craft"];
        }

        return [];
    }

    /**
     * @param string $sfObjectType
     *
     * @return SalesforceObjectMapping
     * @throws \Exception
     */
    public function getSalesforceObjectMapping($sfObjectType)
    {
        $config = $this->getSfToCraftConfig();

        if (!isset($config[$sfObjectType])) {
            throw new \Exception("Could not find a mapping for Salesforce Object " . $sfObjectType);
        }

        return SalesforceObjectMapping::create($sfObjectType, $config[$sfObjectType]);
    }

    /**
     * @return array
     */
    public function getCraftEventsList($mappingId = null)
    {
        $config = $this->getConfig($mappingId);

        if (isset($config["craft_to_sf"])) {
            if (isset($config["craft_to_sf"]["events"])) {
                return $config["craft_to_sf"]["events"];
            }
        }
    }

    /**
     * Gets feed with migration data
     *
     * @param SalesforceObjectMapping $mapping
     * @param $specificIdList
     * @param $forceSync
     * @return null
     */
    public function getData(SalesforceObjectMapping $mapping, $specificIdList, $forceSync, $offset = 0)
    {
        $timestamp = RelationshipRepository::getLatestTimestamp(
            $mapping->objectType,
            $mapping->entityMapping->getTable()
        );

        if ($mapping->updateOnly && !$timestamp) {
            return null;
        }

        $response = $this->dataService
            ->setOffset($offset)
            ->getByType($mapping->objectType)
            ->asObjects()
            ->getData($specificIdList);

        $this->newOffset = $this->dataService->getNewOffest();
        $this->finishedMigration = $this->dataService->getFinishedMigration();

        return $response;
    }

    /**
     * Returns next page number or null if there is no page
     *
     * @return mixed
     */
    public function getNextPageNumber()
    {
        return $this->nextPageNumber;
    }

    /**
     * Returns next new offset
     *
     * @return mixed
     */
    public function getNewOffset()
    {
        return $this->newOffset;
    }

    public function getFinishedMigration()
    {
        return $this->finishedMigration;
    }

    /**
     * @return array
     */
    private function getConfig($mappingId = null)
    {
        if (is_null($this->loadedConfig)) {
            try {
                // You can also define your mapping using yml file
//                $config = Yaml::parse(file_get_contents(__DIR__ . "/../config/solspace-migartion.yml"));

                $config = $this->prepareConfingFromDatabase($mappingId);

            } catch (\Exception $e) {
                $config = [];
            }

            $this->loadedConfig = $config;
        }

        return $this->loadedConfig;
    }

    private function prepareConfingFromDatabase($mappingId = null)
    {
        $config = [
            'gc_to_craft' => []
        ];

        $config['gc_to_craft'] = $this->configService->getConfig($mappingId);

        return $config;
    }
}
