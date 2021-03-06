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

namespace NextDom\Controller\Modals;

use NextDom\Helpers\Render;
use NextDom\Helpers\Utils;
use NextDom\Managers\Plan3dManager;
use NextDom\Model\Entity\Plan3d;

/**
 * Class Plan3dConfigure
 * @package NextDom\Controller\Modals
 */
class Plan3dConfigure extends BaseAbstractModal
{
    /**
     * Render plan 3d configure modal
     *
     * @return string
     * @throws \NextDom\Exceptions\CoreException
     * @throws \ReflectionException
     */
    public static function get(): string
    {
        $pageData = [];
        $plan3d = Plan3dManager::byName3dHeaderId(Utils::init('name'), Utils::init('plan3dHeader_id'));
        if (!is_object($plan3d)) {
            $plan3d = (new Plan3d())
                ->setName(Utils::init('name'))
                ->setPlan3dHeader_id(Utils::init('plan3dHeader_id'));
            $plan3d->save();
        }
        $pageData['JS_VARS']['id'] = $plan3d->getId();

        return Render::getInstance()->get('/modals/plan3d.configure.html.twig', $pageData);
    }
}
