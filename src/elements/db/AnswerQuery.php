<?php 

/**
 * Support plugin for Craft CMS 3.x
 *
 * Simple support system for tracking, prioritising and solving customer support tickets.
 *
 * @link      https://github.com/lukeyouell
 * @copyright Copyright (c) 2018 Luke Youell
 */

namespace lukeyouell\support\elements\db;

use Craft;
use craft\elements\db\ElementQuery;

/**
 * 
 */

class AnswerQuery extends ElementQuery
{
    // =Static
    // ========================================================================

    // =Properties
    // ========================================================================

    /**
     * @var integer
     */

    public $authorId;

    // =Public Methods
    // ========================================================================

    /**
     * Sets the authorId criteria
     */

    public function authorId( $value )
    {
        if (is_numeric($value)) {
            $this->authorId = $value;
        }

        else if (is_null($value)) {
            $this->authorId = null;
        }

        else {    
            throw new InvalidConfigException("Invalid `authorId` criteria.");
        }
    }

    /**
     * Sets the author criteria (delegates to authorId)
     */

    public function author( $value )
    {
        if ($value instanceof User) {
            $value = $value->id;
        }

        $this->authorId($value);
    }

    // =Protected Methods
    // ========================================================================

    /**
     * @inheritdoc
     */

    protected function beforePrepare(): bool
    {
        // does the same as '$this->joinTable', but without needing to know the table's actual name
        $joinTable = "{{%support_answers}} support_answers";

        $this->query->innerJoin($joinTable, "[[support_answers.id]] = [[subquery.elementsId]]");
        $this->subQuery->innerJoin($joinTable, "[[support_answers.id]] = [[elements.id]]");

        // select fields from own table
        $this->query->addSelect('support_answers.authorId AS authorId');

        // select localized fields from the content table
        $this->query->addSelect('content.support_answer_text AS text');

        // Apply custom criteria

        if ($this->authorId) {
            $this->subQuery->andWhere(Db::parseParam('support_answers.authorId', $this->authorId));
        }

        return parent::beforePrepare();
    }

    // =Private Methods
    // ========================================================================
}