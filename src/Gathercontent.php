<?php
/**
 * gathercontent plugin for Craft CMS 3.x
 *
 * gathercontent
 *
 * @link      http://solspace.com
 * @copyright Copyright (c) 2017 Solspace
 */

namespace gathercontent\gathercontent;

use craft\helpers\Db;
use gathercontent\gathercontent\services\GatherContent_AssetService;
use gathercontent\gathercontent\services\GatherContent_ConfigService;
use gathercontent\gathercontent\services\GatherContent_DatabaseMigrationService;
use gathercontent\gathercontent\services\GatherContent_GatherContentService;
use gathercontent\gathercontent\services\GatherContent_MainService;
use gathercontent\gathercontent\services\GatherContent_MappingService;
use gathercontent\gathercontent\services\GatherContent_MigrationService;
use gathercontent\gathercontent\services\LoggerService;
use gathercontent\gathercontent\services\PageService;
use gathercontent\gathercontent\services\Test as TestService;
use gathercontent\gathercontent\services\ToolsService;
use gathercontent\gathercontent\variables\GathercontentVariable;
use gathercontent\gathercontent\twigextensions\GathercontentTwigExtension;
use gathercontent\gathercontent\models\Settings;
use gathercontent\gathercontent\elements\Test as TestElement;
use gathercontent\gathercontent\fields\Test as TestField;
use gathercontent\gathercontent\utilities\Test as TestUtility;
use gathercontent\gathercontent\widgets\Test as TestWidget;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\console\Application as ConsoleApplication;
use craft\web\UrlManager;
use craft\services\Elements;
use craft\services\Fields;
use craft\services\Utilities;
use craft\services\Dashboard;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;

use yii\base\Event;

/**
 * Craft plugins are very much like little applications in and of themselves. We’ve made
 * it as simple as we can, but the training wheels are off. A little prior knowledge is
 * going to be required to write a plugin.
 *
 * For the purposes of the plugin docs, we’re going to assume that you know PHP and SQL,
 * as well as some semi-advanced concepts like object-oriented programming and PHP namespaces.
 *
 * https://craftcms.com/docs/plugins/introduction
 *
 * @author    Solspace
 * @package   Gathercontent
 * @since     1
 *
 * @property  TestService $test
 */
class Gathercontent extends Plugin
{
	public $changelogUrl = 'https://github.com/gathercontent/craft-integration/blob/master/CHANGELOG.md';
	
    // Static Properties
    // =========================================================================

    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * Gathercontent::$plugin
     *
     * @var Gathercontent
     */
    public static $plugin;

    // Public Methods
    // =========================================================================

