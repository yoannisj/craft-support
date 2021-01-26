<?php
/**
 * Support plugin for Craft CMS 3.x
 *
 * Simple support system for tracking, prioritising and solving customer support tickets.
 *
 * @link      https://github.com/lukeyouell
 * @copyright Copyright (c) 2018 Luke Youell
 */

namespace lukeyouell\support\variables;

use lukeyouell\support\Support;
use lukeyouell\support\elements\Message;
use lukeyouell\support\elements\Ticket;
use lukeyouell\support\elements\Answer;
use lukeyouell\support\elements\db\MessageQuery;
use lukeyouell\support\elements\db\TicketQuery;
use lukeyouell\support\elements\db\AnswerQuery;

use Craft;

class SupportVariable
{
    // Public Methods
    // =========================================================================

    public function tickets(array $criteria = []): TicketQuery
    {
        $query = Ticket::find();
        Craft::configure($query, $criteria);

        return $query;
    }

    public function messages(array $criteria = []): MessageQuery
    {
        $query = Message::find();
        Craft::configure($query, $criteria);

        return $query;
    }

    public function ticketStatuses()
    {
        return Support::getInstance()->ticketStatusService->getAllTicketStatuses();
    }

    public function defaultTicketStatus()
    {
        return Support::getInstance()->ticketStatusService->getDefaultTicketStatus();
    }

    public function getTicketStatusById($id)
    {
        return Support::getInstance()->ticketStatusService->getTicketStatusById($id);
    }

    public function answers( array $criteria = []): AnswerQuery
    {
        $query = Answer::find();
        Craft::configure($query, $criteria);

        return $query;
    }
}
