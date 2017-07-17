<?php

namespace gathercontent\gathercontent\services;

use craft\helpers\UrlHelper;
use gathercontent\gathercontent\records\MappingRecord;
use yii\base\Component;
use yii\db\ActiveQuery;

class PageService extends Component
{
    const DATA_TYPE__ARRAY = 'array';
    const DATA_TYPE__RECORD = 'record';

    public $previousPageExists;
    public $nextPageExists;
    public $filteredData;
    public $itemsPerPage;
    public $dataClass;
    public $dataType;
    public $data;
    public $pageCount = [];
    public $searchParameters = [];
    public $postParameters = [];
    public $route;
    public $currentPage = 1;
    public $errors = [];
    public $order;
    public $nextOrder;
    public $sort;
    public $sortUrls;
    public $specialProperties = [];
    public $dataRecord;

    /** @var  ActiveQuery */
    public $filterQuery;

    public function start($itemsPerPage, $data, $dataType, $route, array $postParameters = [], $dataRecord = null)
    {
        $this->itemsPerPage = $itemsPerPage;
        $this->data = $data;
        $this->dataType = $dataType;
        $this->route = $route;
        $this->postParameters = $postParameters;
        $this->dataRecord = $dataRecord;

        $this->setSearchParameters();
        $this->setOrderParameters();
        $this->setFilteredData();
        $this->setSortUrls();

        return $this;
    }

    public function getNextPageUrl()
    {
        $nextPage = $this->getNextPage();

        if (!$nextPage) {
            return false;
        }

        return $this->getUrlForPage($nextPage);
    }

    public function getPreviousPageUrl()
    {
        $prevousPage = $this->getPreviousPage();

        if (!$prevousPage) {
            return false;
        }

        return $this->getUrlForPage($prevousPage);
    }

    public function getUrlForPage($page) {
        $url = $this->route;

        if (count($this->postParameters) > 0) {
            foreach ($this->postParameters as $key => $value) {
                $url .= '/' . $value;
            }
        }

        $pageInfo['page'] = $page;

        if (count($this->searchParameters) > 0) {

            foreach ($this->searchParameters as $parameter => $value) {
                $pageInfo['search'][$parameter] = $value;
            }
        }

        return UrlHelper::cpUrl($url, ['pageInfo' => $pageInfo]);
    }

    public function getSearchParameters()
    {
        if (count($this->searchParameters) == 0) {
            return false;
        }

        return $this->searchParameters;
    }

    public function getNextPage()
    {
        if (!$this->searchParameters) {
            if ($this->currentPage >= $this->pageCount) {
                return false;
            }
        } else {
            if (!$this->nextPageExists) {
                return false;
            }
        }

        return $this->currentPage + 1;
    }

    public function getPreviousPage()
    {
        if ($this->currentPage <= 1) {
            return false;
        }

        return $this->currentPage - 1;
    }

    public function setPageCount()
    {
        switch ($this->dataType) {
            case self::DATA_TYPE__ARRAY:
                $pageCount = $this->getPageCountFromArray();
                break;
            case self::DATA_TYPE__RECORD:
                $pageCount = $this->getPageCountFromRecords();
                break;
            default:
                $pageCount = 0;
                $this->errors[] = 'Trying to set Page Count but uses unauthorized data type: ' . $this->dataType;
                break;
        }

        $this->pageCount = $pageCount;

        return $this;
    }

    public function getPageCountFromArray()
    {
        if ($this->dataType !== self::DATA_TYPE__ARRAY) {
            $this->errors[] = 'Trying to get Page Count From Array but data type set as ' . $this->dataType;
            return false;
        }

        if (!is_array($this->filteredData)) {
            $this->errors[] = 'Trying to get Page Count From Array but data type set as ' . $this->dataType;
            return false;
        }

        $itemsCount = count($this->data);
        $itemsPerPage = $this->itemsPerPage;

        $pageCount = ceil($itemsCount / $itemsPerPage);

        return $pageCount;
    }

    public function getPageCountFromRecords()
    {
        return 0;
    }

    public function setFilteredData()
    {
        switch ($this->dataType) {
            case self::DATA_TYPE__ARRAY:
                $filteredData = $this->getFilteredDataFromArray();
                $filteredData = $this->orderData($filteredData);
                break;
            case self::DATA_TYPE__RECORD:
                $this->getFilteredDataFromRecords();
                $this->orderData();
                $filteredData = $this->getRecordData();
                break;
            default:
                $filteredData = false;
                $this->errors[] = 'Trying to get data but uses unauthorized data type: ' . $this->dataType;
                break;
        }

        $this->filteredData = $filteredData;
        $this->setPageCount();

        return $this;
    }

    public function getFilteredData()
    {
        return $this->filteredData;
    }

    public function getFilteredDataFromArray()
    {
        $filteredData = [];
        $currentPage = $this->currentPage;
        $itemsPerPage = $this->itemsPerPage;
        $data = $this->data;

        $itemsCounter = 0;
        $pageCounter = 1;

        $this->nextPageExists = false;

        foreach ($data as $item) {
            if (!$this->filteredItem($item)) {
                continue;
            }

            $itemsCounter++;

            if ($itemsCounter > $itemsPerPage) {
                $itemsCounter = 1;
                $pageCounter++;
            }

            if ($pageCounter < $currentPage) {
                continue;
            }

            if ($pageCounter > $currentPage) {
                $this->nextPageExists = true;
                break;
            }

            $filteredData[] = $item;
        }

        return $filteredData;
    }

