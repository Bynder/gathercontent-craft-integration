<?php

namespace gathercontent\gathercontent\controllers;

use craft\helpers\UrlHelper;
use craft\models\Section;
use craft\records\Field;
use craft\services\Fields;
use craft\services\Sections;
use gathercontent\gathercontent\assetbundles\Gathercontent\GathercontentAsset;
use gathercontent\gathercontent\Gathercontent;
use Craft;
use craft\web\Controller;
use gathercontent\gathercontent\models\MappingForm;
use gathercontent\gathercontent\models\MappingModel;
use gathercontent\gathercontent\records\MappingRecord;
use gathercontent\gathercontent\records\MigrationRecord;
use gathercontent\gathercontent\services\GatherContent_DatabaseMigrationService;
use gathercontent\gathercontent\services\GatherContent_GatherContentService;
use gathercontent\gathercontent\services\GatherContent_MappingService;
use gathercontent\gathercontent\services\PageService;

class MappingController extends Controller
{
    const MAX_ITEMS_COUNT = 1000;

    /** @var GatherContent_GatherContentService $gatherContentService */
    private $gatherContentService;
    private $isValidApiUser;

    public function init()
    {
        $this->gatherContentService = Gathercontent::$plugin->gatherContent_gatherContent;
        $this->isValidApiUser = $this->gatherContentService->validateSettingsUser();

        parent::init();
    }

    public function actionIndex()
    {
        $templateItemsCounts = [];

        /** @var PageService $pageService */
        $pageService = Gathercontent::$plugin->page;
        $pageService->setSpecialProperty('entryTypeName', 'entryType', 'name');
        $pageService->start(
            1000,
            null,
            PageService::DATA_TYPE__RECORD,
            'gatherContent',
            [],
            MappingRecord::className()
        );

        $mappings = $pageService->filteredData;


        foreach ($mappings as $mapping) {
            $templateItemsCounts[$mapping->gatherContentTemplateId] = $this->gatherContentService->getTemplateItemsCount($mapping->gatherContentTemplateId, $mapping->gatherContentAccountId);
        }

        $this->renderTemplate(
            "gathercontent/mapping",
            [
                "sortUrls" => $pageService->sortUrls,
                "mappings" => $mappings,
                "templateItemsCounts" => $templateItemsCounts,
                'domain' => Craft::$app->request->getServerName(),
                'isValidApiUser' => $this->isValidApiUser,
            ]
        );
    }

    public function actionTemplate($templateId)
    {
        $route = 'gatherContent/mapping/template';
        $maxCount = self::MAX_ITEMS_COUNT;

        $mapping = MappingRecord::find()->andWhere(['gatherContentTemplateId' => $templateId])->one();
        $template = $this->gatherContentService->getTemplateDetails($mapping->gatherContentTemplateId, $mapping->gatherContentAccountId);

        /** @var PageService $pageService */
        $pageService = Gathercontent::$plugin->page;
        $pageService->start(
            $maxCount,
            $template['items'],
            PageService::DATA_TYPE__ARRAY,
            $route,
            ['templateId' => $templateId]
        );

        $nextPage = false;
        $previousPage = false;

        $exceededMaximum = false;

        if ($pageService->getNextPageUrl()) {
            $exceededMaximum = true;
        }

        $this->renderTemplate(
            "gathercontent/mapping/newdetails",
            [
                "searchParameters" => $pageService->searchParameters,
                "sortUrls" => $pageService->sortUrls,
                "route" => $route,
                "title" => 'Template: ' . $template['info']['name'],
                "maxCount" => $maxCount,
                "exceededMaximum" => $exceededMaximum,
                "template" => $template,
                "mapping" => $mapping,
                "items" => $pageService->filteredData,
                'domain' => Craft::$app->request->getServerName(),
                'isValidApiUser' => $this->isValidApiUser,
                'previousPage' => $previousPage,
                'nextPage' => $nextPage,
                'templateId' => $templateId,
            ]
        );
    }

