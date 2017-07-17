<?php

namespace Craft;

/**
 * @property string $defaultView
 * @property bool   $spamProtectionEnabled
 * @property string $formTemplateDirectory
 * @property string $license
 * @property string $fieldDisplayOrder
 * @property bool   $showTutorial
 */
class GatherContent_SettingsModel extends BaseModel
{
    /**
     * @param $attribute
     */
    public function folderExists($attribute)
    {
        $path         = $this->{$attribute};
        $absolutePath = $this->getAbsolutePath($path);

        if (!file_exists($absolutePath)) {
            $this->addError(
                $attribute,
                Craft::t("Directory '{directory}' does not exist", ["directory" => $absolutePath])
            );
        }
    }

    /**
     * If a form template directory has been set and it exists - return its absolute path
     *
     * @return null|string
     */
    public function getAbsoluteFormTemplateDirectory()
    {
        if ($this->formTemplateDirectory) {
            $absolutePath = $this->getAbsolutePath($this->formTemplateDirectory);

            return file_exists($absolutePath) ? $absolutePath : null;
        }

        return null;
    }

    /**
     * Gets the demo template content
     *
     * @param string $name
     *
     * @return string
     * @throws FreeformException
     */
    public function getDemoTemplateContent($name = "flexbox")
    {
        $path = CRAFT_PLUGINS_PATH . "freeform/templates/_defaultFormTemplates/$name.html";
        if (!file_exists($path)) {
            throw new Exception(
                Craft::t("Could not get demo template content. Please contact Solspace.")
            );
        }

        return file_get_contents($path);
    }

    /**
     * @return array|bool
     */
    public function listTemplatesInFormTemplateDirectory()
    {
        $templateDirectoryPath = $this->getAbsoluteFormTemplateDirectory();

        if (!$templateDirectoryPath) {
            return [];
        }

        $files = [];
        foreach (IOHelper::getFiles($templateDirectoryPath) as $file) {
            if (@is_dir($file)) {
                continue;
            }

            $files[$file] = pathinfo($file, PATHINFO_BASENAME);
        }

        return $files;
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'fastlyApiKey' => array(AttributeType::String, 'required' => false),
            'serviceId' => array(AttributeType::String, 'required' => false),
            "license"               => [AttributeType::String, "default" => null],
        ];
    }

    /**
     * @param string $path
     *
     * @return string
     */
    private function getAbsolutePath($path)
    {
        $isAbsolute = $this->isFolderAbsolute($path);

        return $isAbsolute ? $path : (CRAFT_BASE_PATH . $path);
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    private function isFolderAbsolute($path)
    {
        return preg_match("/^(?:\/|\\\\|\w\:\\\\).*$/", $path);
    }
}
