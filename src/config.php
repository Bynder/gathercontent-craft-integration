<?php
/**
 * gathercontent plugin for Craft CMS 3.x
 *
 * gathercontent
 *
 * @link      http://solspace.com
 * @copyright Copyright (c) 2017 Solspace
 */

/**
 * gathercontent config.php
 *
 * This file exists only as a template for the gathercontent settings.
 * It does nothing on its own.
 *
 * Don't edit this file, instead copy it to 'craft/config' as 'gathercontent.php'
 * and make your changes there to override default settings.
 *
 * Once copied to 'craft/config', this file will be multi-environment aware as
 * well, so you can have different settings groups for each environment, just as
 * you do for 'general.php'
 */

return [
//    Moved to Settings model
//    'gatherContent' => [
//        'username' => 'ivars@solspace.com',
//        'apiKey' => '3cf2e601-4a69-43cf-bfd6-509b9da65a64',
//    ],
    'migrate' => [
        'batchLimit' => 1,
        'legacyIdFieldHandle' => 'gcid',
    ]
];