    public function actionIntegrateItems($templateId)
    {
        $result = [
            'redirect' => true,
            'migrationId' => null,
        ];
        $itemsList = $this->getItemsList();

        if ($itemsList){
            /** @var GatherContent_DatabaseMigrationService $dbService */
            $dbService = Gathercontent::$plugin->gatherContent_databaseMigration;
            $migrationResult = $dbService->initMigration([$templateId], null, $itemsList);

            if ($migrationResult['success'] === true) {
                $result['migrationId'] = $migrationResult['migrationId'];
            } else {
                Craft::$app->session->setError('Something went wrong: ' . print_r($result['error'], true));
            }
        } else {
            $result['redirect'] = false;
        }

        return $this->asJson($result);
    }

    private function getItemsList()
    {
        $post = Craft::$app->request->post();

        if (!$post) {
            return false;
        }

        if (!array_key_exists('items', $post) || empty($post['items'])) {
            $result['error']['message'] = 'We did not receive any items';
            return false;
        }

        $itemsList = [];
        $items = $post['items'];

        if (!$items) {
            return false;
        }

        foreach ($items as $itemId => $migrate) {
            if ($migrate == 1) {
                $itemsList[] = $itemId;
            }
        }

        if (count($itemsList) <= 0) {
            return false;
        }

        return $itemsList;
    }

    public function actionMigrationFinished($migrationId)
    {
        $migration = MigrationRecord::find()->andWhere(['id' => $migrationId])->orderBy(['id' => SORT_DESC])->one();

        $items = $migration->items;

        $this->renderTemplate(
            "gathercontent/mapping/migration",
            [
                "title" => 'Migrated ' . count($items) . ' items',
                "migration" => $migration,
                "items" => $items,
                'domain' => Craft::$app->request->getServerName(),
                'isValidApiUser' => $this->isValidApiUser,
            ]
        );
    }

    public function actionEdit($mappingId = null)
    {
        $mapping = null;

        $elementErrors = [];
        $accountOptions = [];
        $projectOptions = [];
        $sectionOptions = [];
        $templateOptions = [];
        $entryTypeOptions = [];
        $fieldOptions = [];
        $tabOptions = [];
        $craftStatuses = $this->getCraftStatusOptions();

        $mainValidation = true;
        $isEdit = false;
        $form = new MappingForm();
        $post = Craft::$app->request->post();

        if ($mappingId !== null || $post) {

            if ($mappingId !== null){
                $mapping = MappingRecord::find()->andWhere(['id' => $mappingId])->one();

                if ($mapping) {
                    $isEdit = true;
                    $form = $form->populate($mapping);
                }
            }

            if ($post) {

                $form->populateWithPost($post);

                if ($form->validate() ) {
                    if ($form->validateElements()) {

                        if ($isEdit) {
                            $success = $form->update($mappingId);
                        } else {
                            $success = $form->save();
                        }

                        if ($success) {
                            Craft::$app->session->setNotice('Mapping Saved');
                            return $this->redirect(UrlHelper::cpUrl('gatherContent/mapping/index'));
                        }
                    }
                } else {
                    $mainValidation = false;
                }

                if (!empty($form->elementErrors)) {
                    $elementErrors = $form->elementErrors;
                    Craft::$app->session->setError('Validation Errors');
                } else {
                    Craft::$app->session->setError('Validation Errors');
                }

                if (!empty($form->globalError)) {
                    Craft::$app->session->setError($form->globalError);
                } else {
                    Craft::$app->session->setError('Validation Errors');
                }
            }

            $isEdit = true;

            // Get Form Options
            $accountOptions = $this->getAccountOptions();
            $sectionOptions = $this->getSectionOptions();
            $projectOptions = $this->getProjectsOptions($form->gatherContentAccountId);
            $templateOptions = $this->getTemplateOptions($form->gatherContentProjectId, $form->gatherContentTemplateId);
            $entryTypeOptions = $this->getEntryTypeOptions($form->craftSectionId, $form->craftEntryTypeId);
            $tabOptions = $this->getTabOptions($form->gatherContentTemplateId, $form->craftEntryTypeId);
        }


        if (!$mapping) {
            $title = 'Create A New Mapping';
        } else {
            $title = 'Edit ' . $mapping->gatherContentTemplateName;
        }


        $this->renderTemplate("gatherContent/mapping/edit", [
            'craftStatuses' => $craftStatuses,
            'mainValidation' => $mainValidation,
            'elementErrors' => $elementErrors,
            'mappingId' => $mappingId,
            'tabOptions' => $tabOptions,
            'fieldOptions' => $fieldOptions,
            'entryTypeOptions' => $entryTypeOptions,
            'templateOptions' => $templateOptions,
            'sectionOptions' => $sectionOptions,
            'projectOptions' => $projectOptions,
            'accountOptions' => $accountOptions,
            'form' => $form,
            'title' => $title,
            'isEdit' => $isEdit,
        ]);
    }

