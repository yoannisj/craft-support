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
use lukeyouell\support\elements\db\TicketQuery;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\UrlHelper;

use yii\base\Exception;
use yii\base\InvalidConfigException;

class Ticket extends Element
{
    // Public Properties
    // =========================================================================

    /**
     * @var integer
     */

    public $ticketStatusId;

    public $_ticketStatus;

    /**
     * @var integer
     */

    public $authorId;

    public $_author;

    /**
     * @var integer
     */

    private $_recipientId;

    /**
     * @var integer
     */

    private $_orderId;

    /**
     * @var string
     */

    private $_orderReference;

    /**
     * @var \craft\commerce\elements\Order | null
     */

    private $_order;

    /**
     * @var int
     */

    private $_deletedOrderReference;

    /**
     * @var array
     */

    public $_messages;

    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */

    public static function displayName(): string
    {
        return Craft::t('support', 'Ticket');
    }

    /**
     * @inheritdoc
     */

    public static function refHandle()
    {
        return 'ticket';
    }

    /**
     * @inheritdoc
     */

    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */

    public static function hasTitles(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */

    public static function isLocalized(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */

    public static function hasStatuses(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */

    public static function statuses(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */

    public static function find(): ElementQueryInterface
    {
        return new TicketQuery(static::class);
    }

    /**
     * @inheritdoc
     */

    protected static function defineSources(string $context = null): array
    {
        $userSessionService = Craft::$app->getUser();
        $userId = $userSessionService->getIdentity()->id;
        $canManageTickets = $userSessionService->checkPermission('support-manageTickets');

        $sources = [
            '*' => [
                'key'         => '*',
                'label'       => 'All Tickets',
                'criteria'    => [
                    'authorId' => $canManageTickets ? '' : $userId,
                ],
                'defaultSort' => ['dateCreated', 'desc'],
            ],
        ];

        $sources[] = [
            'key'         => 'myTickets',
            'label'       => 'My Tickets',
            'criteria'    => [
                'authorId' => $userId,
            ],
            'defaultSort' => ['dateCreated', 'desc'],
        ];

        $sources[] = ['heading' => 'Ticket Status'];

        $statuses = Support::getInstance()->ticketStatusService->getAllTicketStatuses();

        foreach ($statuses as $status) {
            $sources[] = [
                'key'         => 'status:'.$status['handle'],
                'status'      => $status['colour'],
                'label'       => $status['name'],
                'criteria'    => [
                    'authorId' => $canManageTickets ? '' : $userId,
                    'ticketStatusId' => $status['id'],
                ],
                'defaultSort' => ['dateCreated', 'desc'],
            ];
        }

        return $sources;
    }

    /**
     * @inheritdoc
     */

    protected static function defineSearchableAttributes(): array
    {
        return ['title', 'ticketStatusId'];
    }

    /**
     * @inheritdoc
     */

    protected static function defineActions(string $source = null): array
    {
        $userSessionService = Craft::$app->getUser();
        $canDeleteTickets = $userSessionService->checkPermission('support-deleteTickets');

        $actions = [];

        if ($canDeleteTickets) {
            $actions[] = Craft::$app->getElements()->createAction([
                'type'                => Delete::class,
                'confirmationMessage' => Craft::t('support', 'Are you sure you want to delete the selected tickets?'),
                'successMessage'      => Craft::t('support', 'Tickets deleted.'),
            ]);
        }

        return $actions;
    }

    /**
     * @inheritdoc
     */

    protected static function defineTableAttributes(): array
    {
        $userSessionService = Craft::$app->getUser();
        $canManageTickets = $userSessionService->checkPermission('support-manageTickets');

        if ($canManageTickets)
        {
            $attributes = [
                'title'          => Craft::t('support', 'Title'),
                'ticketStatus'   => Craft::t('support', 'Status'),
                'author'         => Craft::t('support', 'Author'),
                'recipient'      => Craft::t('support', 'Recipient'),
                'dateCreated'    => Craft::t('support', 'Date Created'),
                'dateUpdated'    => Craft::t('support', 'Date Updated'),
            ];
        }

        else
        {
            $attributes = [
                'title'        => Craft::t('support', 'Title'),
                'ticketStatus' => Craft::t('support', 'Status'),
                'dateCreated'  => Craft::t('support', 'Date Created'),
                'dateUpdated'  => Craft::t('support', 'Date Updated'),
            ];
        }

        if (Craft::$app->getPlugins()->isPluginEnabled('commerce'))
        {
            $attributes['order'] = Craft::t('commerce', 'Order');
        }

        return $attributes;
    }

    /**
     * @inheritdoc
     */

    protected static function defineDefaultTableAttributes(string $source): array
    {
        $userSessionService = Craft::$app->getUser();
        $canManageTickets = $userSessionService->checkPermission('support-manageTickets');

        if ($canManageTickets) {
            $attributes = ['title', 'ticketStatus', 'recipient', 'dateCreated', 'dateUpdated'];
        } else {
            $attributes = ['title', 'ticketStatus', 'dateCreated', 'dateUpdated'];
        }

        return $attributes;
    }

    /**
     * @inheritdoc
     */

    public function getTableAttributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'ticketStatus':
                $status = $this->getTicketStatus();
                return '<span class="status '.$status['colour'].'"></span>'.$status['name'];
            case 'author':
                if (($author = $this->getAuthor())) {
                    return Craft::$app->getView()->renderTemplate('_elements/element', [ 'element' => $author ]);
                }
                return '';
            case 'recipient':
                if (($recipient = $this->getRecipient())) {
                    return Craft::$app->getView()->renderTemplate('_elements/element', [ 'element' => $recipient ]);
                }
                if (($customer = $this->getCustomer())) {
                    return '<a href="'.$customer->cpEditUrl.'">'.$customer->email.'</a>';
                }
                return '';
            case 'order':
                if (($order = $this->getOrder())) {
                    return Craft::$app->getView()->renderTemplate('_elements/element', [ 'element' => $order ]);
                } else if ($this->deletedOrderReference) {
                    return $this->deletedOrderReference;
                } else {
                    return '';
                }
            default:
                return parent::tableAttributeHtml($attribute);
        }
    }

    // Public Methods
    // =========================================================================

    // Attributes
    // -------------------------------------------------------------------------

    /**
     * @inheritdoc
     */

    public function attributes()
    {
        $attributes = parent::attributes();

        $attributes[] = 'recipientId';
        $attributes[] = 'orderId';
        $attributes[] = 'deletedOrderReference';

        return $attributes;
    }

    /**
     * 
     */

    public function setRecipientId( $value )
    {
        $this->_recipientId = $value;
    }

    /**
     * 
     */

    public function getRecipientId()
    {
        if (!$this->_recipientId)
        {
            $order = $this->getOrder();
            if ($order) {
                $this->_recipientId = $order->customer->userId;
            }
        }

        return $this->_recipientId;
    }

    /**
     * Setter for the `orderId` property
     */

    public function setOrderId( $value )
    {
        $this->_orderId = $value;
        $this->_orderReference = null;

        if (isset($this->_order)
            && $this->_order->id != $value)
        {
            $this->_order = null;
        }

        if (isset($this->_deletedOrderReference)) {
            $this->_deletedOrderReference = null;
        }

        return $this;
    }

    /**
     * 
     */

    public function getOrderId()
    {
        if (!isset($this->_orderId)
            && isset($this->_orderReference))
        {
            $order = $this->getOrder();
            $this->_orderId = $order ? $order->id : null;
        }

        return $this->_orderId;
    }

    /**
     * Setter for the `orderReference` property
     */

    public function setOrderReference( $value )
    {
        $this->_orderReference = $value;
        $this->_orderId = null;

        if (isset($this->_order)
            && $this->_order->reference != $value)
        {
            $this->_order = null;
        }

        return $this;
    }

    /**
     * 
     */

    public function getOrderReference()
    {
        if (!isset($this->_orderReference)
            && isset($this->_orderId))
        {
            $order = $this->getOrder();
            $this->_orderReference = $order ? $order->reference : $this->_deletedOrderReference;
        }

        return $this->_orderReference;
    }

    /**
     * @param string | null $value
     */

    public function setDeletedOrderReference( string $value = null )
    {
        $this->_deletedOrderReference = $value;
    }

    /**
     * @return string | null
     */

    public function getDeletedOrderReference()
    {
        return $this->_deletedOrderReference;
    }

    // Validation
    // -------------------------------------------------------------------------

    /**
     * @inheritdoc
     */

    public function rules()
    {
        $rules = parent::rules();

        // the order validation method will set default recipient to order customer user id
        if (Craft::$app->getPlugins()->isPluginEnabled('commerce')) {
            $rules['orderForRecipient'] = [ 'order', 'validateOrderForRecipient' ];
        }

        $rules['defaultRecipientId'] = [ 'recipientId', 'default', 'value' => function($model, $attribute) {
            return $model->authorId;
        } ];

        return $rules;
    }

    /**
     *
     */

    public function validateOrderForRecipient( $attribute, $params, \yii\validators\InlineValidator $validator )
    {
        $order = $this->$attribute;

        // if ticket is linked to an order that does not belong to ticket recipient id
        if ($order)
        {
            // if order does not belong to customer/recipient
            if ($this->_recipientId && $this->_recipientId != $order->customer->userId)
            {
                $sessionUser = Craft::$app->getUser();
                $canManageTickets = $sessionUser->checkPermission('support-manageTickets');
                $message = null;

                if ($canManageTickets) {
                    $message = Craft::t('support', 'The ticket order should belong to its recipient.');
                }

                else if (!$sessionUser->isGuest && $sessionUser->getIdentity()->id == $this->_recipientId)
                {
                    $recipient = $this->getRecipient();
                    $message = Craft::t('support', 'Could not find order with reference "{reference}" for customer email {customer}', [
                        'reference' => $order->reference,
                        'customer' => $recipient->email,
                    ]);                    
                }

                else
                {
                    $message = Craft::t('support', 'Can not find order with reference "{reference}"', [
                        'reference' => $order->reference,
                    ]);
                }

                $this->addError($attribute, $message);
            }

            else {
                $this->_recipientId = $order->customer->userId;
            }
        }
    }

    // Fields
    // -------------------------------------------------------------------------

    /**
     * @inheritdoc
     */

    public function fields()
    {
        $fields = parent::fields();

        $fields[] = 'recipientId';

        return $fields;
    }

    /**
     * @inheritdoc
     */

    public function extraFields()
    {
        $names = parent::extraFields();

        $names[] = 'ticketStatus';
        $names[] = 'author';
        $names[] = 'recipient';
        $names[] = 'order';
        $names[] = 'orderReference';
        $names[] = 'customer';
        $names[] = 'recipientEmail';
        $names[] = 'recipientName';
        $names[] = 'messages';

        return $names;
    }

    public function getTicketStatus()
    {
        if ($this->_ticketStatus !== null) {
            return $this->_ticketStatus;
        }

        if ($this->ticketStatusId === null) {
            return null;
        }

        $this->_ticketStatus = Support::getInstance()->ticketStatusService->getTicketStatusById($this->ticketStatusId);

        return $this->_ticketStatus;
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

    /**
     * 
     */

    public function getAuthorLanguage()
    {
        $author = $this->getAuthor();

        if ($author && ($language = $author->preferredLanguage)) {
            return $language;
        }

        if ($this->authorId == $this->_recipientId
            && ($order = $this->getOrder()))
        {
            $customerUser = $order->getCustomer()->getUser();

            if ($customerUser && ($language = $customerUser->preferredLanguage)) {
                return $language;
            }

            return $order->orderLanguage;
        }

        return null;
    }

    public function getRecipient()
    {
        if ($this->_recipientId) {
            return Craft::$app->getUsers()->getUserById($this->_recipientId);
        }

        return null;
    }

    public function getOrder()
    {
        if (!isset($this->_order))
        {
            $order = null;

            if (Craft::$app->getPlugins()->isPluginInstalled('commerce')
                && ($this->_orderId || $this->_orderReference))
            {
                $orderQuery = \craft\commerce\elements\Order::find();
                $orderQuery->id = $this->_orderId;
                $orderQuery->reference = $this->_orderReference;

                $order = $orderQuery->one();
            }

            $this->_order = $order;
        }

        return $this->_order;
    }

    public function getCustomer()
    {
        $plugins = Craft::$app->getPlugins();

        if (!$plugins->isPluginInstalled('commerce')) {
            return null;
        }

        if (($order = $this->getOrder())) {
            return $order->customer;
        }

        else if ($this->_recipientId)
        {
            return $plugins->getPlugin('commerce')->getCustomers()
                ->getCustomerByUserId($this->_recipientId);
        }

        return null;
    }


    public function getRecipientEmail()
    {
        if (($order = $this->getOrder())) {
            return $order->email;
        }

        if (($recipient = $this->getRecipient())) {
            return $recipient->email;
        }

        else if (($customer = $this->getCustomer())) {
            return $customer->email;
        }

        return null;
    }

    public function getRecipientName()
    {
        if (($recipient = $this->getRecipient()))
        {
            return $recipient->fullName;
        }

        else if (($customer = $this->getCustomer()))
        {
            $address = $customer->getPrimaryBillingAddress() ?? $customer->getPrimaryShippingAddress();
            $fullName = $address->fullName;

            if (empty($fullName))
            {
                $fullName = implode(' ', array_filter([
                    $address->attention,
                    $address->firstName,
                    $address->lastName
                ]));
            }

            return $fullName;
        }

        return null;
    }
    /**
     * 
     */

    public function getRecipientLanguage()
    {
        $recipient = $this->getRecipient();

        if ($recipient && ($language = $recipient->preferredLanguage)) {
            return $language;
        }

        if (($order = $this->getOrder()))
        {
            $customerUser = $order->getCustomer()->getUser();

            if ($customerUser && ($language = $customerUser->preferredLanguage)) {
                return $language;
            }

            return $order->orderLanguage;
        }

        return null;
    }

    public function getMessages()
    {
        if ($this->_messages !== null) {
            return $this->_messages;
        }

        $this->_messages = Support::getInstance()->messageService->getMessagesByTicketId($this->id);

        return $this->_messages;
    }

    // Editing
    // -------------------------------------------------------------------------

    /**
     * @inheritdoc
     */

    public function getIsEditable(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */

    public function getCpEditUrl()
    {
        return UrlHelper::cpUrl('support/tickets/'.$this->id);
    }

    // Indexes
    // -------------------------------------------------------------------------

    protected static function defineSortOptions(): array
    {
        $sortOptions = [
            'support_tickets.dateCreated' => 'Date Created',
            'support_tickets.dateUpdated' => 'Date Updated',
        ];

        return $sortOptions;
    }

    // Events
    // -------------------------------------------------------------------------

    /**
     * 
     */

    public function afterSave(bool $isNew)
    {
        $data = [
            'ticketStatusId'  => $this->ticketStatusId,
            'authorId' => $this->authorId,
            'recipientId'  => $this->recipientId,
            'orderId'  => $this->orderId,
            'deletedOrderReference' => $this->deletedOrderReference,
        ];

        if ($isNew)
        {
            $data['id'] = $this->id;

            Craft::$app->db->createCommand()
                ->insert('{{%support_tickets}}', $data)
                ->execute();
        }

        else
        {
            Craft::$app->db->createCommand()
                ->update('{{%support_tickets}}', $data, [
                    'id' => $this->id
                ])
                ->execute();
        }

        parent::afterSave($isNew);
    }
}
