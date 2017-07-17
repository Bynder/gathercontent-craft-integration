<?php

namespace gathercontent\gathercontent\controllers;

use craft\helpers\UrlHelper;
use craft\models\EntryType;
use craft\records\Field;
use craft\services\Fields;
use craft\services\Sections;
use gathercontent\gathercontent\Gathercontent;
use Craft;
use craft\web\Controller;
use gathercontent\gathercontent\records\MappingRecord;
use gathercontent\gathercontent\services\GatherContent_DatabaseMigrationService;
use gathercontent\gathercontent\services\GatherContent_GatherContentService;
use gathercontent\gathercontent\services\GatherContent_MappingService;
use Solspace\Freeform\Services\FieldsService;

class IntegrateController extends Controller
{
    /** @var  GatherContent_GatherContentService $gatherContentService */
    private $gatherContentService;
    
    /** @var  GatherContent_MappingService $gatherContentMappingService */
    private $gatherContentMappingService;

    /** @var  GatherContent_DatabaseMigrationService $databaseMigrationService */
    private $databaseMigrationService;

    public function init()
    {
        $this->gatherContentService = Gathercontent::$plugin->gatherContent_gatherContent;
        $this->gatherContentMappingService = Gathercontent::$plugin->gatherContent_mapping;
        $this->databaseMigrationService = Gathercontent::$plugin->gatherContent_databaseMigration;

        parent::init();
    }

    public function actionRun($templateId, $migrationId = null)
    {
        $result = [];

        try {
            $result = $this->databaseMigrationService->initMigration([$templateId], $migrationId);

            if ($result['success'] !== true) {
                header('HTTP/1.1 500 Internal Server Error');
            }

        } catch (\Exception $exception) {
            $errorMessage = $exception->getMessage() . ' | Trace: ' . $exception->getTraceAsString();
            $result['error']['message'] = $errorMessage;
            header('HTTP/1.1 500 Internal Server Error');
        }

        return $this->asJson($result);
    }

    public function actionRunItems($templateId, $migrationId = null)
    {
        $migrate = true;

        $result = [
            'success' => false,
            'finished' => false,
            'error' => [],
        ];

        $post = Craft::$app->request->post();

        if (!array_key_exists('items', $post) || empty($post['items'])) {
            $result['error']['message'] = 'We did not receive any items';
            $migrate = false;
        }

        if ($migrate) {

            $itemsList = $post['items'];

            try {
                $result = $this->databaseMigrationService->initMigration([$templateId], $migrationId, $itemsList);

                if ($result['success'] !== true) {
                    header('HTTP/1.1 500 Internal Server Error');
                }

            } catch (\Exception $exception) {
                $errorMessage = $exception->getMessage() . ' | Trace: ' . $exception->getTraceAsString();
                $result['error']['message'] = $errorMessage;
                header('HTTP/1.1 500 Internal Server Error');
            }
        }

        return $this->asJson($result);
    }

    public function actionRunOne($templateId)
    {
        $result = [];

        try {
            $migrationResult = $this->databaseMigrationService->initMigration([$templateId]);

            if (empty($migrationResult['error'])) {
                $result['success'] = true;

            } else {
                $result['error']['message'] = $migrationResult['error']['message'];
                header('HTTP/1.1 500 Internal Server Error');
            }

        } catch (\Exception $exception) {
            $errorMessage = $exception->getMessage() . ' | Trace: ' . $exception->getTraceAsString();
            $result['error']['message'] = $errorMessage;
            header('HTTP/1.1 500 Internal Server Error');
        }

        return $this->redirect(UrlHelper::cpUrl('gatherContent/mapping/index'));
    }

    public function actionGetProjects($accountId)
    {
        $result = [];
        $result['projects'] = $this->gatherContentService->getProjects($accountId);

        return $this->asJson($result);
    }

    public function actionGetAccounts()
    {
        $result = [];
        $result['accounts'] = $this->gatherContentService->getAccounts();

        return $this->asJson($result);
    }

    public function actionGetAllMappings()
    {
        $result = [
            'mappings' => [],
        ];

        $mappings = MappingRecord::find()->andWhere(['deactive' => false])->all();

        if ($mappings) {
            foreach ($mappings as $mapping) {
                $result['mappings'][] = $mapping->gatherContentTemplateId;
            }
        }

//        $result['mappings'][] = 000; For multiple mappings testing

        return $this->asJson($result);
    }

    public function actionGetSections()
    {
        $result = [];

        /** @var Sections $sectionsService */
        $sectionsService = Craft::$app->get("sections");
        $result['sections'] = $sectionsService->getAllSections();

        return $this->asJson($result);
    }

    public function actionGetTemplates($projectId)
    {
        $result = [];

        /** @var GatherContent_MappingService $mappingService */
        $mappingService = $this->gatherContentMappingService;

        /** @var GatherContent_GatherContentService $gatherContentService */
        $gatherContentService = $this->gatherContentService;
        $gatherContentTemplates = $gatherContentService->getTemplatesByProjectId($projectId);
        $notUsedTempaltes = $mappingService->notUsedTemplates($gatherContentTemplates, true);
        $result['templates'] = $notUsedTempaltes;

        return $this->asJson($result);
    }