    private function getDefaultOptions($label,  $value = '')
    {
        $options = [];
        $default = [
            'value' => $value,
            'label' => $label,
        ];

        $options[] = $default;

        return $options;
    }

    private function getAccountOptions()
    {
        $accountOptions = $this->getDefaultOptions('Select GatherContent Account');
        $accounts = $this->gatherContentService->getAccounts();

        foreach ($accounts as $key => $account) {

            $option = [
                'value' => $account['id'],
                'label' => $account['name'],
            ];

            $accountOptions[] = $option;
        }

        return $accountOptions;
    }


    private function getCraftStatusOptions()
    {
        $options = [];

        $statuses = MappingModel::getCraftStatuses();

        foreach ($statuses as $value => $name) {

            $option = [
                'value' => $value,
                'label' => $name,
            ];

            $options[] = $option;
        }

        return $options;
    }

    private function getProjectsOptions($acountId)
    {
        $options = $this->getDefaultOptions('Select Gather Project');

        if (!$acountId) {
            return $options;
        }

        $rows = $this->gatherContentService->getProjects($acountId);
        foreach ($rows as $key => $row) {
            $option = [
                'value' => $row['id'],
                'label' => $row['name'],
            ];

            $options[] = $option;
        }

        return $options;
    }

    private function getSectionOptions()
    {
        $options = $this->getDefaultOptions('Select Craft Section');

        /** @var Sections $sectionsService */
        $sectionsService = Craft::$app->get("sections");
        $rows = $sectionsService->getAllSections();

        foreach ($rows as $key => $row) {
            /** @var Section $row */
            $option = [
                'value' => $row->id,
                'label' => $row->name,
            ];

            $options[] = $option;
        }

        return $options;
    }

    private function getTemplateOptions($projectId, $templateId = null)
    {
        $options = $this->getDefaultOptions('Select GatherContent Template');

        if (!$projectId) {
            return $options;
        }

        /** @var GatherContent_MappingService $mappingService */
        $mappingService = Gathercontent::$plugin->gatherContent_mapping;

        $rows = $this->gatherContentService->getTemplatesByProjectId($projectId);
        $notUsedTempaltes = $mappingService->notUsedTemplates($rows, true, $templateId);

        foreach ($notUsedTempaltes as $key => $row) {
            $option = [
                'value' => $row['id'],
                'label' => $row['name'],
                'disabled' => $row['used'],
            ];

            $options[] = $option;
        }

        return $options;
    }

    private function getEntryTypeOptions($sectionId, $entryTypeId = null)
    {
        $options = $this->getDefaultOptions('Select Craft Entry Type');

        if (!$sectionId) {
            return $options;
        }

        /** @var GatherContent_MappingService $mappingService */
        $mappingService = GatherContent::$plugin->gatherContent_mapping;

        /** @var Sections $sectionsService */
        $sectionsService = Craft::$app->get("sections");
        $section = $sectionsService->getSectionById($sectionId);

        if ($section) {
            $entryTypes = $section->getEntryTypes();
            $notUsedEntryTypes = $mappingService->notUsedEntryTypes($entryTypes, true, $entryTypeId);

            if ($notUsedEntryTypes) {
                foreach ($notUsedEntryTypes as $key => $entryType) {
                    $option = [
                        'value' => $entryType['id'],
                        'label' => $entryType['name'],
                        'disabled' => $entryType['used'],
                    ];

                    $options[] = $option;
                }
            }
        }

        return $options;
    }

