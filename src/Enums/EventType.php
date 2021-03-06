<?php
/* This file is part of NextDom.
 *
 * NextDom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * NextDom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with NextDom. If not, see <http://www.gnu.org/licenses/>.
 */

namespace NextDom\Enums;

/**
 * Class EventType
 * @package NextDom\Enums
 */
class EventType extends Enum
{
    const CMD_UPDATE = 'cmd::update';
    const EQLOGIC_UPDATE = 'eqLogic::update';
    const SCENARIO_UPDATE = 'scenario::update';
    const SUMMARY_UPDATE = 'jeeObject::summary::update';
}
