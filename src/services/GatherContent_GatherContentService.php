<?php

namespace gathercontent\gathercontent\services;

use gathercontent\gathercontent\Gathercontent;
use Craft;
use craft\base\Component;
use GuzzleHttp\Client;
use function GuzzleHttp\Promise\settle;

class GatherContent_GatherContentService extends Component
{
    const TYPE_RULES = [
        'files' => [
            'craft\fields\Assets',
        ],

        'text' => [
            'craft\fields\PlainText',
            'craft\fields\Tags',
            'craft\fields\Number',
            'craft\ckeditor\Field',
            'craft\redactor\Field',
        ],
        'choice_radio' => [
            'craft\fields\RadioButtons',
            'craft\fields\Dropdown',
        ],
        'choice_checkbox' => [
            'craft\fields\Checkboxes',
            'craft\fields\MultiSelect',
            'craft\fields\Tags',
        ],
    ];



    const FEED_NAMES__ROWS = 'rows';

    const RETURN_TYPE__OBJECTS = 'object';

    public $objectType;
    public $returnType;
    public $configFile;
    public $endpoint;
    public $feedUrl;
    public $testJsonsDirectory;
    public $pageNumber;
    public $nextPageNumber;
    public $responseData;

    public $limit;
    public $lastOffset = 0;
    public $newOffset = 0;
    public $finishedMigration;
    public $settings;

    public function __construct(array $config = [])
    {
        $this->configFile = GatherContent::getConfig();
        $this->limit = $this->configFile['migrate']['batchLimit'];
        $this->settings = Gathercontent::$plugin->getSettings();

        parent::__construct($config);
    }

    public function setOffset($offset)
    {
        $this->lastOffset = $offset;

        return $this;
    }

    /**
     * @return array
     */
    public static function validReturnTypes()
    {
        return [
            self::RETURN_TYPE__OBJECTS,
        ];
    }

    /**
     * @return array
     */
    public static function requiredConfigFields()
    {
        return [
            self::CONFIG_FIELD__FEED_URL,
            self::CONFIG_FIELD__IS_FEED_TESTING,
        ];
    }

    public function getNextPageNumber()
    {
        return $this->nextPageNumber;
    }

    public function getNewOffest()
    {
        return $this->newOffset;
    }

    public function getFinishedMigration()
    {
        return $this->finishedMigration;
    }

    /**
     * Validates return type
     *
     * @return bool
     * @throws \Exception
     */
    public function validateReturnType()
    {
        if (!$this->returnType) {
            throw new \Exception('returnType not set');
        }

        if (!in_array($this->returnType, self::validReturnTypes())) {
            throw new \Exception(sprintf("%s is not valid return type", $this->returnType));
        }

        return true;
    }

    /**
     * Set's page number
     *
     * @param $page
     * @return $this
     * @throws \Exception
     */
    public function page($page)
    {
        $this->validatePageNumber($page);

        $this->pageNumber = $page;

        return $this;
    }

    /**
     * Validates return type
     *
     * @return bool
     * @throws \Exception
     */
    public function validateConfigFile()
    {
        foreach (self::requiredConfigFields() as $requiredField) {
            if (!array_key_exists($requiredField, $this->configFile)) {
                throw new \Exception($requiredField . ' not set in config file');
            }
        }

        return true;
    }

    /**
     * Validates object type
     *
     * @return bool
     * @throws \Exception
     */
    public function validateObjectType()
    {
        if (!$this->objectType) {
            throw new \Exception('Object type  not set');
        }

        return true;
    }

    /**
     * Sets object type
     *
     * @param $objectType
     * @return $this
     */
    public function getByType($objectType)
    {
        $this->objectType = $objectType;

        return $this;
    }

    /**
     * Sets return type as objects
     *
     * @return $this
     */
    public function asObjects()
    {
        $this->returnType = self::RETURN_TYPE__OBJECTS;

        return $this;
    }

