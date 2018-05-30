<?php
/**
 * gathercontent plugin for Craft CMS 3.x
 *
 * gathercontent
 *
 * @link      http://solspace.com
 * @copyright Copyright (c) 2017 Solspace
 */

namespace gathercontent\gathercontent\migrations;

use gathercontent\gathercontent\Gathercontent;

use Craft;
use craft\config\DbConfig;
use craft\db\Migration;
use gathercontent\gathercontent\models\MappingModel;

/**
 * gathercontent Install Migration
 *
 * If your plugin needs to create any custom database tables when it gets installed,
 * create a migrations/ folder within your plugin folder, and save an Install.php file
 * within it using the following template:
 *
 * If you need to perform any additional actions on install/uninstall, override the
 * safeUp() and safeDown() methods.
 *
 * @author    Solspace
 * @package   Gathercontent
 * @since     1
 */
class Install extends Migration
{
    // Public Properties
    // =========================================================================

    /**
     * @var string The database driver to use
     */
    public $driver;

    // Public Methods
    // =========================================================================

    /**
     * This method contains the logic to be executed when applying this migration.
     * This method differs from [[up()]] in that the DB logic implemented here will
     * be enclosed within a DB transaction.
     * Child classes may implement this method instead of [[up()]] if the DB logic
     * needs to be within a transaction.
     *
     * @return boolean return a false value to indicate the migration fails
     * and should not proceed further. All other return values mean the migration succeeds.
     */
    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;

        foreach ($this->getTableData() as $data) {
            $options               = $data['options'] ?? null;
            $fields                = $data['fields'];
            $fields['dateCreated'] = $this->timestamp()->null();
            $fields['dateUpdated'] = $this->timestamp()->null();
            $fields['uid'] = $this->string(255);

            $this->createTable($data['table'], $fields, $options);
        }

        $this->addForeignKey(
            'fields_tabs_fk',
            '{{%gathercontent_mapping_fields}}',
            'tabId',
            '{{%gathercontent_mapping_tabs}}',
            'id',
            'SET NULL'
        );

        $this->addForeignKey(
            'tabs_mapping_fk',
            '{{%gathercontent_mapping_tabs}}',
            'mappingId',
            '{{%gathercontent_mapping}}',
            'id',
            'SET NULL'
        );

        $this->addForeignKey(
            'mapping_items_migrations_fk',
            '{{%gathercontent_migration_items}}',
            'migrationid',
            '{{%gathercontent_migrations}}',
            'id',
            'SET NULL'
        );

        $this->addForeignKey(
            'mapping_items_mapping_fk',
            '{{%gathercontent_migration_items}}',
            'mappingId',
            '{{%gathercontent_mapping}}',
            'id',
            'SET NULL'
        );

        $this->addForeignKey(
            'mapping_items_relationship_fk',
            '{{%gathercontent_migration_items}}',
            'relationshipId',
            '{{%gathercontent_relationships}}',
            'id',
            'SET NULL'
        );

        return true;
    }

    /**
     * This method contains the logic to be executed when removing this migration.
     * This method differs from [[down()]] in that the DB logic implemented here will
     * be enclosed within a DB transaction.
     * Child classes may implement this method instead of [[down()]] if the DB logic
     * needs to be within a transaction.
     *
     * @return boolean return a false value to indicate the migration fails
     * and should not proceed further. All other return values mean the migration succeeds.
     */
    public function safeDown()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
//
        $this->dropForeignKey('tabs_mapping_fk', '{{%gathercontent_mapping_tabs}}');
        $this->dropForeignKey('fields_tabs_fk', '{{%gathercontent_mapping_fields}}');
        $this->dropForeignKey('mapping_items_mapping_fk', '{{%gathercontent_migration_items}}');
        $this->dropForeignKey('mapping_items_migrations_fk', '{{%gathercontent_migration_items}}');
        $this->dropForeignKey('mapping_items_relationship_fk', '{{%gathercontent_migration_items}}');

        foreach ($this->getTableData() as $data) {
            $this->dropTableIfExists($data['table']);
        }

        return true;
    }

    /**
     * @return array
     */
    private function getTableData(): array
    {
        return [
            [
                'table'  => '{{%gathercontent_mapping}}',
                'fields' => [
                    'id' => $this->primaryKey(),
                    'craftSectionId' => $this->integer(),
                    'gatherContentTemplateId' => $this->string(255),
                    'gatherContentProjectId' => $this->string(255),
                    'craftEntryTypeId' => $this->integer(),
                    'gatherContentTemplateName' => $this->string(255),
                    'gatherContentProjectName' => $this->string(255),
                    'lastOffset' => $this->integer(),
                    'lastImportTimestamp' => $this->dateTime(),
                    'migrating' => $this->boolean(),
                    'gatherContentAccountId' => $this->integer(),
                    'deactive' => $this->boolean()->defaultValue(0),
                    'craftStatus' => $this->string()->defaultValue(MappingModel::STATUS__ENABLED),
                ],
            ],
            [
                'table'  => '{{%gathercontent_mapping_fields}}',
                'fields' => [
                    'id' => $this->primaryKey(),
                    'craftFieldHandle' => $this->string(255),
                    'gatherContentElementName' => $this->string(255),
                    'tabId' => $this->integer(),
                ],
            ],
            [
                'table'  => '{{%gathercontent_relationships}}',
                'fields' => [
                    'id' => $this->primaryKey(),
                    'craftElementId' => $this->integer(),
                    'craftElementTable' => $this->string(255),
                    'sfEntityId' => $this->string(255),
                    'sfObject' => $this->string(255),
                    'sfLastModifiedDate' => $this->dateTime(),
                ],
            ],
            [
                'table'  => '{{%gathercontent_mapping_tabs}}',
                'fields' => [
                    'id' => $this->primaryKey(),
                    'title' => $this->string(255),
                    'gatherContentTabId' => $this->string(255),
                    'mappingId' => $this->integer(),
                ],
            ],
            [
                'table'  => '{{%gathercontent_migrations}}',
                'fields' => [
                    'id' => $this->primaryKey(),
                ],
            ],
            [
                'table'  => '{{%gathercontent_migration_items}}',
                'fields' => [
                    'id' => $this->primaryKey(),
                    'mappingId' => $this->integer(),
                    'migrationId' => $this->integer(),
                    'relationshipId' => $this->integer(),
                    'status' => $this->string(255),
                    'itemId' => $this->string(255),
                ],
            ],
        ];
    }
}
