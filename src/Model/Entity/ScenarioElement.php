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
 */

namespace NextDom\Model\Entity;

use NextDom\Exceptions\CoreException;
use NextDom\Helpers\LogHelper;
use NextDom\Helpers\NextDomHelper;
use NextDom\Helpers\SystemHelper;
use NextDom\Helpers\Utils;
use NextDom\Managers\CronManager;
use NextDom\Managers\ScenarioElementManager;
use NextDom\Managers\ScenarioExpressionManager;
use NextDom\Managers\ScenarioManager;
use NextDom\Managers\ScenarioSubElementManager;

/**
 * Scenarioelement
 *
 * ORM\Table(name="scenarioElement")
 * ORM\Entity
 */
class ScenarioElement
{

    /**
     * @var integer
     *
     * @ORM\Column(name="order", type="integer", nullable=false)
     */
    protected $order = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="type", type="string", length=127, nullable=true)
     */
    protected $type;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=127, nullable=true)
     */
    protected $name;

    /**
     * @var string
     *
     * @ORM\Column(name="options", type="text", length=65535, nullable=true)
     */
    protected $options;

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    protected $_subelement;


    public function save() {
        \DB::save($this);
    }

    public function remove() {
        foreach ($this->getSubElement() as $subElement) {
            $subElement->remove();
        }
        \DB::remove($this);
    }

    /**
     * @param \scenario|null $_scenario
     * @return bool|null
     * @throws CoreException
     */
    public function execute(&$_scenario = null) {
        if ($_scenario != null && !$_scenario->getDo()) {
            return null;
        }
        if (!is_object($this->getSubElement($this->getType()))) {
            return null;
        }
        if ($this->getType() == 'if') {
            if ($this->getSubElement('if')->getOptions('enable', 1) == 0) {
                return true;
            }
            $result = $this->getSubElement('if')->execute($_scenario);
            if (is_string($result) && strlen($result) > 1) {
                $_scenario->setLog(__('Expression non valide : ') . $result);
                return null;
            }
            if ($result) {
                if ($this->getSubElement('if')->getOptions('allowRepeatCondition', 0) == 1) {
                    if ($this->getSubElement('if')->getOptions('previousState', -1) != 1) {
                        $this->getSubElement('if')->setOptions('previousState', 1);
                        $this->getSubElement('if')->save();
                    } else {
                        $_scenario->setLog(__('Non exécution des actions pour cause de répétition'));
                        return null;
                    }
                }
                return $this->getSubElement('then')->execute($_scenario);
            }
            if (!is_object($this->getSubElement('else'))) {
                return null;
            }
            if ($this->getSubElement('if')->getOptions('allowRepeatCondition', 0) == 1) {
                if ($this->getSubElement('if')->getOptions('previousState', -1) != 0) {
                    $this->getSubElement('if')->setOptions('previousState', 0);
                    $this->getSubElement('if')->save();
                } else {
                    $_scenario->setLog(__('Non exécution des actions pour cause de répétition'));
                    return null;
                }
            }
            return $this->getSubElement('else')->execute($_scenario);

        } elseif ($this->getType() == 'action') {
            if ($this->getSubElement('action')->getOptions('enable', 1) == 0) {
                return true;
            }
            return $this->getSubElement('action')->execute($_scenario);
        } elseif ($this->getType() == 'code') {
            if ($this->getSubElement('code')->getOptions('enable', 1) == 0) {
                return true;
            }
            return $this->getSubElement('code')->execute($_scenario);
        } elseif ($this->getType() == 'for') {
            $for = $this->getSubElement('for');
            if ($for->getOptions('enable', 1) == 0) {
                return true;
            }
            $limits = $for->getExpression();
            $limits = intval(NextDomHelper::evaluateExpression($limits[0]->getExpression()));
            if (!is_numeric($limits)) {
                $_scenario->setLog(__('[ERREUR] La condition pour une boucle doit être numérique : ') . $limits);
                throw new CoreException(__('La condition pour une boucle doit être numérique : ') . $limits);
            }
            $return = false;
            for ($i = 1; $i <= $limits; $i++) {
                $return = $this->getSubElement('do')->execute($_scenario);
            }
            return $return;
        } elseif ($this->getType() == 'in') {
            $in = $this->getSubElement('in');
            if ($in->getOptions('enable', 1) == 0) {
                return true;
            }
            $time = ceil(str_replace('.', ',', NextDomHelper::evaluateExpression($in->getExpression()[0]->getExpression(), $_scenario)));
            if (!is_numeric($time) || $time < 0) {
                $time = 0;
            }
            if ($time == 0) {
                $cmd = NEXTDOM_ROOT . '/core/php/jeeScenario.php ';
                $cmd .= ' scenario_id=' . $_scenario->getId();
                $cmd .= ' scenarioElement_id=' . $this->getId();
                $cmd .= ' tags=' . escapeshellarg(json_encode($_scenario->getTags()));
                $cmd .= ' >> ' . LogHelper::getPathToLog('scenario_element_execution') . ' 2>&1 &';
                $_scenario->setLog(__('Tâche : ') . $this->getId() . __(' lancement immédiat '));
                SystemHelper::php($cmd);
            } else {
                $crons = CronManager::searchClassAndFunction('scenario', 'doIn', '"scenarioElement_id":' . $this->getId() . ',');
                if (is_array($crons)) {
                    foreach ($crons as $cron) {
                        if ($cron->getState() != 'run') {
                            $cron->remove();
                        }
                    }
                }
                $cron = new cron();
                $cron->setClass('scenario');
                $cron->setFunction('doIn');
                $cron->setOption(array('scenario_id' => intval($_scenario->getId()), 'scenarioElement_id' => intval($this->getId()), 'second' => date('s'), 'tags' => $_scenario->getTags()));
                $cron->setLastRun(date('Y-m-d H:i:s'));
                $cron->setOnce(1);
                $next = strtotime('+ ' . $time . ' min');
                $cron->setSchedule(CronManager::convertDateToCron($next));
                $cron->save();
                $_scenario->setLog(__('Tâche : ') . $this->getId() . __(' programmé à : ') . date('Y-m-d H:i:s', $next) . ' (+ ' . $time . ' min)');
            }
            return true;
        } elseif ($this->getType() == 'at') {
            $at = $this->getSubElement('at');
            if ($at->getOptions('enable', 1) == 0) {
                return true;
            }
            $next = NextDomHelper::evaluateExpression($at->getExpression()[0]->getExpression(), $_scenario);
            if (!is_numeric($next) || $next < 0) {
                throw new CoreException(__('Bloc type A : ') . $this->getId() . __(', heure programmée invalide : ') . $next);
            }
            if ($next < date('Gi', strtotime('+1 minute' . date('G:i')))) {
                $next = str_repeat('0', 4 - strlen($next)) . $next;
                $next = date('Y-m-d', strtotime('+1 day' . date('Y-m-d'))) . ' ' . substr($next, 0, 2) . ':' . substr($next, 2, 4);
            } else {
                $next = str_repeat('0', 4 - strlen($next)) . $next;
                $next = date('Y-m-d') . ' ' . substr($next, 0, 2) . ':' . substr($next, 2, 4);
            }
            $next = strtotime($next);
            if ($next < strtotime('now')) {
                throw new CoreException(__('Bloc type A : ') . $this->getId() . __(', heure programmée invalide : ') . date('Y-m-d H:i:00', $next));
            }
            $crons = CronManager::searchClassAndFunction('scenario', 'doIn', '"scenarioElement_id":' . $this->getId() . ',');
            if (is_array($crons)) {
                foreach ($crons as $cron) {
                    if ($cron->getState() != 'run') {
                        $cron->remove();
                    }
                }
            }
            $cron = new cron();
            $cron->setClass('scenario');
            $cron->setFunction('doIn');
            $cron->setOption(array('scenario_id' => intval($_scenario->getId()), 'scenarioElement_id' => intval($this->getId()), 'second' => 0, 'tags' => $_scenario->getTags()));
            $cron->setLastRun(date('Y-m-d H:i:s', strtotime('now')));
            $cron->setOnce(1);
            $cron->setSchedule(CronManager::convertDateToCron($next));
            $cron->save();
            $_scenario->setLog(__('Tâche : ') . $this->getId() . __(' programmée à : ') . date('Y-m-d H:i:00', $next));
            return true;
        }
        return null;
    }

    /**
     * @param string $_type
     * @return \scenarioSubElement[]|\scenarioSubElement
     * @throws \Exception
     */
    public function getSubElement($_type = '') {
        if ($_type != '') {
            if (isset($this->_subelement[$_type]) && is_object($this->_subelement[$_type])) {
                return $this->_subelement[$_type];
            }
            $this->_subelement[$_type] = ScenarioSubElementManager::byScenarioElementId($this->getId(), $_type);
            return $this->_subelement[$_type];
        } else {
            if (isset($this->_subelement[-1]) && is_array($this->_subelement[-1]) && count($this->_subelement[-1]) > 0) {
                return $this->_subelement[-1];
            }
            $this->_subelement[-1] = ScenarioSubElementManager::byScenarioElementId($this->getId(), $_type);
            return $this->_subelement[-1];
        }
    }

    public function getAjaxElement($_mode = 'ajax') {
        $return = Utils::o2a($this);
        if ($_mode == 'array') {
            if (isset($return['id'])) {
                unset($return['id']);
            }
            if (isset($return['scenarioElement_id'])) {
                unset($return['scenarioElement_id']);
            }
            if (isset($return['log'])) {
                unset($return['log']);
            }
            if (isset($return['_expression'])) {
                unset($return['_expression']);
            }
        }
        $return['subElements'] = array();
        foreach ($this->getSubElement() as $subElement) {
            $subElement_ajax = Utils::o2a($subElement);
            if ($_mode == 'array') {
                if (isset($subElement_ajax['id'])) {
                    unset($subElement_ajax['id']);
                }
                if (isset($subElement_ajax['scenarioElement_id'])) {
                    unset($subElement_ajax['scenarioElement_id']);
                }
                if (isset($subElement_ajax['log'])) {
                    unset($subElement_ajax['log']);
                }
                if (isset($subElement_ajax['_expression'])) {
                    unset($subElement_ajax['_expression']);
                }
            }
            $subElement_ajax['expressions'] = array();
            foreach ($subElement->getExpression() as $expression) {
                $expression_ajax = Utils::o2a($expression);
                if ($_mode == 'array') {
                    if (isset($expression_ajax['id'])) {
                        unset($expression_ajax['id']);
                    }
                    if (isset($expression_ajax['scenarioSubElement_id'])) {
                        unset($expression_ajax['scenarioSubElement_id']);
                    }
                    if (isset($expression_ajax['log'])) {
                        unset($expression_ajax['log']);
                    }
                    if (isset($expression_ajax['_expression'])) {
                        unset($expression_ajax['_expression']);
                    }
                }
                if ($expression->getType() == 'element') {
                    $element = ScenarioElementManager::byId($expression->getExpression());
                    if (is_object($element)) {
                        $expression_ajax['element'] = $element->getAjaxElement($_mode);
                        if ($_mode == 'array') {
                            if (isset($expression_ajax['element']['id'])) {
                                unset($expression_ajax['element']['id']);
                            }
                            if (isset($expression_ajax['element']['scenarioElement_id'])) {
                                unset($expression_ajax['element']['scenarioElement_id']);
                            }
                            if (isset($expression_ajax['element']['log'])) {
                                unset($expression_ajax['element']['log']);
                            }
                            if (isset($expression_ajax['element']['_expression'])) {
                                unset($expression_ajax['element']['_expression']);
                            }
                        }
                    }
                }
                $expression_ajax = NextDomHelper::toHumanReadable($expression_ajax);
                $subElement_ajax['expressions'][] = $expression_ajax;
            }
            $return['subElements'][] = $subElement_ajax;
        }
        return $return;
    }

    public function getAllId() {
        $return = array(
            'element' => array($this->getId()),
            'subelement' => array(),
            'expression' => array(),
        );
        foreach ($this->getSubElement() as $subelement) {
            $result = $subelement->getAllId();
            $return['element'] = array_merge($return['element'], $result['element']);
            $return['subelement'] = array_merge($return['subelement'], $result['subelement']);
            $return['expression'] = array_merge($return['expression'], $result['expression']);
        }
        return $return;
    }

    public function resetRepeatIfStatus() {
        foreach ($this->getSubElement() as $subElement) {
            if ($subElement->getType() == 'if') {
                $subElement->setOptions('previousState', -1);
                $subElement->save();
            }
            foreach ($subElement->getExpression() as $expression) {
                $expression->resetRepeatIfStatus();
            }
        }
    }

    public function export() {
        $return = '';
        foreach ($this->getSubElement() as $subElement) {
            $return .= "\n";
            switch ($subElement->getType()) {
                case 'if':
                    $return .= __('SI');
                    break;
                case 'then':
                    $return .= __('ALORS');
                    break;
                case 'else':
                    $return .= __('SINON');
                    break;
                case 'for':
                    $return .= __('POUR');
                    break;
                case 'do':
                    $return .= __('FAIRE');
                    break;
                case 'code':
                    $return .= __('CODE');
                    break;
                case 'action':
                    $return .= __('ACTION');
                    break;
                case 'in':
                    $return .= __('DANS');
                    break;
                case 'at':
                    $return .= __('A');
                    break;
                default:
                    $return .= $subElement->getType();
                    break;
            }

            foreach ($subElement->getExpression() as $expression) {
                $export = $expression->export();
                if ($expression->getType() != 'condition' && trim($export) != '') {
                    $return .= "\n";
                }
                if (trim($export) != '') {
                    $return .= ' ' . $expression->export();
                }
            }
        }
        return $return;
    }

    public function copy() {
        $elementCopy = clone $this;
        $elementCopy->setId('');
        $elementCopy->save();
        foreach ($this->getSubElement() as $subelement) {
            $subelement->copy($elementCopy->getId());
        }
        return $elementCopy->getId();
    }

    /**
     * @return mixed|null
     * @throws \Exception
     */
    public function getScenario() {
        $scenario = ScenarioManager::byElement($this->getId());
        if (is_object($scenario)) {
            return $scenario;
        }
        $expression = ScenarioExpressionManager::byElement($this->getId());
        if (is_object($expression)) {
            return $expression->getSubElement()->getElement()->getScenario();
        }
        return null;
    }

    public function getId() {
        return $this->id;
    }

    public function setId($id) {
        $this->id = $id;
        return $this;
    }

    public function getName() {
        return $this->name;
    }

    public function setName($name) {
        $this->name = $name;
        return $this;
    }

    public function getType() {
        return $this->type;
    }

    public function setType($type) {
        $this->type = $type;
        return $this;
    }

    public function getOptions($_key = '', $_default = '') {
        return Utils::getJsonAttr($this->options, $_key, $_default);
    }

    public function setOptions($_key, $_value) {
        $this->options = Utils::setJsonAttr($this->options, $_key, $_value);
        return $this;
    }

    public function getOrder() {
        return $this->order;
    }

    public function setOrder($order) {
        $this->order = $order;
        return $this;
    }

    public function getTableName() {
        return 'scenarioElement';
    }
}
