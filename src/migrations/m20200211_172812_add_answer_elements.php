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
        $this->addColumn('{{%support_tickets}}', 'recipientId', 'integer');
        $this->addForeignKey(null, '{{%support_tickets}}', ['recipientId'], '{{%users}}', ['id'], null, 'CASCADE');

        $emailRecipientTypes = [ 'author', 'recipient', 'custom' ];
        $this->alterColumn('{{%support_emails}}', 'recipientType',
            $this->enum('recipientType', $emailRecipientTypes)
        );

        if (Craft::$app->getPlugins()->isPluginInstalled('commerce'))
        {
            $this->addColumn('{{%support_tickets}}', 'orderId', 'integer');
            $this->addForeignKey(null, '{{%support_tickers}}', ['orderId'], '{{%commerce_orders}}', ['orderId'], null, 'CASCADE');
        }

        // Create new table for answers
        $this->createTable(
            '{{%support_answers}}',
            [
                'id'             => $this->primaryKey(),
                'dateCreated'    => $this->dateTime()->notNull(),
                'dateUpdated'    => $this->dateTime()->notNull(),
                'uid'            => $this->uid(),
                // Custom columns in the table
                'authorId'       => $this->integer(),
            ]
        );

        // Setup foreign keys
        $this->addForeignKey(null, '{{%support_answers}}', ['id'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%support_answers}}', ['authorId'], '{{%users}}', ['id'], null, 'CASCADE');

        // Setup columns for localized fields
        $contentTable = Craft::$app->getContent()->contentTable;
        $this->addColumn($contentTable, 'support_answer_text', 'text');
        
        return true;
    }

    /**
     * @inheritdoc
     */

    public function safeDown()
    {
        // echo "m200211_172812_add_answer_elements cannot be reverted.\n";
        // return false;

        // Tickets x Commerce
        $this->dropColumn('{{%support_tickets}}', 'recipientId');

        try {
            $this->dropColumn('{{%support_tickets}}', 'orderId');
        } catch(\Throwable $e) {}

        // Answers
        $this->dropTableIfExists('{{%support_answers}}');

        $contentTable = Craft::$app->getContent()->contentTable;
        $this->dropColumn($contentTable, 'support_answer_text');

        return true;
    }

}
