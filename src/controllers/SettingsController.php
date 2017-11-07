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
        $this->redirect("gathercontent/settings/general");
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
     * @throws HttpException
     */
    public function actionSaveSettings()
    {
        $this->requirePostRequest();
        $postData = craft()->request->getPost('settings', []);

        $plugin = craft()->plugins->getPlugin('gathercontent');
        if (craft()->plugins->savePluginSettings($plugin, $postData)) {
            craft()->userSession->setNotice(Craft::t("Settings Saved"));
            $this->redirectToPostedUrl();
        } else {
            craft()->userSession->setError(Craft::t("Settings not saved"));
        }
    }

    private function provideTemplate($template)
    {
        $this->renderTemplate(
            'gathercontent/settings/_' . $template,
            [
                'settings' => $this->getSettingsModel(),
            ]
        );
    }

    private function getSettingsModel()
    {
        $settingsService = craft()->gathercontent_settings;

        return $settingsService->getSettingsModel();
    }
}
