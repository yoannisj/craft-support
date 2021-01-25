<?php

namespace lukeyouell\support\migrations;

use Craft;
use craft\db\Migration;

/**
 * m200904_182546_add_deleted_order_reference migration.
 */
class m200904_182546_add_deleted_order_reference extends Migration
{
    /**
     * @inheritdoc
     */

    public function safeUp()
    {
        // Place migration code here...
        $this->addColumn('{{%support_tickets}}', 'deletedOrderReference', 'string');
        $this->addColumn('{{%support_ticketstatuses}}', 'legacy', 'boolean');

        return true;
    }

    /**
     * @inheritdoc
     */

    public function safeDown()
    {
        $this->dropColumn('{{%support_tickets}}', 'deletedOrderReference');
        $this->dropColumn('{{%support_ticketstatuses}}', 'legacy');

        return true;
    }
}
