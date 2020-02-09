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

use yii\base\InvalidConfigException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\ServerErrorHttpException;

use Craft;
use craft\errors\InvalidElementException;
use craft\base\Element;
use craft\elements\Asset;
use craft\web\Controller;
use craft\web\Response;
use craft\helpers\Template;

use lukeyouell\support\Support;
use lukeyouell\support\elements\Answer;
use lukeyouell\support\assetbundles\AnswerBundle;

/**
 *
 */

class AnswersController extends Controller
{
    // =Properties
    // ========================================================================

    /**
     * @var bool
     */

    public $allowAnonymous = false;

    /**
     * @var \lukeyouell\support\models\Settings
     */

    public $settings;

    // =Public Methods
    // ========================================================================

    /**
     * @inheritdoc
     */

    public function init()
    {
        parent::init();

        $this->settings = Support::$plugin->getSettings();

        if (!$this->settings->validate()) {
            throw new InvalidConfigException('Support settings don’t validate.');
        }
    }

    // =Actions
    // ------------------------------------------------------------------------

    /**
     * @return \craft\web\Response
     */

    public function actionIndex()
    {
        if (!Craft::$app->getUser()->checkPermission('support-manageAnswers')) {
            throw new ForbiddenHttpException('User not permitted to manage support answers');
        }

        return $this->renderTemplate('support/_answers/index');
    }

    /**
     * @return \craft\web\Response
     */

    public function actionEdit( string $answerId = null, string $siteHandle = null, Answer $answer = null ): Response
    {
        $variables = [
            'settings' => $this->settings,
            'siteHandle' => $siteHandle,
            'answerId' => $answerId,
            'answer' => $answer,
        ];

        $this->prepSiteVariables($variables);
        $this->prepEditableSiteVariables($variables);
        $this->prepAnswerVariables($variables);
        $this->prepAssetVariables($variables);

        $this->enforceAnswerEditPermission($variables['answer']);

        $site = $variables['site'];
        $answer = $variables['answer'];

        $variables['bodyClass'] = 'edit-answer site--' . $site->handle;

        if ($answer->id === null) {
            $variables['title'] = 'Create new Answer';
        } else {
            $variables['docTitle'] = $variables['title'] = trim($answer->title) ?: 'Edit Answer';
        }

        // get site segment for url variables
        $siteSegment = '';

        if (Craft::$app->getIsMultisite() && Craft::$app->getSites()->getCurrentSite()->id != $site->id) {
            $siteSegment = '/'.$site->handle;
        }

        // Get preview variables
        $variables['showPreviewBtn'] = false;

        // Set the base CP edit URL
        $variables['baseCpEditUrl'] = 'support/answers/{id}';

        // Set the "Continue Editing" URL
        $variables['continueEditingUrl'] = $variables['baseCpEditUrl'] . $siteSegment;

        // Set the "Save and add another" URL
        $variables['nextAnswerUrl'] = 'support/answers/new' . $siteSegment;

        // Render the template!
        $this->getView()->registerAssetBundle(AnswerBundle::class);

        return $this->renderTemplate('support/_answers/edit', $variables);
    }

    /**
     *
     */

    public function actionSave()
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        // get answer from post request, and make sure current user can edit it
        $answer = $this->getAnswerModel();
        $this->enforceAnswerEditPermission($answer);

        // support duplicating an existing answer
        if ($request->getBodyParam('duplicate'))
        {
            try {
                $answer = Craft::$app->getElements()->duplicateElement($answer);
            }

            catch (InvalidElementException $e)
            {
                $clone = $e->element;

                if ($request->getAcceptsJson())
                {
                    return $this->asJson([
                        'success' => false,
                        'errors' => $clone->getErrors(),
                    ]);
                }

                Craft::$app->getSession()->setError('Couldn’t duplicate answer.');

                // Send the original answer back to the template, with any validation errors on the clone
                $answer->addErrors($clone->getErrors());

                Craft::$app->getUrlManager()->setRouteParams([
                    'answer' => $answer,
                ]);

                return null;
            }

            catch (\Throwable $e) {
                throw new ServerErrorHttpException('An error occurred when duplicating the answer.', 0, $e);
            }
        }

        // Populate the answer with post data
        $this->populateAnswerModel($answer);

        // Save the answer element
        if ($answer->enabled && $answer->enabledForSite) {
            $answer->setScenario(Element::SCENARIO_LIVE);
        }

