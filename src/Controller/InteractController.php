<?php

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
 *
 * @Support <https://www.nextdom.org>
 * @Email   <admin@nextdom.org>
 * @Authors/Contributors: Sylvaner, Byackee, cyrilphoenix71, ColonelMoutarde, edgd1er, slobberbone, Astral0, DanoneKiD
 */

namespace NextDom\Controller;

use NextDom\Helpers\NextDomHelper;
use NextDom\Helpers\Render;
use NextDom\Managers\CmdManager;
use NextDom\Managers\EqLogicManager;
use NextDom\Managers\InteractDefManager;
use NextDom\Managers\JeeObjectManager;

class InteractController extends BaseController
{
    /**
     * Render interact page
     *
     * @param Render $render Render engine
     * @param array $pageData Page data
     *
     * @return string Content of interact page
     *
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function get(Render $render, &$pageData): string
    {

        $interacts = array();
        $pageData['interactTotal'] = InteractDefManager::all();
        $interacts[-1] = InteractDefManager::all(null);
        $interactListGroup = InteractDefManager::listGroup();
        if (is_array($interactListGroup)) {
            foreach ($interactListGroup as $group) {
                $interacts[$group['group']] = InteractDefManager::all($group['group']);
            }
        }
        $pageData['JS_END_POOL'][] = '/public/js/desktop/tools/interact.js';
        $pageData['interactsList'] = $interacts;
        $pageData['interactsListGroup'] = $interactListGroup;
        $pageData['interactDisabledOpacity'] = NextDomHelper::getConfiguration('eqLogic:style:noactive');
        $pageData['interactCmdType'] = NextDomHelper::getConfiguration('cmd:type');
        $pageData['interactAllUnite'] = CmdManager::allUnite();
        $pageData['interactJeeObjects'] = JeeObjectManager::all();
        $pageData['interactEqLogicTypes'] = EqLogicManager::allType();
        $pageData['interactEqLogics'] = EqLogicManager::all();
        $pageData['interactEqLogicCategories'] = NextDomHelper::getConfiguration('eqLogic:category');
        $pageData['JS_END_POOL'][] = '/public/js/adminlte/utils.js';

        return $render->get('/desktop/tools/interact.html.twig', $pageData);
    }
}
