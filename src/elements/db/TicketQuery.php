<?php
/**
 * Support plugin for Craft CMS 3.x
 *
 * Simple support system for tracking, prioritising and solving customer support tickets.
 *
 * @link      https://github.com/lukeyouell
 * @copyright Copyright (c) 2018 Luke Youell
 */

namespace lukeyouell\support\elements\db;

use lukeyouell\support\models\TicketStatus as TicketStatus;

use Craft;
use craft\elements\User;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;

use yii\base\InvalidConfigException;

class TicketQuery extends ElementQuery
{
    // =Properties
    // ========================================================================

    /**
     * @var int | array
     */

    public $ticketStatusId;

    /**
     * @var int | array
     */

    public $authorId;

    /**
     * @var int | array
     */

    public $recipientId;

    /**
     * @var int | array
     */

    public $orderId;

    /**
     * @var string | array
     */

    public $deletedOrderReference;

    /**
     * @var bool
     */

    protected $commerceInstalled;

    // =Public Methods
    // ========================================================================

    /**
     * @inheritdoc
     */

    public function init()
    {
        $this->commerceInstalled = Craft::$app->getPlugins()->isPluginInstalled('commerce');
    }

    /**
     * @inheritdoc
     */

    public function __set($name, $value)
    {
        switch ($name) {
            case 'ticketStatus':
                $this->ticketStatus($value);
                break;
            case 'order':
                $this->order($value);
                break;
            case 'recipient':
                $this->recipient($value);
                break;
            default:
                parent::__set($name, $value);
        }
    }

    /**
     * 
     */

    public function ticketStatusId($value)
    {
        $this->ticketStatusId = $value;
        return $this;
    }

    /**
     * 
     */

    public function ticketStatus($value)
    {
        if ($value instanceof TicketStatus) {
            $this->ticketStatusId = $value->id;
        } elseif ($value !== null) {
            $this->ticketStatusId = $value;
        } else {
            $this->ticketStatusId = null;
        }

        return $this;
    }

    /**
     * 
     */

    public function authorId($value)
    {
        $this->parseElementIdCriteria(User::class, 'authorId', $value);
        return $this;
    }

    /**
     * 
     */

    public function author( $value )
    {
        $this->parseUserCriteria('author', $value);
        return $this;
    }

    /**
     * 
     */

    public function recipientId($value)
    {
        $this->parseElementIdCriteria(User::class, 'recipientId', $value);
        return $this;
    }

    /**
     * 
     */

    public function recipient( $value )
    {
        $this->parseUserCriteria('recipient', $value);
        return $this;
    }

    /**
     * 
     */

    public function orderId( $value )
    {
        if ($this->commerceInstalled)
        {
            $elementType = \craft\commerce\elements\Order::class;
            $this->parseElementIdCriteria($elementType, 'orderId', $value);
        }

        return $this;
    }

    /**
     * 
     */

    public function order( $value )
    {
        if ($this->commerceInstalled) {
            $this->parseOrderCriteria($value);
        }

        return $this;
    }

    /**
     * 
     */

    public function deletedOrderReference( $value )
    {
        $this->deletedOrderReference = $value;
        return $this;
    }

    /**
     * 
     */

    protected function beforePrepare(): bool
    {
        // join in the tickets table
        $this->joinElementTable('support_tickets');

        // select the columns
        $this->query->select([
            'support_tickets.ticketStatusId',
            'support_tickets.authorId',
            'support_tickets.recipientId',
            'support_tickets.orderId',
            'support_tickets.deletedOrderReference',
        ]);

        if ($this->ticketStatusId) {
            $this->subQuery->andWhere(Db::parseParam('support_tickets.ticketStatusId', $this->ticketStatusId));
        }

        if ($this->authorId) {
            $this->subQuery->andWhere(Db::parseParam('support_tickets.authorId', $this->authorId));
        }

        if ($this->recipientId) {
            $this->subQuery->andWhere(Db::parseParam('support_tickets.recipientId', $this->recipientId));
        }

        if ($this->orderId) {
            $this->subQuery->andWhere(Db::parseParam('support_tickets.orderId', $this->orderId));
        }

        if ($this->deletedOrderReference) {
            $this->subQuery->andWhere(Db::parseParam('support_tickets.deletedOrderReference', $this->deletedOrderReference));
        }

        return parent::beforePrepare();
    }

    /**
     * 
     */

    protected function parseElementIdCriteria( string $elementType, string $name, $value )
    {
        $value = $this->parseElementIdCriteriaValue($elementType, $name, $value);
        $this->$name = $vaue;
    }

    /**
     * 
     */

    protected function parseElementIdCriteriaValue( string $elementType, string $name, $value )
    {
        if (is_array($value))
        {
            $res = [];

            foreach ($value as $val) {
                $res[] = $this->parseElementIdCriteriaValue($elementType, $name, $value);
            }

            return $res;
        }

        else if (is_numeric($value)) {
            return (int)$value;
        }

        else if (is_null($value)) {
            return null;
        }

        $errorMessage = Craft::t('support', 'Ticket criteria `{name}` must be a `{elementType}` instance, id, name or title.', [
            'elementType' => $elementType,
            'name' => $name,
        ]);

        throw new InvalidConfigException($errorMessage);
    }

    /**
     * 
     */

    protected function parseUserCriteria( string $name, $value )
    {
        $value = $this->getUserCriteriaValue($name, $value);
        $this->parseElementIdCriteria($name .'Id', $value);
    }

    /**
     * 
     */

    protected function parseUserCriteriaValue( string $name,  $value )
    {
        if (is_array($value))
        {
            $res = [];

            foreach ($value as $val) {
                $res[] = $this->parseUserCriteriaValue($name, $value);
            }

            return $res;
        }

        if (is_string($value) && !is_numeric($value)) {
            $value = Craft::$app->getUsers()->getUserByUsernameOrEmail($value);
        }

        if ($value instanceof User) {
            $value =  $value->id;
        }

        return $value;
    }

    /**
     * 
     */

    protected function parseOrderCriteria( string $name, $value )
    {
        $value = $this->parseOrderCriteriaValue($name, $value);
        $this->parseElementIdCriteria($name .'Id', $value);
    }

    /**
     * 
     */

    protected function parseOrderCriteriaValue( string $name, $value )
    {
        if (is_array($value))
        {
            $res = [];

            foreach ($value as $val) {
                $res[] = $this->parseOrderCriteriaValue($name, $val);
            }

            return $res;
        }

        if (is_string($value) && !is_numeric($value))
        {
            $value = \craft\commerce\elements\Order::find()
                ->orWhere([
                    'reference' => $value,
                    'number' => $value,
                    'shortNumber' => $value,
                ])
                ->one();
        }

        if ($value instanceof User) {
            $value =  $value->id;
        }

        return $value;
    }

    // =Private Methods
    // ========================================================================

}