        if (!Craft::$app->getElements()->saveElement($answer))
        {
            if ($request->getAcceptsJson())
            {
                return $this->asJson([
                    'success' => false,
                    'errors' => $answer->getErrors(),
                ]);
            }

            Craft::$app->getSession()->setError('Couldn’t save answer.');

            // Send the answer back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'answer' => $answer
            ]);

            return null;
        }

        // Handle successful web response
        if ($request->getAcceptsJson())
        {
            return $this->asJson([
                'success' => true,
                'id' => $answer->id,
                'title' => $answer->title,
                'status' => $answer->getStatus(),
                'url' => $answer->getUrl(),
                'cpEditUrl' => $answer->getCpEditUrl()
            ]);
        }

        Craft::$app->getSession()->setNotice('Answer saved.');

        // Send the answer to the redirect url
        return $this->redirectToPostedUrl($answer);
    }

    /**
     *
     */

    public function actionDelete()
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        // Get answer from post request
        $answerId = $request->getRequiredBodyParam('answerId');
        $answer = Support::getInstance()->answerService->getAnswerById($answerId);

        if (!$answer) {
            throw new NotFoundHttpException('Answer not found');
        }

        // Make sure user has permission to do this
        $this->requirePermission('support-deleteAnswers');

        // Delete answer
        if (!Craft::$app->getElements()->deleteElement($answer))
        {
            if ($request->getAcceptsJson()) {
                return $this->asJson([ 'success' => false ]);
            }

            Craft::$app->getSession()->setError('Couldn’t delete answer.');

            // Send the answer back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'answer' => $answer
            ]);

            return null;
        }

        // Handle successful response
        if ($request->getAcceptsJson()) {
            return $this->asJson([ 'success' => true ]);
        }

        Craft::$app->getSession()->setNotice('Answer deleted.');

        return $this->redirectToPostedUrl($answer);
    }

    // =Protected Methods
    // ========================================================================

    /**
     *
     */

    protected function enforceAnswerEditPermission( Answer $answer = null )
    {
        if ($answer && Craft::$app->getIsMultisite()) {
            $this->requirePermission('editSite:' . $answer->getSite()->uid);
        }

        $this->requirePermission('support-manageAnswers');
    }

    /**
     *
     */

    protected function prepSiteVariables( array &$variables = [] )
    {
        $siteHandle = $variables['siteHandle'] ?? null;

        if (!empty($siteHandle))
        {
            $variables['site'] = Craft::$app->getSites()->getSiteByHandle($siteHandle);

            if (!$variables['site']) {
                throw new NotFoundHttpException('Invalid site handle: ' . $siteHandle);
            }
        }

        else {
            $variables['site'] = Craft::$app->getSites()->getCurrentSite();
        }

        return $variables;
    }

    /**
     *
     */

    protected function prepEditableSiteVariables( array &$variables = [] )
    {
        if (Craft::$app->getIsMultiSite()) {
            // Only use the sites that the user has access to
            $variables['siteIds'] = Craft::$app->getSites()->getEditableSiteIds();
        }

        else {
            /** @noinspection PhpUnhandledExceptionInspection */
            $variables['siteIds'] = [ Craft::$app->getSites()->getPrimarySite()->id ];
        }

        if (!$variables['siteIds']) {
            throw new ForbiddenHttpException('User not permitted to edit content in any sites');
        }

        return $variables;
    }

    /** 
     *
     */

    protected function prepAnswerVariables( array &$variables = [] )
    {
        $site = $variables['site'] ?? Craft::$app->getSites()->getCurrentSite();
        $answer = $variables['answer'] ?? null;

        if (empty($answer))
        {
            $answerId = $variables['answerId'] ?? null;

            if (!empty($answerId)) {
                $answer = Support::getInstance()->answerService->getAnswerById($answerId, $site->id);

                if (!$answer) {
                    throw new NotFoundHttpException('Answer not found');
                }
            }

            else
            {
                $answer = new Answer();
                $answer->enabled = true;
                $answer->siteId = $site->id;
            }

            $variables['answer'] = $answer;
            $variables['answerId'] = $answer->id;
        }

        return $variables;
    }

    /**
     *
     */

    protected function prepAssetVariables( array &$variables = [] )
    {
        $volume = null;
        $volumeId = $this->settings->volumeId;

        if ($volumeId){
            $volume = Craft::$app->getVolumes()->getVolumeById($volumeId);
        }

        $variables['elementType'] = Asset::class;
        $variables['volume'] = $volume;
        $variables['volumeId'] = $volumeId;

        return $variables;
    }

    /**
     * 
     */

    protected function getAnswerModel()
    {
        $request = Craft::$app->getRequest();

        $answerId = $request->getBodyParam('answerId');
        $siteId = $request->getBodyParam('siteId');

        if ($answerId)
        {
            $answer = Support::getInstance()->answerService->getAnswerById($answerId, $siteId);
            if (!$answer) {
                throw new NotFoundHttpException('Answer not found');
            }
        }

        else
        {
            $answer = new Answer();

            if ($siteId) {
                $answer->siteId = $siteId;
            }
        }

        return $answer;
    }

    /**
     * 
     */

    protected function populateAnswerModel( Answer &$answer )
    {
        $request = Craft::$app->getRequest();

        $answer->enabled = (bool)$request->getBodyParam('enabled', $answer->enabled);
        $answer->title = $request->getBodyParam('title', $answer->title);
        $answer->text = $request->getBodyParam('text', '');

        return $answer;
    }
}