<?php
/**
 * Support plugin for Craft CMS 3.x
 *
 * Simple support system for tracking, prioritising and solving customer support tickets.
 *
 * @link      https://github.com/lukeyouell
 * @copyright Copyright (c) 2018 Luke Youell
 */

namespace lukeyouell\support\services;

use lukeyouell\support\Support;
use lukeyouell\support\elements\Ticket;
use lukeyouell\support\models\Email as EmailModel;
use lukeyouell\support\models\TicketStatus as TicketStatusModel;
use lukeyouell\support\records\Email as EmailRecord;
use lukeyouell\support\records\TicketStatus as TicketStatusRecord;
use lukeyouell\support\records\TicketStatusEmail as TicketStatusEmailRecord;

use Craft;
use craft\base\Component;
use craft\db\Query;

use yii\base\Exception;

class TicketStatusService extends Component
{

    // Properties
    // =========================================================================

    private $_fetchedAllStatuses = false;

    private $_ticketStatusesById = [];

    private $_ticketStatusesByHandle = [];

    // Public Methods
    // =========================================================================

    /**
     * @return \lukeyouell\support\models\TicketStatusModel[]
     */

    public function getAllTicketStatuses()
    {
        if (!$this->_fetchedAllStatuses) {
            $results = $this->_createTicketStatusQuery()->all();

            foreach ($results as $row) {
                $this->_memoizeTicketStatus(new TicketStatusModel($row));
            }

            $this->_fetchedAllStatuses = true;
        }

        return $this->_ticketStatusesById;
    }

    /**
     * @return \lukeyouell\support\models\TicketStatusModel | null
     */

     public function getTicketStatusById($id)
    {
        $result = $this->_createTicketStatusQuery()
            ->where(['id' => $id])
            ->one();

        if ($result) {
            return new TicketStatusModel($result);
        }

        return null;
    }

    /**
     * @return \lukeyouell\support\models\TicketStatusModel | null
     */

    public function getDefaultTicketStatus()
    {
        $result = $this->_createTicketStatusQuery()
            ->where(['default' => 1])
            ->one();

        if ($result) {
            return new TicketStatusModel($result);
        }

        return null;
    }

    /**
     * @return \lukeyouell\support\models\TicketStatusModel | null
     */

    public function getNewMessageTicketStatus()
    {
        $result = $this->_createTicketStatusQuery()
            ->where(['newMessage' => 1])
            ->one();

        if ($result) {
            return new TicketStatusModel($result);
        }

        return null;
    }

    /**
     * @return \lukeyouell\support\models\TicketStatusModel | null
     */

    public function getLegacyTicketStatus()
    {
        $result = $this->_createTicketStatusQuery()
            ->where(['legacy' => 1])
            ->one();

        if ($result) {
            return new TicketStatusModel($result);
        }

        return null;
    }

    /**
     * 
     */

    public function checkIfTicketStatusInUse($id)
    {
        $result = Ticket::find()
            ->ticketStatusId($id)
            ->one();

        return $result;
    }

    /**
     * 
     */

     public function reorderTicketStatuses(array $ids)
    {
        foreach ($ids as $sortOrder => $id) {
            Craft::$app->getDb()->createCommand()
                ->update('{{%support_ticketstatuses}}', ['sortOrder' => $sortOrder + 1], ['id' => $id])
                ->execute();
        }

        return true;
    }

    /**
     * 
     */

    public function saveTicketStatus(TicketStatusModel $model, array $emailIds, bool $runValidation = true)
    {
        if ($model->id) {
            $record = TicketStatusRecord::findOne($model->id);

            if (!$record->id) {
                throw new Exception(Craft::t('support', 'No ticket status exists with the ID "{id}"',
                    ['id' => $model->id]));
            }
        } else {
            $record = new TicketStatusRecord();
        }

        if ($runValidation && !$model->validate()) {
            Craft::info('Ticket status not saved due to a validation error.', __METHOD__);

            return false;
        }

        $record->name = $model->name;
        $record->handle = $model->handle;
        $record->colour = $model->colour;
        $record->sortOrder = $model->sortOrder ?: 999;
        $record->default = $model->default;
        $record->newMessage = $model->newMessage;
        $record->legacy = $model->legacy;

        // Validate email ids
        $exist = EmailRecord::find()->where(['in', 'id', $emailIds])->exists();
        $hasEmails = (boolean) count($emailIds);

        if (!$exist && $hasEmails) {
            $model->addError('emails', 'One or more emails do not exist in the system.');
        }

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            // Only one default status can be among statuses
            if ($record->default) {
                TicketStatusRecord::updateAll(['default' => 0]);
            }

            // Only one status can be triggered by new messages
            if ($record->newMessage) {
                TicketStatusRecord::updateAll(['newMessage' => 0]);
            }

            // if ($record->legacy) {
            //     TicketStatusRecord::updateAll(['legacy' => 0]);
            // }

            // Save it
            $record->save(false);

            // Delete old email links
            if ($model->id) {
                $rows = TicketStatusEmailRecord::find()->where(['ticketStatusId' => $model->id])->all();

                foreach ($rows as $row) {
                    $row->delete();
                }
            }

            // Save new email links
            $rows = array_map(
                function ($id) use ($record) {
                    return [$id, $record->id];
                }, $emailIds);

            $cols = ['emailId', 'ticketStatusId'];
            $table = TicketStatusEmailRecord::tableName();
            Craft::$app->getDb()->createCommand()->batchInsert($table, $cols, $rows)->execute();

            // Now that we have a record ID, save it on the model
            $model->id = $record->id;

            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();

            throw $e;
        }

        return true;
    }

    /**
     * 
     */

    public function deleteTicketStatusbyId($id)
    {
        $statuses = $this->getAllTicketStatuses();

        $existingTicket = $this->checkIfTicketStatusInUse($id);

        // Don't delete if it's still in use
        if ($existingTicket) {
            return false;
        }

        // Don't delete if it's the only status left
        if (count($statuses) > 1) {
            $record = TicketStatusRecord::findOne($id);

            return $record->delete();
        }

        return false;
    }

    // Private Methods
    // =========================================================================

    /**
     * 
     */

    private function _memoizeTicketStatus(TicketStatusModel $ticketStatus)
    {
        $this->_ticketStatusesById[$ticketStatus->id] = $ticketStatus;
        $this->_ticketStatusesByHandle[$ticketStatus->handle] = $ticketStatus;
    }

    /**
     * 
     */

    private function _createTicketStatusQuery()
    {
        return (new Query())
            ->select([
                'id',
                'name',
                'handle',
                'colour',
                'sortOrder',
                'default',
                'newMessage',
                'legacy',
            ])
            ->orderBy('sortOrder')
            ->from(['{{%support_ticketstatuses}}']);
    }
}
