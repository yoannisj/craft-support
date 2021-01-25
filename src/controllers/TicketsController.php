<?php
/**
 * Support plugin for Craft CMS 3.x
 *
 * Simple support system for tracking, prioritising and solving customer support tickets.
 *
 * @link      https://github.com/lukeyouell
 * @copyright Copyright (c) 2018 Luke Youell
 */

namespace lukeyouell\support\controllers;

use lukeyouell\support\Support;

use Craft;
use craft\elements\Asset;
use craft\helpers\Template;
use craft\web\Controller;
use craft\helpers\ArrayHelper;

use yii\base\InvalidConfigException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;

class TicketsController extends Controller
{
    // Public Properties
    // =========================================================================

    public $settings;

    // Public Methods
    // =========================================================================

    /**
     * 
     */

    public function init()
    {
        parent::init();

        $this->settings = Support::$plugin->getSettings();
        if (!$this->settings->validate()) {
            throw new InvalidConfigException('Support settings don’t validate.');
        }
    }

    /**
     * 
     */

    public function actionIndex()
    {
        return $this->renderTemplate('support/_tickets/index');
    }

    /**
     * 
     */

    public function actionNew()
    {
        $volume = $this->settings->volumeId ? Craft::$app->getVolumes()->getVolumeById($this->settings->volumeId) : null;

        $variables = [
            'volume' => $volume,
            'elementType' => Asset::class,
            'settings' => $this->settings,
        ];

        return $this->renderTemplate('support/_tickets/new', $variables);
    }

    /**
     * 
     */

    public function actionView(string $ticketId = null)
    {
        $this->requireLogin();

        $ticket = Support::getInstance()->ticketService->getTicketById($ticketId);
        $userIdentity = Craft::$app->getUser()->getIdentity();

        if (!$ticket) {
            throw new NotFoundHttpException('Ticket not found');
        }

        $volume = $this->settings->volumeId ? Craft::$app->getVolumes()->getVolumeById($this->settings->volumeId) : null;

        $variables = [
            'ticket'   => $ticket,
            'ticketStatuses' => Support::getInstance()->ticketStatusService->getAllTicketStatuses(),
            'volume' => $volume,
            'assetElementType' => Asset::class,
            'settings' => $this->settings,
        ];

        return $this->renderTemplate('support/_tickets/ticket', $variables);
    }

    /**
     * 
     */

    public function actionCreate()
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $request = Craft::$app->getRequest();

        $supportPlugin = Support::getInstance();
        $settings = $supportPlugin->getSettings();

        // First create ticket
        $ticket = $supportPlugin->ticketService->createTicket($request);
        $message = $request->post('message');

        $success = Craft::$app->getElements()->saveElement($ticket, true, false);

        // require a ticket message upon creation
        if (empty($message))
        {
            $ticket->addError('message', Craft::t('support', 'Message can not be empty.'));
            $success = false;
        }

        if (!$success)
        {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                ]);
            }

            Craft::$app->getSession()->setError('Couldn’t create the ticket.');
            Craft::$app->getUrlManager()->setRouteParams([
                'ticket' => $ticket,
            ]);
        }

        else
        {
            // Ticket created, now create message but don't change ticket status id
            $message = $supportPlugin->messageService->createMessage($ticket->id, $request, false);

            // Handle email notification after message is created
            if ($ticket->ticketStatus->emails) {
                $supportPlugin->mailService->handleEmail($ticket->id);
            }

            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => true,
                ]);
            }

            Craft::$app->getSession()->setNotice('Ticket created.');
        }

        return $this->redirectToPostedUrl();    
    }

    /**
     * 
     */

    public function actionSave()
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $request = Craft::$app->getRequest();
        $ticketId = Craft::$app->security->validateData($request->post('ticketId'));
        $ticketStatusId = $request->post('ticketStatusId');
        $message = $request->post('message');

        $supportPlugin = Support::getInstance();
        $userSession = Craft::$app->getUser();

        if ($ticketId)
        {
            // get existing ticket based on given id
            $ticket = $supportPlugin->ticketService->getTicketById($ticketId);

            if (!$ticket) {
                throw new NotFoundHttpException('Ticket not found');
            }

            if ($ticketStatusId) {
                $supportPlugin->ticketService->changeTicketStatus($ticket, $ticketStatusId);
            }

            $success = Craft::$app->getElements()->saveElement($ticket, false);

            if ($success) {
                Craft::$app->getSession()->setNotice('Ticket updated.');
            }

            else {
                Craft::$app->getSession()->setError('Could not update ticket.');
            }
        }

        return $this->redirectToPostedUrl();
    }

    // =Protected method
    // ========================================================================

}
