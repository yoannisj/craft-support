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
use lukeyouell\support\elements\db\TicketQuery;

use Craft;
use craft\base\Component;
use craft\errors\ElementNotFoundException;
use craft\helpers\ArrayHelper;

use craft\commerce\elements\Order;

use yii\base\InvalidConfigException;
use yii\web\NotFoundHttpException;
use yii\web\ForbiddenHttpException;

class TicketService extends Component
{
    // Public Methods
    // =========================================================================

    public function createTicket( array $params )
    {
        $supportPlugin = Support::getInstance();
        $defaultTicketStatus = $supportPlugin->ticketStatusService->getDefaultTicketStatus();

        $ticket = new Ticket();
        $ticket->ticketStatusId = ArrayHelper::getValue($defaultTicketStatus, 'id');
        $ticket->title = ArrayHelper::getValue($params, 'title');

        // get author info
        $authorId = (ArrayHelper::getValue($params, 'authorId') ?:
            ArrayHelper::getValue($params, 'author.id') ?:
            Craft::$app->getUser()->getIdentity()->id);

        if (is_array($authorId)) $authorId = ArrayHelper::firstValue($authorId);
        $ticket->authorId = $authorId;

        // get recipient info
        $recipientId = ArrayHelper::getValue($params, 'recipientId');
        if ($recipientId)
        {
            $ticket->recipientId = (is_array($recipientId) ?
                ArrayHelper::firstValue($recipientId) : $recipientId);
        }

        // populate with commerce info (will link order and set default recipient)
        $this->populateCommerceFields($ticket, $params);

        // unless linked to an order, recipient defaults to author created on the front-end
        if (!$ticket->recipientId && Craft::$app->getRequest()->getIsSiteRequest()) {
            $ticket->recipientId = $ticket->authorId;
        }

        // Save newly created ticket (gives it an ID)
        $success = Craft::$app->getElements()->saveElement($ticket, true, false);

        // Ticket created, now create message but don't change ticket status id
        if ($success)
        {
            // create required first message from params
            $messageParams = array_merge([], $params, [
                'ticketId' => $ticket->id,
                'authorId' => $ticket->authorId,
            ]);

            $message = $supportPlugin->messageService->createMessage($messageParams, false);
            if (!$message)
            {
                $ticket->addError('messages',
                    Craft::t('support', 'New ticket must have a non-empty first message.'));
            }
        }

        return $ticket;
    }

    public function getTicketById($ticketId = null)
    {
        $userSessionService = Craft::$app->getUser();
        $userId = $userSessionService->getIdentity()->id;
        $canManageTickets = $userSessionService->checkPermission('support-manageTickets');

        if ($ticketId)
        {
            $query = new TicketQuery(Ticket::class);
            $query->id($ticketId);

            // limit visible tickets for non-admins
            if (!$canManageTickets) $query->recipientId($userId);

            return $query->one();
        }

        return null;
    }

    public function changeTicketStatus($ticket = null, $newStatus = null)
    {
        if (!$ticket || !$ticket->id) return false;

        $supportPlugin = Support::getInstance();

        if (is_numeric($newStatus)) {
            $newStatus = $supportPlugin->ticketStatusService->getTicketStatusById($newStatus);
        }

        if (!$newStatus || !$newStatus->id) {
            throw new NotFoundHttpException('Ticket status not found');
        }

        $ticket->ticketStatusId = $newStatus->id;
        $success = Craft::$app->getElements()->saveElement($ticket, false);

        if ($success)
        {
            // Handle ticket status emails after saving ticket
            if ($newStatus->emails) {
                $supportPlugin->mailService->handleEmail($ticket->id);
            }

            return true;
        }

        return false;
    }

    public function saveTicketById($ticketId = null)
    {
        if ($ticketId)
        {
            $query = new TicketQuery(Ticket::class);
            $query->id = $ticketId;
            $ticket = $query->one();

            if ($ticket)
            {
                $this->populateCommerceFields($ticket);                
                return Craft::$app->getElements()->saveElement($ticket, true, false);
            }
        }

        return null;
    }

    /**
     *
     */

    public function populateCommerceFields( Ticket &$ticket, array $params = [] )
    {
        if (!Craft::$app->getPlugins()->isPluginEnabled('commerce')) {
            return;
        }

        $commerce = Craft::$app->getPlugins()->getPlugin('commerce');

        // get order data
        $orderId = ArrayHelper::getValue($params, 'orderId');
        $orderReference = ArrayHelper::getValue($params, 'orderReference');
        $order = null;

        if (is_array($orderId)) {
            $orderId = ArrayHelper::firstValue($orderId);
        } else if (is_array($orderReference)) {
            $orderReference = ArrayHelper::firstValue($orderReference);
        }

        $orderId = trim($orderId);
        $orderReference = trim($orderReference);

        if (!empty($orderId) || !empty($orderReference))
        {
            $ticket->orderId = $orderId;
            $ticket->orderReference = $orderReference;

            $orderQuery = Order::find();
            if (!empty($orderId)) $orderQuery->id($orderId);
            else if (!empty($orderReference)) $orderQuery->reference($orderReference);
            $order = $orderQuery->one();
        }

        // get customer data
        $customer = null;

        if ($ticket->recipientId) {
            $customer = $commerce->customers->getCustomerByUserId($ticket->recipientId);
        } else if ($order) {
            $customer = $order->getCustomer();
        } else if (Craft::$app->getRequest()->getIsSiteRequest()) {
            $customer = $commerce->customers->getCustomer();
        }

        if ($customer) $ticket->recipientId = $customer->userId;
    }
}