    public function getBlocks()
    {
        $blocks = [];
        $client = $this->getClient();

        $projectsList = $client->get('/projects', [
            'query' => [
                'account_id' => $this->configFile['gatherContent']['accountId'],
            ]
        ]);

        $projects = json_decode($projectsList->getBody(), true)['data'];

        foreach ($projects as $pKey => $project) {
            $blocks[$pKey]['projectId'] = $project['id'];
            $blocks[$pKey]['projectName'] = $project['name'];
            $blocks[$pKey]['templates'] = [];

            $templatesList = $client->get('/templates', [
                'query' => [
                    'project_id' => $project['id'],
                ]
            ]);

            $templates = json_decode($templatesList->getBody(), true)['data'];

            foreach ($templates as $tKey => $template) {
                $blocks[$pKey]['templates'][$tKey]['templateId'] = $template['id'];
                $blocks[$pKey]['templates'][$tKey]['templateName'] = $template['name'];
                $blocks[$pKey]['templates'][$tKey]['elements'] = [];

                $templateCall = $client->get('/templates/'.$template['id']);
                $templateResult = json_decode($templateCall->getBody(), true)['data'];

                $foundTemplateContent = Gathercontent::arrayAdvancedSearch($templateResult['config'], 'label', $this->getContentTabName());

                if (!empty($foundTemplateContent)) {
                    $elements = $foundTemplateContent[0]['elements'];

                    foreach ($elements as $eKey => $element) {
                        $blocks[$pKey]['templates'][$tKey]['elements'][$eKey]['elementName'] = $element['name'];
                        $blocks[$pKey]['templates'][$tKey]['elements'][$eKey]['elementLabel'] = null;
                        $blocks[$pKey]['templates'][$tKey]['elements'][$eKey]['elementTitle'] = null;

                        if (array_key_exists('label', $element)) {
                            $blocks[$pKey]['templates'][$tKey]['elements'][$eKey]['elementLabel'] = $element['label'];
                        }


                        if (array_key_exists('title', $element)) {
                            $blocks[$pKey]['templates'][$tKey]['elements'][$eKey]['elementTitle'] = $element['title'];
                        }
                    }
                }
            }
        }

        return $blocks;
    }

    public function getAccounts()
    {
        $result = [];
        $client = $this->getClient();

        $accountsList = $client->get('/accounts');

        $accounts = json_decode($accountsList->getBody(), true)['data'];

        foreach ($accounts as $pKey => $account) {
            $result[$pKey]['id'] = $account['id'];
            $result[$pKey]['name'] = $account['name'];
        }

        return $result;
    }

    public function validateUser($username, $apiKey)
    {
        $client = $this->getClientManually($username, $apiKey);
        try {
            $meResponse = $client->get('/me');
            $me = json_decode($meResponse->getBody(), true)['data'];
        } catch (\Exception $exception)
        {
            return false;
        }

        return true;
    }

    public function getProjects($accountId)
    {
        $result = [];
        $client = $this->getClient();

        $projectsList = $client->get('/projects', [
            'query' => [
                'account_id' => $accountId,
            ]
        ]);

        $projects = json_decode($projectsList->getBody(), true)['data'];

        foreach ($projects as $pKey => $project) {
            $result[$pKey]['id'] = $project['id'];
            $result[$pKey]['name'] = $project['name'];
        }

        return $result;
    }

    public function getTemplatetNameById($templateId)
    {
        $client = $this->getClient();

        $templateCall = $client->get('/templates/'.$templateId);
        $templateResult = json_decode($templateCall->getBody(), true)['data'];

        return $templateResult['name'];
    }

    public function getTemplateItemsCount($templateId)
    {
        $client = $this->getClient();

        $templateListResponse = $client->get('/templates/'. $templateId);
        $templateData = json_decode($templateListResponse->getBody(), true)['data'];

        $projectId = $templateData['project_id'];

        $itemListResponse = $client->get('/items', [
            'query' => [
                'project_id' => $projectId,
            ]
        ]);

        $items = json_decode($itemListResponse->getBody(), true)['data'];
        $items = $this->getTemplateItems($items, $templateId);

        $promises = array_map(function ($item) use ($client) {
            return $client->getAsync('/items/' . $item['id']);
        }, $items);

        $promiseResponses = settle($promises)->wait();

        $encodedItemResponses = array_column($promiseResponses, 'value');

        $itemsData = array_map(function ($response) {
            return json_decode($response->getBody(), true)['data'];
        }, $encodedItemResponses);

        return count($itemsData);
    }

