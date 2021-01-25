<?php
/**
 * Support plugin for Craft CMS 3.x
 *
 * Simple support system for tracking, prioritising and solving customer support tickets.
 *
 * @link      https://github.com/lukeyouell
 * @copyright Copyright (c) 2018 Luke Youell
 */

namespace lukeyouell\support\models;

use lukeyouell\support\Support;

use Craft;
use craft\base\Model;
use craft\base\VolumeInterface;

/**
 * Support Settings Model
 *
 * This is a model used to define the plugin's settings.
 *
 * Models are containers for data. Just about every time information is passed
 * between services, controllers, and templates in Craft, it’s passed via a model.
 *
 * https://craftcms.com/docs/plugins/models
 *
 * @author    Luke Youell
 * @package   Support
 * @since     1.0.0
 */
class Settings extends Model
{
    // =Static
    // =========================================================================

    const PROPAGATION_METHOD_NONE = 'none';
    const PROPAGATION_METHOD_SITE_GROUP = 'siteGroup';
    const PROPAGATION_METHOD_LANGUAGE = 'language';

    // Public Properties
    // =========================================================================

    public $pluginNameOverride;

    public $fromEmail;

    public $fromName;

    public $attachments = false;

    public $volumeId;

    public $volumeSubpath = 'attachments/{id}';

    private $_answerSites = '*';

    public $answerPropagationMethod = 'all';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */

    public function attributes()
    {
        $names = parent::attributes();

        $names[] = 'answerSites';

        return $names;
    }

    /**
     * @inheritdoc
     */

    public function setAnswerSites( $value )
    {
        $this->_answerSites = $value;
    }

    /**
     * @inheritdoc
     */

    public function getAnswerSites()
    {
        if ($this->_answerSites == '*' || $this->_answerSites == 'all') {
            $this->_answerSites =  Craft::$app->getSites()->getAllSiteIds();
        }

        return $this->_answerSites;
    }

    /**
     * Returns the validation rules for attributes.
     *
     * Validation rules are used by [[validate()]] to check if attribute values are valid.
     * Child classes may override this method to declare different validation rules.
     *
     * More info: http://www.yiiframework.com/doc-2.0/guide-input-validation.html
     *
     * @return array
     */
    public function rules()
    {
        return [
            [['attachments'], 'boolean'],
            [['pluginNameOverride', 'fromEmail', 'fromName', 'volumeSubpath'], 'string'],
            [['volumeId'], 'number', 'integerOnly' => true],
            [['answerSites'], 'validateSitesSelect'],
        ];
    }

    /**
     * 
     */

    public function validateSitesSelect( $attribute, $params, $validator )
    {
        $value = $this->$attribute;
        $isValid = true;

        if (is_array($value))
        {
            foreach ($value as $val)
            {
                if (!is_numeric($val))
                {
                    $isValid = false;
                    break;
                }
            }
        }

        else if ($value != '*') {
            $isValid = false;
        }

        if (!$isValid) {
            $this->addError($attribute, Craft::t('support', 'Answer sites must be the "*" string or an array of site ids.'));
        }
    }
}
