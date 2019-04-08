<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* This file is part of NextDom Software.
 *
 * NextDom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * NextDom Software is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with NextDom Software. If not, see <http://www.gnu.org/licenses/>.
 */

namespace {

    use NextDom\Helpers\FileSystemHelper;
    use NextDom\Managers\ConfigManager;

    define('NEXTDOM_ROOT', realpath(__DIR__ . '/..'));
    define('NEXTDOM_DATA', '/var/lib/nextdom');
    define('NEXTDOM_LOG', '/var/log/nextdom');

    if (file_exists(NEXTDOM_DATA . '/config/common.config.php')) {
        require_once NEXTDOM_DATA . '/config/common.config.php';
    }
    /**
     * Force plugin autoload last
     */
    spl_autoload_register('nextdomPluginAutoload', true, true);
    require_once NEXTDOM_ROOT . '/vendor/autoload.php';
    require_once NEXTDOM_ROOT . '/core/class/DB.class.php';
    require_once NEXTDOM_ROOT . '/src/Managers/ConfigManager.php';
    require_once NEXTDOM_DATA . '/config/nextdom.config.php';
    require_once NEXTDOM_DATA . '/config/compatibility.config.php';

    $ENABLED_PLUGINS = null;

    // Developer mode : Register global error and exception handlers
    if (php_sapi_name() != 'cli' && (ConfigManager::getDefaultConfiguration()['core']['developer::mode'] == '1') && (ConfigManager::getDefaultConfiguration()['core']['developer::errorhandler'] == '1') && (ConfigManager::getDefaultConfiguration()['core']['developer::exceptionhandler'] == '1')) {
        Symfony\Component\Debug\ErrorHandler::register();
        Symfony\Component\Debug\ExceptionHandler::register();
    }

    /**
     * Include files from plugins
     *
     * @param string $className Name of the class
     *
     * @throws Exception
     */
    function nextdomPluginAutoload($className)
    {
        global $ENABLED_PLUGINS;
        if ($ENABLED_PLUGINS === null) {
            $ENABLED_PLUGINS = array_keys(ConfigManager::getEnabledPlugins());
        }
        if (!empty($ENABLED_PLUGINS)) {
            $purgedClassName = str_replace(array('Real', 'Cmd'), '', $className);
            $activePlugin = in_array($purgedClassName, $ENABLED_PLUGINS);
            if (!$activePlugin) {
                $purgedClassName = explode('_', $purgedClassName)[0];
                $activePlugin = in_array($purgedClassName, $ENABLED_PLUGINS);
            }
            if ($activePlugin) {
                try {
                    FileSystemHelper::includeFile('core', $purgedClassName, 'class', $purgedClassName);
                } catch (\Throwable $e) {

                }
            }
        }
    }

    /**
     * Translate a string (global function)
     *
     * @param string $content
     * @param string $name
     * @param bool $backslah
     * @return string
     * @throws \Exception
     */
    function __(string $content, string $name = '', bool $backslah = false): string
    {
        return \NextDom\__($content, $name, $backslah);
    }
}

// Declare global functions
namespace NextDom {

    use NextDom\Helpers\TranslateHelper;

    /**
     * Translate a string
     *
     * @param string $content
     * @param string $name
     * @param bool $backslah
     * @return string
     * @throws \Exception
     */
    function __(string $content, string $name = '', bool $backslah = false): string
    {
        return TranslateHelper::sentence($content, $name, $backslah);
    }
}
