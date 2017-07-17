<?php
/**
 * gathercontent plugin for Craft CMS 3.x
 *
 * gathercontent
 *
 * @link      http://solspace.com
 * @copyright Copyright (c) 2017 Solspace
 */

namespace gathercontent\gathercontent\models;

use gathercontent\gathercontent\Gathercontent;

use Craft;
use craft\base\Model;

/**
 * Gathercontent Settings Model
 *
 * This is a model used to define the plugin's settings.
 *
 * Models are containers for data. Just about every time information is passed
 * between services, controllers, and templates in Craft, it’s passed via a model.
 *
 * https://craftcms.com/docs/plugins/models
 *
 * @author    Solspace
 * @package   Gathercontent
 * @since     1
 */
class Settings extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * Some field model attribute
     *
     * @var string
     */
    public $username;
    public $apiKey;
    public $volumeHandle;

    // Public Methods
    // =========================================================================


    public function rules(): array
    {
        return [
            [['username', 'apiKey', 'volumeHandle'], 'required'],
            [['username', 'apiKey'], 'validCredentials'],
        ];
    }

    public function validCredentials()
    {
        $success = Gathercontent::$plugin->gatherContent_gatherContent->validateUser($this->username, $this->apiKey);

        if (!$success) {
            $this->addError(
                'username',
                'Username or Api Key is not valid'
            );

            $this->addError(
                'apiKey',
                'Username or Api Key is not valid'
            );
        }
    }
}
