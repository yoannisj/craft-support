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
use lukeyouell\support\records\Email as EmailRecord;

use Craft;
use craft\base\Component;
use craft\mail\Message;
use craft\web\View;
use craft\helpers\StringHelper;
use craft\helpers\ArrayHelper;
use craft\helpers\UrlHelper;
use craft\helpers\App as AppHelper;

use yii\base\InvalidConfigException;
use yii\helpers\Markdown;

/**
 *
 */

class MailService extends Component
{
    // Public Properties
    // =========================================================================

    /**
     *
     */

    public $settings;

    /**
     *
     */

    public $mailSettings;

    // Public Methods
    // =========================================================================

    /**
     *
     */

    public function init()
    {
        parent::init();

        $this->settings = Support::$plugin->getSettings();
        $this->mailSettings = AppHelper::mailSettings();
    }

    /**
     *
     */

    public function handleEmail($ticketId = null)
    {
        // get ticket element
        if ($ticketId) $ticket = Support::getInstance()->ticketService->getTicketById($ticketId);
        if (!$ticket || !$ticket->id) return;

        $emails = $ticket->ticketStatus->emails;

        // set language for email recipient
        $originalLanguage = Craft::$app->language;
        $originalSite = Craft::$app->getSites()->getCurrentSite();
        $recipientLanguage = null;

        foreach ($emails as $email)
        {
            if ($email->enabled)
            {
                // get recipient's language
                if ($email->recipientType == EmailRecord::TYPE_RECIPIENT) {
                    $recipientLanguage = $recipientLanguage ?? $ticket->getRecipientLanguage();
                } else if ($email->recipientType == EmailRecord::TYPE_AUTHOR) {
                    $recipientLanguage = $recipientLanguage ?? $ticket->getAuthorLanguage();
                }

                // Render the email template in the recipient's preferred language
                if ($recipientLanguage) {
                    Craft::$app->language = $recipientLanguage;
                }

                $this->sendEmail($email, $ticket);

                // reset language
                Craft::$app->language = $originalLanguage;
            }

            $recipientLanguage = null;
        }
    }
    
    /**
     *
     */

    public function sendEmail($email, $ticket)
    {
        $mailer = Craft::$app->getMailer();

        $message = (new Message())
            ->setFrom([$this->getFromEmail() => $this->getFromName()])
            ->setSubject($this->getSubject($email, $ticket))
            ->setHtmlBody($this->getTemplateHtml($email, $ticket));

        $toEmails = $this->getToEmails($email, $ticket);

        foreach ($toEmails as $toEmail)
        {
            $message->setTo($toEmail);
            $mailer->send($message);
        }
    }

    /**
     *
     */

    public function getFromEmail()
    {
        $fromEmail = $this->settings->fromEmail ?: $this->mailSettings->fromEmail;
        return Craft::parseEnv($fromEmail);
    }

    /**
     *
     */

    public function getFromName()
    {
        $fromName = $this->settings->fromName ?: $this->mailSettings->fromName;
        return Craft::parseEnv($fromName);
    }

    /**
     *
     */

    public function getToEmails($email, $ticket)
    {
        $toEmail = '';

        if ($email->recipientType == EmailRecord::TYPE_AUTHOR) {
            $toEmail = $ticket->author->email;
        } elseif ($email->recipientType == EmailRecord::TYPE_RECIPIENT) {
            $toEmail = $ticket->recipientEmail;
        } elseif ($email->recipientType == EmailRecord::TYPE_CUSTOM) {
            $toEmail = $email->to;
        }

        if (empty($toEmail)) {
            throw new InvalidConfigException('Could not determine recipient email addresse(s)');
        }

        // accept comma-separated string
        $emails = is_string($toEmail) ? StringHelper::split($toEmail) : $toEmails;

        // accept environment variables
        return array_map('Craft::parseEnv', $emails);
    }

    /**
     *
     */

    public function getSubject($email, $ticket)
    {
        // Replace keys with ticket values
        $subject = Craft::$app->getView()->renderObjectTemplate($email->subject, $ticket);
        return $subject;
    }

    /**
     *
     */

    public function getTemplateHtml($email, $ticket)
    {
        if ($email->templatePath)
        {
            $variables = [
                'email' => $email,
                'ticket' => $ticket,
            ];

            // Set Craft to the site template mode
            $view = Craft::$app->getView();
            $oldTemplateMode = $view->getTemplateMode();
            $view->setTemplateMode($view::TEMPLATE_MODE_SITE);

            // Render template
            $html = Craft::$app->view->renderTemplate($email->templatePath, $variables);

            // Set Craft back to the previous template mode
            $view->setTemplateMode($oldTemplateMode);

            return $html;
        }

        return null;
    }
}
