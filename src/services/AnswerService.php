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

use yii\base\Exception;
use yii\web\ForbiddenHttpException;

use Craft;
use craft\base\Component;

use lukeyouell\support\elements\Answer;

/**
 * 
 */

class AnswerService extends Component
{
    // =Public Methods
    // ========================================================================

    /**
     *
     */

    public function getAnswerById( int $answerId, int $siteId = null )
    {
        $query = Answer::find();
        $query->id = $answerId;
        $query->siteId = $siteId;

        return $query->one();
    }

}