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
use craft\web\Controller;
use craft\helpers\ArrayHelper;

use yii\base\InvalidConfigException;

class MessagesController extends Controller
{
    // Public Properties
    // =========================================================================

    public $settings;

    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();

        $this->settings = Support::$plugin->getSettings();
        if (!$this->settings->validate()) {
            throw new InvalidConfigException('Support settings don’t validate.');
        }
    }

    public function actionNewMessage()
    {
        $this->requirePostRequest();

        $supportPlugin = Support::getInstance();
        $request = Craft::$app->getRequest();
        $params = $request->getBodyParams();

        // Validate ticketId
        $ticketId = Craft::$app->security->validateData(ArrayHelper::getValue($params, 'ticketId'));
        $params = array_merge([], $params, [ 'ticketId' => $ticketId ]);

        $message = $supportPlugin->messageService->createMessage($params);

        if (!$message || $message->hasErrors())
        {
            Craft::$app->getSession()->setError('Couldn’t send the message.');
            Craft::$app->getUrlManager()->setRouteParams([
                'message' => $message,
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice('Message sent.');

        return $this->redirectToPostedUrl();
    }

    public function actionDeleteMessage()
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $messageId = $request->post('messageId');

        if ($messageId) {
            $res = Support::getInstance()->messageService->deleteMessage($messageId);

            if (!$res) {
                Craft::$app->getSession()->setError('Couldn’t delete the message.');
            } else {
                Craft::$app->getSession()->setNotice('Message deleted.');
            }
        }

        return $this->redirectToPostedUrl();
    }
}
