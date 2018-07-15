<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration,
    Doctrine\DBAL\Schema\Schema;

class Version20171031224128 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $subscription = $schema->createTable('subscription');

        $subscription->addColumn('subscription_id', 'string', array('length' => 255));
        $subscription->addColumn('steamid', 'bigint', array('unsigned' => true, 'notnull' => false));
        $subscription->addColumn('update_date', 'datetime', array());
        $subscription->addColumn('status', 'string', array('length' => 255, 'notnull' => false));
        $subscription->addColumn('start_date', 'datetime', array('notnull' => false));
        $subscription->addColumn('end_date', 'datetime', array('notnull' => false));
        $subscription->addColumn('checkout_id', 'string', array('length' => 255, 'notnull' => false));
        $subscription->addColumn('update_url', 'string', array('length' => 511, 'notnull' => false));
        $subscription->addColumn('cancel_url', 'string', array('length' => 511, 'notnull' => false));
        $subscription->addColumn('user_id', 'string', array('length' => 255, 'notnull' => false));
        $subscription->addColumn('plan_id', 'string', array('length' => 255, 'notnull' => false));
        $subscription->addColumn('passthrough', 'string', array('length' => 255, 'notnull' => false));

        $subscription->setPrimaryKey(array('subscription_id'));

        $user = $schema->getTable('user');
        $subscription->addForeignKeyConstraint($user, array('steamid'), array('id'), array('onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL'));

        $payment = $schema->createTable('payment');

        $payment->addColumn('order_id', 'string', array('length' => 255));
        $payment->addColumn('subscription_id', 'string', array('length' => 255));
        $payment->addColumn('payment_date', 'datetime', array('notnull' => false));
        $payment->addColumn('refund_date', 'datetime', array('notnull' => false));
        $payment->addColumn('receipt_url', 'string', array('length' => 511, 'notnull' => false));
        $payment->addColumn('gross_amount', 'integer', array('unsigned' => true, 'notnull' => false));
        $payment->addColumn('fee_amount', 'integer', array('unsigned' => true, 'notnull' => false));
        $payment->addColumn('tax_amount', 'integer', array('unsigned' => true, 'notnull' => false));
        $payment->addColumn('earned_amount', 'integer', array('unsigned' => true, 'notnull' => false));

        $payment->setPrimaryKey(array('order_id'));

        $payment->addForeignKeyConstraint($subscription, array('subscription_id'), array('subscription_id'), array('onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE'));
    }

    public function down(Schema $schema)
    {
        $schema->dropTable('subscription');
        $schema->dropTable('payment');
    }
}