    public function getTemplateDetails($templateId, $accountId)
    {
        $accountSlug = $this->getAccountSlug($accountId);
        $templateItems = [];
        $client = $this->getClient();

        $templateListResponse = $client->get('/templates/'. $templateId);
        $templateData = json_decode($templateListResponse->getBody(), true)['data'];

        $projectId = $templateData['project_id'];

        $itemListResponse = $client->get('/items', [
            'query' => [
                'project_id' => $projectId,
            ]
        ]);

        $items = json_decode($itemListResponse->getBody(), true)['data'];

        $items = $this->getTemplateItems($items, $templateId);

        $promises = array_map(function ($item) use ($client) {
            return $client->getAsync('/items/' . $item['id']);
        }, $items);

        $promiseResponses = settle($promises)->wait();

        $encodedItemResponses = array_column($promiseResponses, 'value');

        $itemsData = array_map(function ($response) {
            return json_decode($response->getBody(), true)['data'];
        }, $encodedItemResponses);

        foreach ($itemsData as $key => $item) {

            if ($item['template_id'] != $templateId) {
                continue;
            }

            $itemResult = [
                'id' => $item['id'],
                'title' => $item['name'],
                'status' => $item['status']['data']['name'],
                'updated' => $item['updated_at']['date'],
                'url' => $this->getItemUrl($item['id'], $accountId, $accountSlug),
            ];

            $templateItems[] = $itemResult;
        }

        return [
            'info' => $templateData,
            'items' => $templateItems,
            'itemsCount' => count($templateItems),
        ];
    }

    public function getItemUrl($itemId, $accountId, $accountSlug = null)
    {
        // Example
        // https://solspace2.gathercontent.com/item/4537182

        $url = null;

        if ($accountSlug === null) {
            if ($accountId !== null) {
                $accountSlug = $this->getAccountSlug($accountId);
            }
        }

        if ($accountSlug !== null) {
            $url = 'https://{{account_slug}}.gathercontent.com/item/{{item_id}}';
            $url = str_replace('{{account_slug}}', $accountSlug, $url);
            $url = str_replace('{{item_id}}', $itemId, $url);
        }

        return $url;
    }

    public function getAccountSlug($accountId)
    {
        $client = $this->getClient();
        $response = $client->get('/accounts/' . $accountId);
        $account = json_decode($response->getBody(), true)['data'];

        return $account['slug'];
    }

    public function getProjectNameById($projectId)
    {
        $client = $this->getClient();

        $projectCall = $client->get('/projects/'.$projectId);
        $projectResult = json_decode($projectCall->getBody(), true)['data'];

        return $projectResult['name'];
    }

    public function getTemplatesByProjectId($projectId)
    {
        $result = [];
        $client = $this->getClient();

        $templatesList = $client->get('/templates', [
            'query' => [
                'project_id' => $projectId,
            ]
        ]);

        $templates = json_decode($templatesList->getBody(), true)['data'];

        if (array_key_exists('message', $templates) && $templates['message'] == 'Project Not Found') {
            return $result;
        }

        foreach ($templates as $tKey => $template) {
            $result[$tKey]['id'] = $template['id'];
            $result[$tKey]['name'] = $template['name'];
        }

        return $result;
    }

