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

use NextDom\Enums\DateFormat;
use NextDom\Enums\NextDomObj;
use NextDom\Helpers\DBHelper;
use NextDom\Managers\CmdManager;
use NextDom\Managers\HistoryManager;
use NextDom\Model\Entity\Parents\BaseModel;

/**
 * History
 *
 * @ORM\Table(name="history", uniqueConstraints={@ORM\UniqueConstraint(name="unique", columns={"datetime", "cmd_id"})}, indexes={@ORM\Index(name="fk_history5min_commands1_idx", columns={"cmd_id"})})
 * @ORM\Entity
 */
class History extends BaseModel
{
    const TABLE_NAME = NextDomObj::HISTORY;

    /**
     * @var string
     *
     * @ORM\Column(name="datetime", type="datetime", nullable=false)
     */
    protected $datetime;

    /**
     * @var string
     *
     * @ORM\Column(name="value", type="string", length=127, nullable=true)
     */
    protected $value;

    /**
     * @var \NextDom\Model\Entity\Cmd
     *
     * @ORM\ManyToOne(targetEntity="NextDom\Model\Entity\Cmd")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="cmd_id", referencedColumnName="id")
     * })
     */
    protected $cmd_id;
    protected $_tableName = self::TABLE_NAME;

    /**
     * @param null $_cmd
     * @param bool $_direct
     * @throws \NextDom\Exceptions\CoreException
     */
    public function save($_cmd = null, $_direct = false)
    {
        global $NEXTDOM_INTERNAL_CONFIG;
        if ($_cmd === null) {
            $cmd = $this->getCmd();
            if (!is_object($cmd)) {
                HistoryManager::emptyHistory($this->getCmd_id());
                return;
            }
        } else {
            $cmd = $_cmd;
        }
        if ($this->getDatetime() == '') {
            $this->setDatetime(date(DateFormat::FULL));
        }
        if ($cmd->getConfiguration('historizeRound') !== '' && is_numeric($cmd->getConfiguration('historizeRound')) && $cmd->getConfiguration('historizeRound') >= 0 && $this->getValue() !== null) {
            $this->setValue(round($this->getValue(), $cmd->getConfiguration('historizeRound')));
        }
        if ($NEXTDOM_INTERNAL_CONFIG['cmd']['type']['info']['subtype'][$cmd->getSubType()]['isHistorized']['canBeSmooth'] && $cmd->getConfiguration('historizeMode', 'avg') != 'none' && $this->getValue() !== null && $_direct === false) {
            if ($this->getTableName() == 'history') {
                $time = strtotime($this->getDatetime());
                $time -= $time % 300;
                $this->setDatetime(date(DateFormat::FULL, $time));
                if ($this->getValue() === 0) {
                    $values = [
                        'cmd_id' => $this->getCmd_id(),
                        'datetime' => date('Y-m-d H:i:00', strtotime($this->getDatetime()) + 300),
                        'value' => $this->getValue(),
                    ];
                    $sql = 'REPLACE INTO ' . HistoryManager::DB_CLASS_NAME . '
                            SET `cmd_id` = :cmd_id,
                            `datetime` = :datetime,
                            `value` = :value';
                    DBHelper::exec($sql, $values);
                    return;
                }
                $values = [
                    'cmd_id' => $this->getCmd_id(),
                    'datetime' => $this->getDatetime(),
                ];
                $sql = 'SELECT `value`
                        FROM ' . HistoryManager::DB_CLASS_NAME . '
                        WHERE `cmd_id` = :cmd_id
                        AND `datetime`=:datetime';
                $result = DBHelper::getOne($sql, $values);
                if ($result !== false) {
                    switch ($cmd->getConfiguration('historizeMode', 'avg')) {
                        case 'avg':
                            $this->setValue(($result['value'] + intval($this->getValue())) / 2);
                            break;
                        case 'min':
                        case 'max':
                            $this->setValue(min($result['value'], $this->getValue()));
                            $this->setValue(max($result['value'], $this->getValue()));
                            break;
                    }
                    if ($result['value'] === $this->getValue()) {
                        return;
                    }
                }
            } else {
                $this->setDatetime(date('Y-m-d H:00:00', strtotime($this->getDatetime())));
            }
        }
        $values = [
            'cmd_id' => $this->getCmd_id(),
            'datetime' => $this->getDatetime(),
            'value' => $this->getValue(),
        ];
        if ($values['value'] === '') {
            $values['value'] = null;
        }
        $sql = 'REPLACE INTO ' . HistoryManager::DB_CLASS_NAME . '
                SET `cmd_id` = :cmd_id,
                `datetime` = :datetime,
                `value` = :value';
        DBHelper::exec($sql, $values);
    }

    /**
     * @return bool|Cmd
     * @throws \Exception
     */
    public function getCmd()
    {
        return CmdManager::byId($this->cmd_id);
    }

    /**
     * @return Cmd
     */
    public function getCmd_id()
    {
        return $this->cmd_id;
    }

    /**
     * @param $cmd_id
     * @return $this
     */
    public function setCmd_id($cmd_id)
    {
        $this->updateChangeState($this->cmd_id, $cmd_id);
        $this->cmd_id = $cmd_id;
        return $this;
    }

    /**
     * @return string
     */
    public function getDatetime()
    {
        return $this->datetime;
    }

    /**
     * @param $datetime
     * @return $this
     */
    public function setDatetime($datetime)
    {
        $this->updateChangeState($this->datetime, $datetime);
        $this->datetime = $datetime;
        return $this;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param $value
     * @return $this
     */
    public function setValue($value)
    {
        $this->updateChangeState($this->value, $value);
        $this->value = $value;
        return $this;
    }

    /**
     * @param $_tableName
     * @return $this
     */
    public function setTableName($_tableName)
    {
        $this->_tableName = $_tableName;
        return $this;
    }
}
