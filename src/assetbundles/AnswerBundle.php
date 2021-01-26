<?php
/**
 * Support plugin for Craft CMS 3.x
 *
 * Simple support system for tracking, prioritising and solving customer support tickets.
 *
 * @link      https://github.com/lukeyouell
 * @copyright Copyright (c) 2018 Luke Youell
 */

namespace lukeyouell\support\assetbundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class AnswerBundle extends AssetBundle
{
    public function init()
    {
        // define the path that your publishable resources live
        $this->sourcePath = '@lukeyouell/support/assetbundles';

        // define the dependencies
        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/answer.js',
        ];

        $this->css = [
            'css/answer.css',
        ];

        parent::init();
    }
}
