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

use NextDom\Helpers\Utils;

try {
    require_once __DIR__ . '/../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect()) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__), -1234);
    }

    ajax::init();

    if (init('action') == 'nbUpdate') {
        ajax::success(update::nbNeedUpdate());
    }

    if (!isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__), -1234);
    }

    if (init('action') == 'all') {
        $return = array();
        foreach (update::all(init('filter')) as $update) {
            $infos = utils::o2a($update);
            if ($update->getType() == 'plugin') {
                try {
                    $plugin = plugin::byId($update->getLogicalId());
                    $infos['plugin'] = is_object($plugin) ? utils::o2a($plugin) : array();
                } catch (Exception $e) {

                }
            }
            $return[] = $infos;
        }
        ajax::success($return);
    }

    if (init('action') == 'checkAllUpdate') {
        unautorizedInDemo();
        update::checkAllUpdate();
        ajax::success();
    }

    if (init('action') == 'update') {
        unautorizedInDemo();
        log::clear('update');
        $update = update::byId(init('id'));
        if (!is_object($update)) {
            throw new Exception(__('Aucune correspondance pour l\'ID : ' . init('id'), __FILE__));
        }
        try {
            if ($update->getType() != 'core') {
                log::add('update', 'alert', __("[START UPDATE]", __FILE__));
            }
            $update->doUpdate();
            if ($update->getType() != 'core') {
                log::add('update', 'alert', __("Launch cron dependancy plugins", __FILE__));
                try {
                    $cron = cron::byClassAndFunction('plugin', 'checkDeamon');
                    if (is_object($cron)) {
                        $cron->start();
                    }
                } catch (Exception $e) {

                }
                log::add('update', 'alert', __("[END UPDATE SUCCESS]", __FILE__));
            }
        } catch (Exception $e) {
            if ($update->getType() != 'core') {
                log::add('update', 'alert', $e->getMessage());
                log::add('update', 'alert', __("[END UPDATE ERROR]", __FILE__));
            }
        }
        ajax::success();
    }

    if (init('action') == 'remove') {
        unautorizedInDemo();
        update::findNewUpdateObject();
        $update = update::byId(init('id'));
        if (!is_object($update)) {
            $update = update::byLogicalId(init('id'));
        }
        if (!is_object($update)) {
            throw new Exception(__('Aucune correspondance pour l\'ID : ' . init('id'), __FILE__));
        }
        $update->deleteObjet();
        ajax::success();
    }

    if (init('action') == 'checkUpdate') {
        unautorizedInDemo();
        $update = update::byId(init('id'));
        if (!is_object($update)) {
            $update = update::byLogicalId(init('id'));
        }
        if (!is_object($update)) {
            throw new Exception(__('Aucune correspondance pour l\'ID : ' . init('id'), __FILE__));
        }
        $update->checkUpdate();
        ajax::success();
    }

    if (init('action') == 'updateAll') {
        unautorizedInDemo();
        nextdom::update(json_decode(init('options', '{}'), true));
            ajax::success();
        }

        if (init('action') == 'save') {
            unautorizedInDemo();
            $new = false;
            $update_json = json_decode(init('update'), true);
            if (isset($update_json['id'])) {
                $update = update::byId($update_json['id']);
            }
            if (isset($update_json['logicalId'])) {
                $update = update::byLogicalId($update_json['logicalId']);
            }
            if (!isset($update) || !is_object($update)) {
                $update = new update();
                $new = true;
            }
            $old_update = $update;
            utils::a2o($update, $update_json);
            $update->save();
            try {
                $update->doUpdate();
            } catch (Exception $e) {
                if ($new) {
                    throw $e;
                } else {
                    $update = $old_update;
                    $update->save();
                }
            }
            ajax::success(utils::o2a($update));
        }

        if (init('action') == 'saves') {
            unautorizedInDemo();
            utils::processJsonObject('update', init('updates'));
            ajax::success();
        }

        if (init('action') == 'preUploadFile') {
            unautorizedInDemo();
            $uploadDir = '/tmp';
            $filename = Utils::readUploadedFile($_FILES, "file", $uploadDir, 100, array(), function($file) {
                $remove = array(" ", "(", ")");
                return str_replace($remove, "", $file["name"]);
            });
            $filepath = sprintf("%s/%s", $uploadDir, $filename);
            ajax::success($filepath);
        }

        throw new Exception(__('Aucune méthode correspondante à : ', __FILE__) . init('action'));
        /*     * *********Catch exeption*************** */
    } catch (Exception $e) {
        ajax::error(displayException($e), $e->getCode());
    }