    /**
     * Set our $plugin static property to this class so that it can be accessed via
     * Gathercontent::$plugin
     *
     * Called after the plugin class is instantiated; do any one-time initialization
     * here such as hooks and events.
     *
     * If you have a '/vendor/autoload.php' file, it will be loaded for you automatically;
     * you do not need to load it in your init() method.
     *
     */
    public function init()
    {
        try {
            parent::init();
            $this->name = "GatherContent";
            self::$plugin = $this;

            $this->setComponents([
                'gatherContent_config' => GatherContent_ConfigService::class,
                'gatherContent_databaseMigration' => GatherContent_DatabaseMigrationService::class,
                'gatherContent_gatherContent' => GatherContent_GatherContentService::class,
                'gatherContent_migration' => GatherContent_MigrationService::class,
                'gatherContent_mapping' => GatherContent_MappingService::class,
                'gatherContent_asset' => GatherContent_AssetService::class,
                'gatherContent_main' => GatherContent_MainService::class,
                'logger' => LoggerService::class,
                'tools' => ToolsService::class,
                'page' => PageService::class,
            ]);

            // Add in our Twig extensions
            Craft::$app->view->twig->addExtension(new GathercontentTwigExtension());

            // Add in our console commands
            if (Craft::$app instanceof ConsoleApplication) {
                $this->controllerNamespace = 'gathercontent\gathercontent\console\controllers';
            }

            Event::on(
                UrlManager::class,
                UrlManager::EVENT_REGISTER_CP_URL_RULES,
                function (RegisterUrlRulesEvent $event) {
                    $routes       = include __DIR__ . '/routes.php';
                    $event->rules = array_merge($event->rules, $routes);
                }
            );

            // Register our elements
            Event::on(
                Elements::className(),
                Elements::EVENT_REGISTER_ELEMENT_TYPES,
                function (RegisterComponentTypesEvent $event) {
                    $event->types[] = TestElement::class;
                }
            );

            // Register our fields
            Event::on(
                Fields::className(),
                Fields::EVENT_REGISTER_FIELD_TYPES,
                function (RegisterComponentTypesEvent $event) {
                    $event->types[] = TestField::class;
                }
            );

            // Register our utilities
            Event::on(
                Utilities::className(),
                Utilities::EVENT_REGISTER_UTILITY_TYPES,
                function (RegisterComponentTypesEvent $event) {
                    $event->types[] = TestUtility::class;
                }
            );

            // Register our widgets
            Event::on(
                Dashboard::className(),
                Dashboard::EVENT_REGISTER_WIDGET_TYPES,
                function (RegisterComponentTypesEvent $event) {
                    $event->types[] = TestWidget::class;
                }
            );

            // Do something after we're installed
            Event::on(
                Plugins::className(),
                Plugins::EVENT_AFTER_INSTALL_PLUGIN,
                function (PluginEvent $event) {
                    if ($event->plugin === $this) {
                        // We were just installed
                    }
                }
            );

            /**
             * Logging in Craft involves using one of the following methods:
             *
             * Craft::trace(): record a message to trace how a piece of code runs. This is mainly for development use.
             * Craft::info(): record a message that conveys some useful information.
             * Craft::warning(): record a warning message that indicates something unexpected has happened.
             * Craft::error(): record a fatal error that should be investigated as soon as possible.
             *
             * Unless `devMode` is on, only Craft::warning() & Craft::error() will log to `craft/storage/logs/web.log`
             *
             * It's recommended that you pass in the magic constant `__METHOD__` as the second parameter, which sets
             * the category to the method (prefixed with the fully qualified class name) where the constant appears.
             *
             * To enable the Yii debug toolbar, go to your user account in the AdminCP and check the
             * [] Show the debug toolbar on the front end & [] Show the debug toolbar on the Control Panel
             *
             * http://www.yiiframework.com/doc-2.0/guide-runtime-logging.html
             */
            Craft::info(
                Craft::t(
                    'gathercontent',
                    '{name} plugin loaded',
                    ['name' => $this->name]
                ),
                __METHOD__
            );
        } catch (\Exception $exception) {
            var_dump($exception->getMessage());
            echo '<br/>';
            var_dump($exception->getTraceAsString());
            die();
        }
    }

    /**
     * Returns the component definition that should be registered on the
     * [[\craft\web\twig\variables\CraftVariable]] instance for this plugin’s handle.
     *
     * @return mixed|null The component definition to be registered.
     * It can be any of the formats supported by [[\yii\di\ServiceLocator::set()]].
     */
    public function defineTemplateComponent()
    {
        return GathercontentVariable::class;
    }

    public static function getConfig()
    {
        return require(__DIR__ . "/config.php");
    }

    // Protected Methods
    // =========================================================================

    /**
     * Creates and returns the model used to store the plugin’s settings.
     *
     * @return \craft\base\Model|null
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }

    /**
     * Returns the rendered settings HTML, which will be inserted into the content
     * block on the settings page.
     *
     * @return string The rendered settings HTML
     */
    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate(
            'gathercontent/settings',
            [
                'settings' => $this->getSettings()
            ]
        );
    }

    /**
     * Simulates Null coalescing operator
     * http://php.net/manual/en/migration70.new-features.php#migration70.new-features.null-coalesce-op
     *
     */
    public static function returnIfSet($issetValue, $notSetValue = null)
    {
        return isset($issetValue) ? $issetValue : $notSetValue;
    }

    public static function arrayAdvancedSearch($array, $key, $value)
    {
        $results = array();

        if (is_array($array)) {
            if (isset($array[$key]) && $array[$key] == $value) {
                $results[] = $array;
            }

            foreach ($array as $subarray) {
                $results = array_merge($results, self::arrayAdvancedSearch($subarray, $key, $value));
            }
        }

        return $results;
    }

    public static function getCurrentDateTime()
    {
        return Db::prepareDateForDb(new \DateTime());
    }

    public static function loadDependencies()
    {
        $autoloadLibrary = [
            'Mappings/SalesforceObjectMapping',
            'Mappings/CraftEntityMapping',
            'Repositories/RelationshipRepository',
            'Mappings/CraftFieldMapping',
        ];

        foreach ($autoloadLibrary as $path) {
            require_once __DIR__ . '/Library/'. $path. '.php';
        }
    }
}