    private function getTabOptions($templateId, $entryTypeId)
    {
        $result = [];

        if (!$templateId || !$entryTypeId) {
            return $result;
        }

        /** @var GatherContent_GatherContentService $gatherContentService */
        $gatherContentService = $this->gatherContentService;
        $tabs = $gatherContentService->getElementsByTemplateId($templateId, true);

        foreach ($tabs as $tabKey => $tab) {
            foreach ($tab['elements'] as $elementKey => $element) {
                $tabs[$tabKey]['elements'][$elementKey]['fields'] = $this->getFieldOptions($templateId, $entryTypeId, $element);
            }
        }

        return $tabs;
    }

    private function getFieldOptions($templateId, $entryTypeId, $elementInfo)
    {
        /** @var GatherContent_GatherContentService $gatherContentService */
        $gatherContentService = GatherContent::$plugin->gatherContent_gatherContent;

        $options = [];

        if (!$entryTypeId || !$templateId || !$elementInfo) {
            return $options;
        }

        /** @var Fields $fieldsService */
        $fieldsService = Craft::$app->get("fields");

        /** @var Sections $sectionsService */
        $sectionsService = Craft::$app->get("sections");
        $entryType = $sectionsService->getEntryTypeById($entryTypeId);

        if ($entryType) {
            $fields = $fieldsService->getFieldsByLayoutId($entryType->fieldLayoutId);

            if (!empty($fields)) {
                foreach ($fields as $key => $field) {

                    /** @var Field $result */
                    $valid = $gatherContentService->validateElementTypeFast($elementInfo, $field);

                    if ($valid['success'] === true) {
                        $options[$key]['value'] = $field->handle;
                        $options[$key]['label'] = $field->name;
                    }
                }
            }
        }

        return $options;
    }

    public function actionSave()
    {
        $post = Craft::$app->request->post();

        $form = MappingForm::create();
        $form->setAttributes($post, false); // TODO: SET SAFE VALUES FOR FORM AND TURN SAFEONLY ON

        if ($form->validate() && $form->validateElementTypes() && $form->save()) {
            Craft::$app->session->setFlash('Saved', true);

            return $this->redirect(UrlHelper::cpUrl('gatherContent/mapping/index'));
        }

        if (!empty($form->elementErrors)) {
            Craft::$app->session->setError(print_r($form->elementErrors, true));
        } else {
            Craft::$app->session->setError('Validation Errors');
        }

        return $this->renderTemplate("gatherContent/mapping/edit", [
            'form' => $form,
            'title' => 'Save',
        ]);
    }

    public function actionDelete($mappingId)
    {
        $mappingRecord = MappingRecord::findOne(['id' => $mappingId]);

        if ($mappingRecord) {

            $mappingRecord->deactive = true;

            if ($mappingRecord->save()) {
                Craft::$app->session->setNotice('Deactivated');

                return $this->redirect(UrlHelper::cpUrl('gatherContent/mapping/index'));
            }
        }

        Craft::$app->session->setError('Something went wrong');

        return $this->redirect(UrlHelper::cpUrl('gatherContent/mapping/index'));
    }

    public function actionActivate($mappingId)
    {
        $mappingRecord = MappingRecord::findOne(['id' => $mappingId]);

        if ($mappingRecord) {

            $mappingRecord->deactive = false;

            if ($mappingRecord->save()) {
                Craft::$app->session->setNotice('Activated');

                return $this->redirect(UrlHelper::cpUrl('gatherContent/mapping/index'));
            }
        }

        Craft::$app->session->setError('Something went wrong');

        return $this->redirect(UrlHelper::cpUrl('gatherContent/mapping/index'));
    }
}
