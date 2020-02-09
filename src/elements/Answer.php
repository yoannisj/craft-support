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
use craft\elements\actions\Restore;
use craft\helpers\StringHelper;


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
        return [
            Restore::class,
        ];
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

    // =Properties
    // ========================================================================

    /**
     * @var string
     */

    public $text = '';

    // =Public Methods
    // ========================================================================

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
     * 
     */

    public function getCpEditUrl()
    {
        $siteSegment = $this->siteId ? '/'.$this->getSite()->handle : '';
        return 'support/answers'.$this->id.$siteSegment;
    }

    // =Events
    // ------------------------------------------------------------------------

    /**
     * @inheritdoc
     */

    public function afterSave( bool $isNew )
    {
        $contentTable = Craft::$app->getContent()->contentTable;

        Craft::$app->db->createCommand()
            ->update($contentTable, [
                'support_answer_text' => $this->text,
            ], [
                'id' => $this->contentId,
                // 'elementId' => $this->id,
                // 'siteId' => $this->siteId,
            ])
            ->execute();

        if ($isNew)
        {
            Craft::$app->db->createCommand()
                ->insert('{{%support_answers}}', [
                    'id' => $this->id,
                ])
                ->execute();
        }

        else
        {
            Craft::$app->db->createCommand()
                ->update('{{%support_answers}}', [
                ], ['id' => $this->id])
                ->execute();
        }

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
