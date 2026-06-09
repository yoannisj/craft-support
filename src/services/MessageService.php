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
use lukeyouell\support\elements\Message;
use lukeyouell\support\elements\db\MessageQuery;

use Craft;
use craft\base\Component;
use craft\helpers\ArrayHelper;

class MessageService extends Component
{
    // Public Methods
    // =========================================================================

    public function getMessageById($messageId = null)
    {
        if ($messageId) {
          $query = new MessageQuery(Message::class);
          $query->id = $messageId;

          return $query->one();
        }

        return null;
    }

    public function getMessagesByTicketId($ticketId = null)
    {
        if ($ticketId) {
          $query = new MessageQuery(Message::class);
          $query->ticketId = $ticketId;

          return $query->all();
        }

        return null;
    }

    public function createMessage(array $params = [], bool $updateTicket = true)
    {
        $craftElements = Craft::$app->getElements();
        $supportPlugin = Support::getInstance();

        $message = new Message();

        // populate message with param values
        $message->ticketId = ArrayHelper::getValue($params, 'ticketId');
        $message->content = ArrayHelper::getValue($params, 'message');
        $message->ticketId = ArrayHelper::getValue($params, 'ticketId');
        // Security: never trust a posted authorId. Use the logged-in user when
        // there is one (web requests); only fall back to a passed authorId for
        // headless/CLI callers with no identity. Prevents author spoofing.
        $identity = Craft::$app->getUser()->getIdentity();
        $message->authorId = $identity
            ? $identity->id
            : ArrayHelper::getValue($params, 'authorId');

        $attachments = ArrayHelper::getValue($params, 'attachments');
        $message->attachmentIds = $attachments ? implode(',', $attachments) : null;

        $success = $craftElements->saveElement($message, true, false);

        if ($success && $updateTicket && ($ticket = $message->getTicket()))
        {            
            // Change ticket status if one exists with this enabled
            $newStatus = $supportPlugin->ticketStatusService->getNewMessageTicketStatus();

            if ($newStatus && $newStatus->id) { // will save ticket and update the 'dateUpdated' value
                $supportPlugin->ticketService->changeTicketStatus($ticket, $newStatus->id);
            } else {
                Craft::$app->getElements()->saveElement($ticket); // save to update the 'dateUpdated' value
            }
        }

        return $message;
    }

    public function deleteMessage($messageId = null)
    {
        if ($messageId) {
            $message = $this->getMessageById($messageId);

            if ($message) {
                // Check user is message author
                $owner = $this->isMessageAuthor($message->authorId, Craft::$app->getUser()->getIdentity()->id);

                if ($owner) {
                    Craft::$app->getElements()->deleteElement($message);

                    return true;
                }
            }
        }

        return null;
    }

    public function isMessageAuthor($authorId = null, $userId = null)
    {
        if ($authorId and $userId) {
            if ($authorId === $userId) {
                return true;
            }
        }

        return false;
    }
}
