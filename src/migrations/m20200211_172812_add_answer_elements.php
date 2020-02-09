<?php

namespace yoannisj\tailor\migrations;

use Craft;
use craft\db\Migration;

/**
 * m200211_172812_add_answer_elements migration.
 */

class m200211_172812_add_answer_elements extends Migration
{
    /**
     * @inheritdoc
     */

    public function safeUp()
    {
        // Create new table for answers
        $this->createTable(
            '{{%support_answers}}',
            [
                'id'             => $this->primaryKey(),
                'dateCreated'    => $this->dateTime()->notNull(),
                'dateUpdated'    => $this->dateTime()->notNull(),
                'uid'            => $this->uid(),
            ]
        );

        // Give the table a foreign key to the elements table
        // $this->addForeignKey(null, '{{%support_answers}}', ['id'], '{{%elements}}', ['id'], 'CASCADE');

        $contentTable = Craft::$app->getContent()->contentTable;
        $this->addColumn($contentTable, 'support_answer_text', 'text');

        $this->addForeignKey(null, '{{%support_answers}}', ['id'], '{{%elements}}', ['id'], 'CASCADE');

        return true;
    }

    /**
     * @inheritdoc
     */

    public function safeDown()
    {
        // echo "m200211_172812_add_answer_elements cannot be reverted.\n";
        // return false;

        $this->dropTableIfExists('{{%support_answers}}');

        $contentTable = Craft::$app->getContent()->contentTable;
        $this->dropColumn($contentTable, 'support_answer_text');

        return true;
    }

}
