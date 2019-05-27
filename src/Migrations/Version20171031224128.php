<?php

namespace ThrottleMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20171031224128 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $subscription = $schema->createTable('subscription');

        $subscription->addColumn('subscription_id', 'string', ['length' => 255]);
        $subscription->addColumn('steamid', 'bigint', ['unsigned' => true, 'notnull' => false]);
        $subscription->addColumn('update_date', 'datetime', []);
        $subscription->addColumn('status', 'string', ['length' => 255, 'notnull' => false]);
        $subscription->addColumn('start_date', 'datetime', ['notnull' => false]);
        $subscription->addColumn('end_date', 'datetime', ['notnull' => false]);
        $subscription->addColumn('checkout_id', 'string', ['length' => 255, 'notnull' => false]);
        $subscription->addColumn('update_url', 'string', ['length' => 511, 'notnull' => false]);
        $subscription->addColumn('cancel_url', 'string', ['length' => 511, 'notnull' => false]);
        $subscription->addColumn('user_id', 'string', ['length' => 255, 'notnull' => false]);
        $subscription->addColumn('plan_id', 'string', ['length' => 255, 'notnull' => false]);
        $subscription->addColumn('passthrough', 'string', ['length' => 255, 'notnull' => false]);

        $subscription->setPrimaryKey(['subscription_id']);

        $user = $schema->getTable('user');
        $subscription->addForeignKeyConstraint($user, ['steamid'], ['id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'SET NULL']);

        $payment = $schema->createTable('payment');

        $payment->addColumn('order_id', 'string', ['length' => 255]);
        $payment->addColumn('subscription_id', 'string', ['length' => 255]);
        $payment->addColumn('payment_date', 'datetime', ['notnull' => false]);
        $payment->addColumn('refund_date', 'datetime', ['notnull' => false]);
        $payment->addColumn('receipt_url', 'string', ['length' => 511, 'notnull' => false]);
        $payment->addColumn('gross_amount', 'integer', ['unsigned' => true, 'notnull' => false]);
        $payment->addColumn('fee_amount', 'integer', ['unsigned' => true, 'notnull' => false]);
        $payment->addColumn('tax_amount', 'integer', ['unsigned' => true, 'notnull' => false]);
        $payment->addColumn('earned_amount', 'integer', ['unsigned' => true, 'notnull' => false]);

        $payment->setPrimaryKey(['order_id']);

        $payment->addForeignKeyConstraint($subscription, ['subscription_id'], ['subscription_id'], ['onUpdate' => 'CASCADE', 'onDelete' => 'CASCADE']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('subscription');
        $schema->dropTable('payment');
    }
}