    public function actionGetElements($templateId, $entryTypeId)
    {
        $result = [];
        /** @var GatherContent_GatherContentService $gatherContentService */
        $gatherContentService = $this->gatherContentService;
        $result['tabs'] = $gatherContentService->getElementsByTemplateId($templateId, true);

        foreach ($result['tabs'] as $tabKey => $tab) {
            foreach ($tab['elements'] as $elementKey => $element) {
                $result['tabs'][$tabKey]['elements'][$elementKey]['fields'] = $this->getFieldOptions($templateId, $entryTypeId, $element);
            }
        }

        return $this->asJson($result);
    }

    public function getFieldOptions($templateId, $entryTypeId, $element)
    {
        $result = [];

        /** @var Fields $fieldsService */
        $fieldsService = Craft::$app->get("fields");

        /** @var Sections $sectionsService */
        $sectionsService = Craft::$app->get("sections");
        $entryType = $sectionsService->getEntryTypeById($entryTypeId);

        if ($entryType) {
            $fields = $fieldsService->getFieldsByLayoutId($entryType->fieldLayoutId);

            /** @var GatherContent_GatherContentService $gatherContentService */
            $gatherContentService = GatherContent::$plugin->gatherContent_gatherContent;

            if (!empty($fields)) {
                foreach ($fields as $field) {

                    /** @var Field $result */
                    $valid = $gatherContentService->validateElementTypeFast($element, $field);

                    if ($valid['success'] === true) {
                        $result[] = $field;
                    }
                }
            }
        }

        return $result;
    }

    public function actionGetFields($entryTypeId, $templateId, $elementName)
    {
        $result = [
            'fields' => [],
        ];

        /** @var Fields $fieldsService */
        $fieldsService = Craft::$app->get("fields");

        /** @var Sections $sectionsService */
        $sectionsService = Craft::$app->get("sections");
        $entryType = $sectionsService->getEntryTypeById($entryTypeId);

        if ($entryType) {
            $fields = $fieldsService->getFieldsByLayoutId($entryType->fieldLayoutId);

            /** @var GatherContent_GatherContentService $gatherContentService */
            $gatherContentService = GatherContent::$plugin->gatherContent_gatherContent;

            if (!empty($fields)) {
                foreach ($fields as $field) {

                    /** @var Field $result */
                    $valid = $gatherContentService->validateElementType($elementName, $field->handle, $templateId);

                    if ($valid['success'] === true) {
                        $result['fields'][] = $field;
                    }
                }
            }
        }

        return $this->asJson($result);
    }

    public function actionGetEntryTypes($sectionId)
    {
        $result = [
            'entryTypes' => [],
        ];

        /** @var GatherContent_MappingService $mappingService */
        $mappingService = $this->gatherContentMappingService;
        $usedEntryTypes = $mappingService->getAllEntrTypes();

        /** @var Sections $sectionsService */
        $sectionsService = Craft::$app->get("sections");
        $section = $sectionsService->getSectionById($sectionId);

        if ($section) {
            $entryTypes = $section->getEntryTypes();
            $notUsedEntryTypes = $mappingService->notUsedEntryTypes($entryTypes, true);

            if ($entryTypes) {
                $result['entryTypes'] = $notUsedEntryTypes;
            }
        }

        return $this->asJson($result);
    }

    private function addMockTitle($result)
    {
        $mockTitleObject = new \stdClass();
        $mockTitleObject->id = 0;
        $mockTitleObject->handle = 'title';
        $mockTitleObject->name = 'Title';
        $result['fields'][] = $mockTitleObject;

        return $result;
    }

    public function actionValidateElementType($elementName, $fieldHandle, $templateId)
    {
        /** @var GatherContent_GatherContentService $gatherContentService */
        $gatherContentService = GatherContent::$plugin->gatherContent_gatherContent;
        $result = $gatherContentService->validateElementType($elementName, $fieldHandle, $templateId);

        return $this->asJson($result);
    }

    public function actionSwitchMapping($mappingId)
    {
        $mappingRecord = MappingRecord::findOne(['id' => $mappingId]);

        if ($mappingRecord) {

            if ($mappingRecord->deactive == true) {
                $mappingRecord->deactive = false;
            } else {
                $mappingRecord->deactive = true;
            }

            if ($mappingRecord->save()) {
                return $this->asJson(['success' => true, 'deactive' => $mappingRecord->deactive]);
            }
        }

        return $this->asJson(['succcess' => false]);
    }

    public function actionValidate()
    {
        $post = Craft::$app->request->post();

        if (!array_key_exists('email', $post) || !array_key_exists('key', $post)){
            return $this->asJson(['success' => false]);
        }

        /** @var GatherContent_GatherContentService $gatherContentService */
        $gatherContentService = GatherContent::$plugin->gatherContent_gatherContent;
        $isValidUser = $gatherContentService->validateUser($post['email'], $post['key']);

        return $this->asJson(['success' => $isValidUser]);
    }
}