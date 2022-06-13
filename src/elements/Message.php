<?php
/**
 * Support plugin for Craft CMS 3.x
 *
 * Simple support system for tracking, prioritising and solving customer support tickets.
 *
 * @link      https://github.com/lukeyouell
 * @copyright Copyright (c) 2018 Luke Youell
 */

namespace lukeyouell\support\elements;

use lukeyouell\support\Support;
use lukeyouell\support\elements\db\MessageQuery;

use yii\validators\InlineValidator;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\UrlHelper;

class Message extends Element
{
    // Public Properties
    // =========================================================================

    private $_ticketId;

    private $_ticket;
    
    protected $isNormalizedTicket = false;

    public $authorId;

    public $attachmentIds;

    public $content;

    public $_author;

    // Static Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('support', 'Message');
    }

    public static function refHandle()
    {
        return 'message';
    }

    public static function hasContent(): bool
    {
        return true;
    }

    public static function hasTitles(): bool
    {
        return false;
    }

    public static function isLocalized(): bool
    {
        return true;
    }

    public static function hasStatuses(): bool
    {
        return false;
    }

    public static function find(): ElementQueryInterface
    {
        return new MessageQuery(static::class);
    }

    // Public Methods
    // -------------------------------------------------------------------------

    public function __toString()
    {
        if ($this->content) {
            return (string)$this->content;
        }

        return (string)$this->id;
    }

    public function attributes()
    {
        $attributes = parent::attributes();

        $attributes[] = 'ticketId';

        return $attributes;
    }

    public function setTicketId( $id )
    {
        $this->_ticketId = $id;
        $this->isNormalizedTicket = false;
    }

    public function getTicketId()
    {
        return $this->_ticketId;
    }

    public function getTicket()
    {
        if (!$this->isNormalizedTicket)
        {
            $this->_ticket = ($this->ticketId ? Support::getInstance()->ticketService
                ->getTicketById($this->ticketId) : null);
            $this->isNormalizedTicket = true;
        }

        return $this->_ticket;
    }

    public function extraFields()
    {
        $names = parent::extraFields();

        $names[] = 'author';

        return $names;
    }

    public function getAuthor()
    {
        if ($this->_author !== null) {
            return $this->_author;
        }

        if ($this->authorId === null) {
            return null;
        }

        if (($this->_author = Craft::$app->getUsers()->getUserById($this->authorId)) === null) {
            throw new InvalidConfigException('Invalid author ID: '.$this->authorId);
        }

        return $this->_author;
    }

    // =Validation
    // -------------------------------------------------------------------------

    public function rules()
    {
        $rules = parent::rules();

        $rules['ticketIdRequired'] = [ 'ticketId', 'required' ];
        $rules['ticketFound'] = [ 'ticketId', 'validateTicketReference' ];
        $rules['contentRequired'] = [ 'content', 'required' ];

        return $rules;
    }

    public function validateTicketReference( $attribute, $params, InlineValidator $validator )
    {
        $ticket = $this->ticket;
        if (!$ticket)
        {
            $message = Craft::t('support', 'Could not find Ticket for message‘s {attribute}', [
                'attribuite' => $attribute
            ]);

            $this->addError($attribute, $message);
        }
    }

    // Events
    // -------------------------------------------------------------------------

    public function afterSave(bool $isNew)
    {
        if ($isNew) {
            Craft::$app->db->createCommand()
                ->insert('{{%support_messages}}', [
                    'id'            => $this->id,
                    'ticketId'      => $this->ticketId,
                    'authorId'      => $this->authorId,
                    'attachmentIds' => $this->attachmentIds,
                    'content'       => $this->content,
                ])
                ->execute();
        } else {
            Craft::$app->db->createCommand()
                ->update('{{%support_messages}}', [
                    'content' => $this->content,
                ], ['id' => $this->id])
                ->execute();
        }

        parent::afterSave($isNew);
    }
}
