<?php

namespace gathercontent\gathercontent\controllers;

use gathercontent\gathercontent\Gathercontent;
use Craft;
use craft\web\Controller;

class SettingsController extends Controller
{
    /**
     * Make sure this controller requires a logged in member
     */
    public function init()
    {
        $this->requireLogin();
    }

    /**
     * Redirects to the default selected view
     */
    public function actionDefaultView()
    {
        $this->redirect("fastly/settings/general");
    }

    /**
     * Attempt cloning a demo template into the user's specified template directory
     */
    public function actionAddDemoTemplate()
    {
        PermissionsHelper::requirePermission(PermissionsHelper::PERMISSION_SETTINGS_ACCESS);

        $this->requirePostRequest();

        $errors    = [];
        $settings  = $this->getSettingsModel();
        $extension = ".html";

        $templateDirectory = $settings->getAbsoluteFormTemplateDirectory();
        $templateName      = craft()->request->getPost("templateName", null);

        if (!$templateDirectory) {
            $errors[] = Craft::t("No custom template directory specified in settings");
        } else {
            if ($templateName) {
                $templateName = StringHelper::toSnakeCase($templateName);

                $templatePath = $templateDirectory . "/" . $templateName . $extension;
                if (file_exists($templatePath)) {
                    $errors[] = Craft::t("Template '{name}' already exists", ["name" => $templateName . $extension]);
                } else {
                    try {
                        IOHelper::writeToFile($templatePath, $settings->getDemoTemplateContent());
                    } catch (FreeformException $exception) {
                        $errors[] = $exception->getMessage();
                    }
                }
            } else {
                $errors[] = Craft::t("No template name specified");
            }
        }

        $this->returnJson(
            [
                "templateName" => $templateName,
                "errors"       => $errors,
            ]
        );
    }

    /**
     * Renders the License settings page template
     */
    public function actionLicense()
    {
        $this->provideTemplate('license');
    }

    /**
     * Renders the General settings page template
     */
    public function actionGeneral()
    {
        $this->provideTemplate('general');
    }

    /**
     * Renders the General settings page template
     */
    public function actionFormattingTemplates()
    {
        craft()->templates->includeCssResource("freeform/css/code-pack.css");

        $this->provideTemplate('formatting_templates');
    }

    /**
     * @throws HttpException
     */
    public function actionSaveSettings()
    {
        $this->requirePostRequest();
        $postData = craft()->request->getPost('settings', []);

        $plugin = craft()->plugins->getPlugin('fastly');
        if (craft()->plugins->savePluginSettings($plugin, $postData)) {
            craft()->userSession->setNotice(Craft::t("Settings Saved"));
            $this->redirectToPostedUrl();
        } else {
            craft()->userSession->setError(Craft::t("Settings not saved"));
        }
    }

    /**
     * Determines which template has to be rendered based on $template
     * Adds a Freeform_SettingsModel to template variables
     *
     * @param string $template
     *
     * @throws HttpException
     */
    private function provideTemplate($template)
    {
        $this->renderTemplate(
            'fastly/settings/_' . $template,
            [
                'settings' => $this->getSettingsModel(),
            ]
        );
    }

    /**
     * @return Freeform_SettingsModel
     */
    private function getSettingsModel()
    {
        /** @var Fastly_SettingsService $settingsService */
        $settingsService = craft()->fastly_settings;

        return $settingsService->getSettingsModel();
    }
}