    public function getElementsByTemplateId($templateId, $fullInfo = false, $onlyElements = false)
    {
        $result = [];
        $client = $this->getClient();

        $templateCall = $client->get('/templates/'.$templateId);
        $foundTemplateContent = json_decode($templateCall->getBody(), true)['data'];

        // Filter particular tab
//        $foundTemplateContent = GatherContent::arrayAdvancedSearch($templateResult['config'], 'label', $this->getContentTabName());

        if (!empty($foundTemplateContent)) {
            $elementsTabs = $foundTemplateContent['config'];

            foreach ($elementsTabs as $tKey => $tab) {

                $tabResult = [
                    'title' => $tab['label'],
                    'id' => $tab['name'],
                    'elements' => [],
                ];

                foreach ($tab['elements'] as $eKey => $element) {
                    $tabResult['elements'][$eKey]['name'] = $element['name'];
                    $tabResult['elements'][$eKey]['label'] = null;
                    $tabResult['elements'][$eKey]['title'] = null;

                    if ($fullInfo) {

                        if (array_key_exists('type', $element)) {
                            $tabResult['elements'][$eKey]['type'] = $element['type'];
                        }
                    }

                    if (array_key_exists('label', $element)) {
                        $tabResult['elements'][$eKey]['label'] = $element['label'];
                    }


                    if (array_key_exists('title', $element)) {
                        $tabResult['elements'][$eKey]['title'] = $element['title'];
                    }
                }

                $result[$tKey] = $tabResult;
            }

            if ($onlyElements) {
                $onlyElementsResult = [];
                foreach ($result as $tab) {

                    foreach ($tab['elements'] as $element)  {
                        $onlyElementsResult[$element['name']] = $element;
                    }
                }

                return $onlyElementsResult;
            }
        }

        return $result;
    }

    /**
     * Returns feed data
     *
     * @return bool|mixed
     * @throws \Exception
     */
    public function getData($specificIdList = null)
    {
        $json = $this->callEndpoint($specificIdList);
        $this->processResponse($json);
        $responseData = $this->preFormat($this->responseData);

        return $responseData;
    }

    /**
     * Processes JSON response by set return type
     *
     * @param $json
     * @return bool|mixed
     */
    private function processResponse($json)
    {
        if ($this->returnType == self::RETURN_TYPE__OBJECTS) {
            $objects = $this->formatJsonToObjects($json);
            $this->responseData = $objects;

            return true;
        }

        return false;
    }

    private function preFormat($responseData)
    {
        $newArray = [];

        foreach ($responseData as $responseObject) {

            if (isset($responseObject->ID)) {
                $responseObject->id = $responseObject->ID;
            }

            if (isset($responseObject->title)) {
                $responseObject->title = html_entity_decode($responseObject->title);
                $responseObject->title = strip_tags($responseObject->title);
            }

            $newArray[] = $responseObject;

        }

        return $newArray;
    }

    /**
     * Formats JSON data to objects
     *
     * @param $json
     * @return mixed
     */
    private function formatJsonToObjects($json)
    {
        return json_decode($json);
    }

    /**
     * Returns json from endpoint
     *
     * @return mixed
     * @throws \Exception
     */
    private function callEndpoint($specificIdList = null)
    {
        $result = [];

        $client = $this->getClient();

        $templateId = $this->objectType;
        $templateListResponse = $client->get('/templates/'. $templateId);
        $templateResponse = json_decode($templateListResponse->getBody(), true)['data'];

        $projectId = $templateResponse['project_id'];

        $itemListResponse = $client->get('/items', [
            'query' => [
                'project_id' => $projectId,
            ]
        ]);

        $items = json_decode($itemListResponse->getBody(), true)['data'];

        $batching = true;

        if ($specificIdList !== null) {
            $batching = false;
        }

        $items = $this->getProcessingItems($items, $batching, $specificIdList);

        $promises = array_map(function ($item) use ($client) {
            return $client->getAsync('/items/' . $item['id']);
        }, $items);

        $promiseResponses = settle($promises)->wait();

        $encodedItemResponses = array_column($promiseResponses, 'value');

        $itemsData = array_map(function ($response) {
            return json_decode($response->getBody(), true)['data'];
        }, $encodedItemResponses);

        foreach ($itemsData as $key => $item) {

            if ($item['template_id'] != $templateId) {
                continue;
            }

            $itemResult = [
                'id' => $item['id'],
                'title' => $item['name'],
            ];

            $itemFiles = $this->getFiles($itemResult['id']);

//            $contentConfig = GatherContent::arrayAdvancedSearch($item['config'], 'label', $this->getContentTabName())[0]['elements'];
//            $metaDataConfig = arrayAdvancedSearch($item['config'], 'label', 'Meta data')[0]['elements'];

            foreach ($item['config'] as $tab) {

                if (!array_key_exists('elements', $tab)) {
                    continue;
                }

                foreach ($tab['elements'] as $content) {

                    $value = null;

                    if (array_key_exists('value', $content)) {
                        $value = $content['value'];
                    }

                    if ($content['type'] == 'files') {

                        if (array_key_exists($content['name'], $itemFiles)) {
                            $value = $itemFiles[$content['name']];
                        }
                    }

                    if (in_array($content['type'], ['choice_radio'])) {

                        $options = $content['options'];

                        foreach ($options as $option) {
                            if ($option['selected'] === true) {
                                $value = $option['label'];
                            }
                        }
                    }

                    if (in_array($content['type'], ['choice_checkbox'])) {

                        $value = [];

                        $options = $content['options'];

                        foreach ($options as $option) {
                            if ($option['selected'] === true) {
                                $value[] = $option['label'];
                            }
                        }
                    }

                    $itemResult[$content['name']] = $value;
                }
            }

            $result[] = $itemResult;
        }

        return json_encode($result);
    }

