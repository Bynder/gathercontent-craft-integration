<?php

namespace gathercontent\gathercontent\services;

use yii\base\Component;

class ToolsService extends Component
{
    const DEFAULT_CATEGORY = 'tools';

    public function isConsole()
    {
        if (php_sapi_name() == 'cli') {
            return true;
        }

        return false;
    }
}