    public function filteredItem($item)
    {
        if (count($this->searchParameters) == 0) {
            return true;
        }

        switch ($this->dataType) {
            case self::DATA_TYPE__ARRAY:
                $filteredData = $this->filteredItemFromArray($item);
                break;
            default:
                $filteredData = false;
                $this->errors[] = 'Trying to get data but uses unauthorized data type: ' . $this->dataType;
                break;
        }

        return $filteredData;
    }

    public function filteredItemFromArray($item)
    {
        foreach ($this->searchParameters as $parameter => $value) {
            if (!array_key_exists($parameter, $item)) {
                continue;
            }

            $itemValue = $item[$parameter];
            $itemValue = trim($itemValue);
            $itemValue = strtolower($itemValue);

            if(strpos($itemValue, $value) === false){
                return false;
            }
        }

        return true;
    }

    public function getFilteredDataFromRecords()
    {
        $findQuery = call_user_func($this->dataRecord.'::find');
        $this->filterQuery = $findQuery;

        return $this->filterQuery;
    }

    private function orderData($customData = null)
    {
        switch ($this->dataType) {
            case self::DATA_TYPE__ARRAY:
                return $this->orderArrayData($customData);
                break;
            case self::DATA_TYPE__RECORD:
                return $this->orderRecordData();
            default:
                $this->errors[] = 'Trying to use unknown data type: ' . $this->dataType;
                return false;
        }
    }

    public function getRecordData()
    {
        return $this->filterQuery->all();
    }

    private function orderArrayData($customData)
    {
        if (!$this->sort) {
            return $customData;
        }

        if ($this->order == 'asc') {
            usort($customData, function($a, $b) {
                return $b[$this->sort] <=> $a[$this->sort];
            });
        } else {
            usort($customData, function($a, $b) {
                return $a[$this->sort] <=> $b[$this->sort];
            });
        }

        return $customData;
    }


    private function orderRecordData()
    {
        if (!$this->sort) {
            return false;
        }

        $this->filterQuery;

        $order = SORT_DESC;

        if ($this->order == 'asc') {
            $order = SORT_ASC;
        }

        if (!array_key_exists($this->sort, $this->specialProperties)) {
            $this->filterQuery->orderBy([$this->sort => $order]);
        } else {
            $this->filterQuery
                ->joinWith($this->specialProperties[$this->sort]['relation'])
                ->orderBy([$this->specialProperties[$this->sort]['property'] => $order]);
        }

        return $this;
    }

    private function setSortUrls()
    {
        switch ($this->dataType) {
            case self::DATA_TYPE__ARRAY:
                $keys = $this->setSortArrayUrls();
                break;
            case self::DATA_TYPE__RECORD:
                $keys = $this->setSortRecordUrls();
                break;
            default:
                $this->errors[] = 'Trying to set Sort Urls but uses unauthorized data type: ' . $this->dataType;
                return false;
        }

        $pageInfo = [];

        if (count($this->searchParameters) > 0) {

            foreach ($this->searchParameters as $parameter => $value) {
                $pageInfo['search'][$parameter] = $value;
            }
        }

        $url = $this->route;

        if (count($this->postParameters) > 0) {
            foreach ($this->postParameters as $key => $value) {
                $url .= '/' . $value;
            }
        }

        foreach ($keys as $key) {
            $this->sortUrls[$key] = UrlHelper::cpUrl($url, ['sort' => $key, 'order' => $this->nextOrder, 'pageInfo' => $pageInfo]);
        }

        return true;
    }

    private function setSortArrayUrls()
    {
        if (!$this->data) {
            return false;
        }

        $keys = array_keys(array_shift($this->data));

        return $keys;
    }

    private function setSortRecordUrls()
    {
        $firstRecord = new $this->dataRecord();
        $keys = $firstRecord->attributes();

        foreach ($this->specialProperties as $propertyName => $property){
            $keys[] = $propertyName;
        }

        return $keys;
    }

    private function setOrderParameters()
    {
        $this->nextOrder = 'desc';

        $request = \Craft::$app->request;
        $get = $request->get();
        $post = $request->post();

        $getPost = array_merge($get, $post);

        if (!array_key_exists('sort', $getPost) && !array_key_exists('order', $getPost)) {
            return false;
        }

        $this->sort = $getPost['sort'];
        $this->order = $getPost['order'];

        if ($this->order == 'desc') {
            $this->nextOrder = 'asc';
        }

        return true;
    }

    private function setSearchParameters()
    {
        $request = \Craft::$app->request;
        $get = $request->get();
        $post = $request->post();

        if ($get && array_key_exists('pageInfo', $get)) {
            $getPageInfo = $get['pageInfo'];

            if (array_key_exists('page', $getPageInfo)) {
                $this->currentPage = $getPageInfo['page'];
            }

            if (array_key_exists('search', $getPageInfo)) {

                $searchParameters = $getPageInfo['search'];

                foreach ($searchParameters as $parameter => $value) {

                    $value = trim($value);
                    $value = strtolower($value);

                    if ($value == '') {
                        continue;
                    }

                    $this->searchParameters[$parameter] = trim($value);
                }
            }
        }

        if ($post && array_key_exists('pageInfo', $post)) {
            $postPageInfo = $post['pageInfo'];

            if (array_key_exists('search', $postPageInfo)) {

                $searchParameters = $postPageInfo['search'];

                foreach ($searchParameters as $parameter => $value) {

                    $value = trim($value);
                    $value = strtolower($value);

                    if ($value == '') {
                        continue;
                    }

                    $this->currentPage = 1;

                    $this->searchParameters[$parameter] = trim($value);
                }
            }
        }
    }

    public function setSpecialProperty($name, $relation, $property)
    {
        $this->specialProperties[$name]['relation'] = $relation;
        $this->specialProperties[$name]['property'] = $property;
    }
}