    private function getFiles($itemName)
    {
        $result = [];
        $client = $this->getClient();
        $filesResponse = $client->get('/items/'.$itemName.'/files');
        $files = json_decode($filesResponse->getBody(), true)['data'];

        foreach ($files as $file) {
            $result[$file['field']][] = [
                'url' => $file['url'],
                'filename' => $file['filename'],
            ];
        }

        return $result;
    }

    /**
     * Gets feed call URL
     *
     * @return string
     * @throws \Exception
     */
    private function getCallUrl()
    {

        if (!$this->feedUrl) {
            throw new \Exception('feedUrl is not set');
        }

        if (!$this->endpoint) {
            throw new \Exception('endpoint is not set');
        }

        $pageNumber = $this->pageNumber;

        if ($this->endpoint == "series") {
            $this->endpoint = 'article-series';
            $pageNumber = $this->pageNumber * 100;
        } else {
            $this->validatePageNumber($this->pageNumber);
        }

        return $this->feedUrl . '/' . $this->endpoint . '/' . $pageNumber;
    }

    private function validatePageNumber($number)
    {
        return true;
    }

    /**
     * Runs all validations for feed request
     *
     * @return bool
     * @throws \Exception
     */
    private function runAllValidations()
    {
        if (!$this->validateObjectType() ||
            !$this->validateReturnType() ||
            !$this->validateConfigFile()
        ) {
            return false;
        }

        return true;
    }

    private function createClient($username, $apiKey)
    {
        $craftVersion = Craft::$app->getVersion();
        $pluginVersion = Gathercontent::$plugin->getVersion();

        $userAgent = 'Integration-CraftCMS-'.$craftVersion.'/'.$pluginVersion;

        $client = new Client([
            'base_uri' => 'https://api.gathercontent.com',
            'headers' => [
                'Accept' => 'application/vnd.gathercontent.v0.5+json',
                'User-Agent' => $userAgent,
            ],
            'auth' => [
                $username,
                $apiKey
            ]
        ]);

        return $client;
    }

    private function getClient()
    {
        $username = $this->settings->username;
        $apiKey = $this->settings->apiKey;

        $client = $this->createClient($username, $apiKey);

        return $client;
    }

    private function getClientManually($username, $apiKey)
    {
        $client = $this->createClient($username, $apiKey);

        return $client;
    }

    public function validateSettingsUser()
    {
        return $this->validateUser($this->settings->username, $this->settings->apiKey);
    }

