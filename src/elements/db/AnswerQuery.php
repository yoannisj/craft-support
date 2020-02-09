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

    // =Public Methods
    // ========================================================================

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

        // pull in the response message from the content table
        $this->query->addSelect('content.support_answer_text AS text');

        return parent::beforePrepare();
    }

    // =Private Methods
    // ========================================================================
}