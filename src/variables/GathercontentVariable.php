<?php
/**
 * gathercontent plugin for Craft CMS 3.x
 *
 * gathercontent
 *
 * @link      http://solspace.com
 * @copyright Copyright (c) 2017 Solspace
 */

namespace gathercontent\gathercontent\variables;

use gathercontent\gathercontent\Gathercontent;

use Craft;

/**
 * gathercontent Variable
 *
 * Craft allows plugins to provide their own template variables, accessible from
 * the {{ craft }} global variable (e.g. {{ craft.gathercontent }}).
 *
 * https://craftcms.com/docs/plugins/variables
 *
 * @author    Solspace
 * @package   Gathercontent
 * @since     1
 */
class GathercontentVariable
{
    // Public Methods
    // =========================================================================

    /**
     * Whatever you want to output to a Twig tempate can go into a Variable method.
     * You can have as many variable functions as you want.  From any Twig template,
     * call it like this:
     *
     *     {{ craft.gathercontent.exampleVariable }}
     *
     * Or, if your variable requires parameters from Twig:
     *
     *     {{ craft.gathercontent.exampleVariable(twigValue) }}
     *
     * @param null $optional
     * @return string
     */
    public function exampleVariable($optional = null)
    {
        $result = "And away we go to the Twig template...";
        if ($optional) {
            $result = "I'm feeling optional today...";
        }
        return $result;
    }
}
