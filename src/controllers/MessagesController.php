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
use yii\web\TooManyRequestsHttpException;

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
        $this->requireLogin();

        $supportPlugin = Support::getInstance();
        $request = Craft::$app->getRequest();
        $params = $request->getBodyParams();

        // Security: throttle message creation per user to prevent flooding/abuse.
        $this->enforceMessageRateLimit();

        // Security: never trust a posted authorId — force it to the logged-in user.
        $params['authorId'] = Craft::$app->getUser()->getId();

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

    // Private Methods
    // =========================================================================

    /**
     * Throttles message creation per authenticated user using the cache, to
     * prevent a single account/session from flooding the support system
     * (e.g. an automated vulnerability scanner). Tune the limits as needed.
     *
     * @throws TooManyRequestsHttpException
     */
    private function enforceMessageRateLimit()
    {
        $maxMessages = 8;   // max messages…
        $window      = 60;  // …per this many seconds, per user

        $userId = Craft::$app->getUser()->getId();
        if (!$userId) {
            return;
        }

        $cache = Craft::$app->getCache();
        $cacheKey = ['support', 'message-rate-limit', $userId];
        $count = (int)$cache->get($cacheKey);

        if ($count >= $maxMessages) {
            throw new TooManyRequestsHttpException(
                Craft::t('support', 'You’re sending messages too quickly. Please wait a moment and try again.')
            );
        }

        $cache->set($cacheKey, $count + 1, $window);
    }
}
