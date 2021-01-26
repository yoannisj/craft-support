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

use Craft;
use craft\base\Element;
use craft\elements\db\ElementQueryInterface;
use craft\elements\actions\Delete;
use craft\elements\actions\Restore;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\helpers\ArrayHelper;

use lukeyouell\support\Support;
use lukeyouell\support\models\Settings as SupportSettings;
use lukeyouell\support\elements\db\AnswerQuery;

/**
 * Answer element class
 */

class Answer extends Element
{

    // =Static
    // ========================================================================


    /**
     * @inheritdoc
     */

    public static function displayName(): string
    {
        return Craft::t('support', 'Answer');
    }

    /**
     * @inheritdoc
     */

    public static function pluralDisplayName(): string
    {
        return Craft::t('support', 'Answers');
    }

    /**
     * @inheritdoc
     */

    public static function find(): ElementQueryInterface
    {
        return new AnswerQuery(static::class);
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
        return true;
    }

    /**
     * @inheritdoc
     */

    protected static function defineActions( string $source = null ): array
    {
        $userSession = Craft::$app->getUser();
        $canManageAnswers = $userSession->checkPermission('support-manageAnswers');

        $actions = [];

        if ($canManageAnswers)
        {
            $actions[] = Craft::$app->getElements()->createAction([
                'type'                => Delete::class,
                'confirmationMessage' => Craft::t('support', 'Are you sure you want to delete the selected answers?'),
                'successMessage'      => Craft::t('support', 'Answers deleted.'),
            ]);

            $actions[] = Craft::$app->getElements()->createAction([
                'type'                  => Restore::class,
                'successMessage'        => Craft::t('support', 'Answers restored.'),
                'partialSuccessMessage' => Craft::t('support', 'Some answers could not be restored successfully.'),
                'failMessage'           => Craft::t('support', 'Could not restore selected answers.'),
            ]);
        }

        return $actions;
    }

    /**
     * @inheritdoc
     */

    protected static function defineTableAttributes(): array
    {
        return [
            'title' => Craft::t('app', 'Title'),
            'text' => 'Text',
        ];
    }

    /**
     * @inheritdoc
     */

    protected static function defineSources(string $context = null): array
    {
        return [
            [
                'key' => '*',
                'label' => 'All Answers',
                'criteria' => []
            ],
        ];
    }

    // =Properties
    // ========================================================================

    /**
     * @var string
     */

    public $text = '';

    /**
     * @var int
     */

    public $authorId;

    // =Public Methods
    // ========================================================================

    // =Fields
    // ------------------------------------------------------------------------

    /**
     * @inheritdoc
     */

    public function extraFields()
    {
        $fields = parent::extraFields();

        $fields[] = 'author';

        return $fields;
    }

    /**
     * Getter method for the `author` field
     */

    public function getAuthor()
    {
        if ($this->authorId !== null) {
            return Craft::$app->getUsers()->getUserById($this->authorId);            
        }

        return null;
    }

    // =Editing
    // ------------------------------------------------------------------------

    /**
     * @inheritdoc
     */

    public function getIsEditable(): bool
    {
        if ($this->siteId && Craft::$app->getIsMultisite()) {
            return Craft::$app->getUser()->checkPermission('editSite:' . $this->getSite()->uid);
        }

        return Craft::$app->getUser()->checkPermission('supports-manageAnswers');
    }

    /**
     * @inheritdoc
     */

    public function getEditorHtml(): string
    {
        $view = Craft::$app->getView();

        $html = '';

        // add 'Title' field to editor HUD
        // (added after message field so both are included at the editor's top, in reversed order)
        $html .= $view->renderTemplateMacro('_includes/forms', 'textField', [
            [
                'label' => Craft::t('app', 'Title'),
                'id' => 'title',
                'name' => 'title',
                'value' => $this->title,
                'errors' => $this->getErrors('title'),
                'first' => true,
                'autofocus' => true,
                'required' => true
            ]
        ]);

        // add 'Message' field to editor HUD
        $html .= $view->renderTemplateMacro('_includes/forms', 'textareaField', [
            [
                'label' => 'Message',
                'siteId' => $this->siteId,
                'id' => 'text',
                'name' => 'text',
                'value' => $this->text,
                'errors' => $this->getErrors('text'),
                'first' => true,
                'autofocus' => false,
                'required' => true,
                'rows' => 6,
            ]
        ]);

        $html .= parent::getEditorHtml();

        return $html;
    }

    /**
     * @inheritdoc
     */

    public function getCpEditUrl()
    {
        $url = UrlHelper::cpUrl('support/answers/'.$this->id);

        if (Craft::$app->getIsMultisite()) {
            $url .= '/' . $this->getSite()->handle;
        }

        return $url;
    }

    // =Content
    // ------------------------------------------------------------------------

    /**
     * @inheritdoc
     */

    public function getSupportedSites(): array
    {
        $settings = Support::getInstance()->getSettings();
        
        /** @var Site[] $allSites */
        $allSites = ArrayHelper::index(Craft::$app->getSites()->getAllSites(), 'id');
        $sites = [];

        foreach ($settings->answerSites as $siteId)
        {
            switch ($settings->answerPropagationMethod)
            {
                case SupportSettings::PROPAGATION_METHOD_NONE:
                    $include = ($siteId == $this->siteId);
                    break;
                case SupportSettings::PROPAGATION_METHOD_SITE_GROUP:
                    $include = ($allSites[$siteId]->groupId == $allSites[$this->siteId]->groupId);
                    break;
                case SupportSettings::PROPAGATION_METHOD_LANGUAGE:
                    $include = ($allSites[$siteId]->language == $allSites[$this->siteId]->language);
                    break;
                default:
                    $include = true;
                    break;
            }

            if ($include) {
                $sites[] = $siteId;
            }
        }

        return $sites;
    }

    // =Events
    // ------------------------------------------------------------------------

    /**
     * @inheritdoc
     */

    public function afterSave( bool $isNew )
    {
        if ($isNew)
        {
            // Insert element field values in answers table
            Craft::$app->db->createCommand()
                ->insert('{{%support_answers}}', [
                    'id' => $this->id,
                    'authorId' => $this->authorId,
                ])
                ->execute();
        }

        else
        {
            // Update element field values in answers table
            Craft::$app->db->createCommand()
                ->update('{{%support_answers}}', [
                    'authorId' => $this->authorId,
                ], [ 'id' => $this->id ])
                ->execute();
        }

        // Save localized field values in content table
        $contentTable = Craft::$app->getContent()->contentTable;
        Craft::$app->db->createCommand()
            ->update($contentTable, [
                'support_answer_text' => $this->text,
            ], [ 'id' => $this->contentId ])
            ->execute();

        parent::afterSave($isNew);
    }

    // =Protected Methods
    // ========================================================================

    /**
     * @inheritdoc
     */

    protected function tableAttributeHtml(string $attribute): string
    {
        switch ($attribute)
        {
            case 'text':
                return StringHelper::safeTruncate($this->text, 90, '…');
        }

        return parent::tableAttributeHtml($attribute);
    }
}