    private function getProcessingItems($items, $batching = true, $specificIdList = null)
    {
        $this->finishedMigration = false;
        $items = $this->getTemplateItems($items);
        $result = [];
        $items = array_reverse($items);

        if ($specificIdList !== null) {
            foreach ($items as $key => $item) {
                if (!in_array($item['id'], $specificIdList)) {
                    unset($items[$key]);
                }
            }
        }

        if (!$batching) {
            $this->finishedMigration = true;
            $this->lastOffset = 0;
            return $items;
        }

        if (!is_numeric($this->lastOffset)) {
            return $items;
        }

        if ($this->lastOffset == 0) {
            $result = array_slice($items, 0, $this->limit);

            if (end($result) == end($items)) {
                $this->finishedMigration = true;
            }
        } else {
            $key = 0;

            foreach ($items as $key => $item) {
                if ($item['id'] < $this->lastOffset) {
                    break;
                }
            }

            $oldItems = array_slice($items, $key, $this->limit);
            $result = array_merge($oldItems, $result);
            $resultCount = count($result);
            $moreNeededItems = $this->limit - $resultCount;

            if (end($oldItems) == end($items)) {
                $this->finishedMigration = true;
            }
        }

        if ($this->finishedMigration) {
            $this->newOffset = 0;
        } else {
            $lastItem = end($result);
            $this->newOffset = $lastItem['id'];
        }

        return $result;
    }

    private function getTemplateItems($items, $templateId = null)
    {
        if ($templateId === null) {
            $templateId = $this->objectType;
        }

        $result = [];

        foreach ($items as $item) {
            if ($item['template_id'] == $templateId) {
                $result[] = $item;
            }
        }

        return $result;
    }

    public function validateElementType($elementName, $fieldHandle, $templateId)
    {
        $result = [
            'success' => true,
            'error' => false,
        ];

        $elements = $this->getElementsByTemplateId($templateId, true, true);

        if (!array_key_exists($elementName, $elements)) {
            $result['error'] = 'Field Type Not Found';
            $result['success'] = false;
        }

        $elementInfo = $elements[$elementName];

        $fieldType = $this->getFieldType($fieldHandle);

        if (!$fieldType) {
            $result['error'] = 'Field Type Not Found';
            $result['success'] = false;
        }

        if (!array_key_exists('type', $elementInfo)) {
            $result['error'] = 'Type for Element "' . $elementInfo['label'] . '" not found';
            $result['success'] = false;
        }

        $compatable = $this->isElementTypeAndFieldTypeCompatable($elementInfo['type'], $fieldType);

        if (!$compatable) {
            $fieldModel = new $fieldType();
            $fieldTypeName = $fieldModel::displayName();
            $result['error'] = 'Cannot be mapped with Field Type ' . $fieldTypeName;
            $result['success'] = false;
        }

        return $result;
    }

    public function validateElementTypeFast($elementInfo, $fieldModel)
    {
        $result = [
            'success' => true,
            'error' => false,
        ];


        $fieldType = $fieldModel::className();

        if (!$fieldType) {
            $result['error'] = 'Field Type Not Found';
            $result['success'] = false;
        }

        if (!array_key_exists('type', $elementInfo)) {
            $result['error'] = 'Type for Element "' . $elementInfo['label'] . '" not found';
            $result['success'] = false;
        }

        $compatable = $this->isElementTypeAndFieldTypeCompatable($elementInfo['type'], $fieldType);

        if (!$compatable) {
            $fieldModel = new $fieldType();
            $fieldTypeName = $fieldModel::displayName();
            $result['error'] = 'Cannot be mapped with Field Type ' . $fieldTypeName;
            $result['success'] = false;
        }

        return $result;
    }

    public function isElementTypeAndFieldTypeCompatable($elementType, $fieldType)
    {
        $matchingTypes = self::TYPE_RULES;

        if (!array_key_exists($elementType, $matchingTypes)) {
            return false;
        }

        if (in_array($fieldType, $matchingTypes[$elementType])) {
            return true;
        }

        return false;
    }

    public function getFieldType($fieldHandle)
    {
        $fieldType = 'text';

        /** @var Fields $fieldService */
        $fieldService = \Craft::$app->get('fields');

        /** @var Field $fieldModel */
        $fieldModel = $fieldService->getFieldByHandle($fieldHandle);

        if (!$fieldModel) {
            return false;
        }

        return $fieldModel::className();
    }
}
