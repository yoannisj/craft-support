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
use craft\helpers\ArrayHelper;

use yii\base\InvalidConfigException;
use yii\web\NotFoundHttpException;
use yii\web\ForbiddenHttpException;

class TicketService extends Component
{
    // Public Methods
    // =========================================================================

    public function createTicket( $submission = null, bool $validate = false )
    {
        if ($submission)
        {
          $defaultTicketStatus = Support::getInstance()->ticketStatusService->getDefaultTicketStatus();

          $ticket = new Ticket();
          $ticket->ticketStatusId = $defaultTicketStatus['id'];
          $ticket->title = $submission->post('title');

          // get author info
          $userSession = Craft::$app->getUser();

          if ($userSession->isGuest) {
            // redirect user to log-in (passes current url as login's returnUrl)
            $userSession->loginRequired();
            Craft::$app->end();
          }          

          $ticket->authorId = $userSession->getIdentity()->id;
          
          // get recipient info
          $recipientId = $submission->post('recipientId');
          if ($recipientId)
          {
            if (is_array($recipientId)) {
                $recipientId = ArrayHelper::firstValue($recipientId);
            }

            $ticket->recipientId = $recipientId;
          }

          // get commerce info
          $this->populateCommerceFields($ticket);

          Craft::error('AUTHOR ID: ' . $ticket->authorId);
          Craft::error('RECIPIENT ID: ' . $ticket->recipientId);
          Craft::error('ORDER ID: ' . $ticket->orderId.' ('. ($ticket->order->customerId ?? null).')');
          Craft::error('CUSTOMER ID: ' . ($ticket->getCustomer()->id ?? null));

          // recipient defaults to author created on the front-end without linking to an order
          if (!$ticket->recipientId && $submission->getIsSiteRequest()) {
            $ticket->recipientId = $ticket->authorId;
          }

          return $ticket;
        }

        return null;
    }

    public function getTicketById($ticketId = null)
    {
        $userSessionService = Craft::$app->getUser();
        $userId = $userSessionService->getIdentity()->id;
        $canManageTickets = $userSessionService->checkPermission('support-manageTickets');

        if ($ticketId)
        {
            $query = new TicketQuery(Ticket::class);
            $query->id = $ticketId;
            $query->authorId = $canManageTickets ? null : $userId;

            return $query->one();
        }

        return null;
    }

    public function changeTicketStatus($ticket = null, $ticketStatusId = null)
    {
        if ($ticket->id && $ticketStatusId)
        {
            $status = Support::getInstance()->ticketStatusService->getTicketStatusById($ticketStatusId);

            if (!$status->id) {
                throw new NotFoundHttpException('Ticket status not found');
            }

            $ticket->ticketStatusId = $status->id;

            Craft::$app->getElements()->saveElement($ticket, false);

            // Handle ticket status emails after saving ticket
            if ($status->emails) {
                Support::getInstance()->mailService->handleEmail($ticket->id);
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

    public function populateCommerceFields( Ticket &$ticket )
    {
        if (!Craft::$app->getPlugins()->isPluginEnabled('commerce')) {
            return;
        }

        $commerce = Craft::$app->getPlugins()->getPlugin('commerce');
        $request = Craft::$app->getRequest();

        // get order data
        $orderId = $request->getBodyParam('orderId');
        $orderReference = $request->getBodyParam('orderReference');
        $order = null;

        if (is_array($orderId)) {
            $orderId = ArrayHelper::firstValue($orderId);
        }

        if (is_array($orderReference)) {
            $orderReference = ArrayHelper::firstValue($orderReference);
        }

        $orderId = trim($orderId);
        $orderReference = trim($orderReference);

        if (!empty($orderId) || !empty($orderReference))
        {
            $orderQuery = \craft\commerce\elements\Order::find();
            $orderQuery->id = $orderId;
            $orderQuery->reference = $orderReference;
            $order = $orderQuery->one();

            if (!$order) {
                throw new NotFoundHttpException('Could not find order with reference ' . $orderReference);
            }

            $ticket->orderId = $order->id;
            $ticket->orderReference = $order->reference;
        }

        // get customer data
        $customer = null;

        if ($ticket->recipientId) {
            $customer = $commerce->customers->getCustomerByUserId($ticket->recipientId);
        } else if ($order) {
            $customer = $order->getCustomer();
        } else if ($request->getIsSiteRequest()) {
            $customer = $commerce->customers->getCustomer();
        }

        if ($order) $ticket->orderId = $order->id;
        if ($customer) $ticket->recipientId = $customer->userId;
    }
}
