<?php

namespace Dfn\Bundle\OroCronofyBundle\Migrations\Schema;

use Doctrine\DBAL\Schema\Schema;

use Oro\Bundle\MigrationBundle\Migration\Installation;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

/**
 * Class DfnOroCronofyBundleInstaller
 * @package Dfn\Bundle\OroCronofyBundle\Migrations\Schema
 */
class DfnOroCronofyBundleInstaller implements Installation
{
    /**
     * {@inheritdoc}
     */
    public function getMigrationVersion()
    {
        return 'v1_0';
    }

    /**
     * {@inheritdoc}
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        $this->createCalendarOrigin($schema);
        $this->createCronofyEvent($schema);
    }

    /**
     * @param Schema $schema
     */
    private function createCalendarOrigin(Schema $schema)
    {
        $table = $schema->createTable('dfn_calendar_origin');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('owner_id', 'integer', []);
        $table->addColumn('organization_id', 'integer', []);
        $table->addColumn('isActive', 'boolean', []);
        $table->addColumn(
            'synchronized',
            'datetime',
            ['notnull' => false, 'comment' => '(DC2Type:datetime)']
        );
        $table->addColumn('access_token', 'string', ['notnull' => false]);
        $table->addColumn('refresh_token', 'string', ['notnull' => false]);
        $table->addColumn(
            'access_token_expires_at',
            'datetime',
            ['notnull' => false, 'comment' => '(DC2Type:datetime)']
        );
        $table->addColumn('scope', 'string', ['notnull' => false]);
        $table->addColumn('provider_name', 'string', ['notnull' => false]);
        $table->addColumn('profile_id', 'string', ['notnull' => false]);
        $table->addColumn('profile_name', 'string', ['notnull' => false]);
        $table->addColumn('calendar_name', 'string', []);
        $table->addColumn('calendar_id', 'string', []);
        $table->addColumn('channel_id', 'string', ['notnull' => false]);
        $table->addIndex(['owner_id'], 'IDX_666D67077E3C61F9', []);
        $table->addIndex(['organization_id'], 'IDX_666D670732C8A3DE', []);
        $table->addIndex(['profile_id'], 'profile_id_idx', []);
        $table->addIndex(['channel_id'], 'channel_id_idx', []);
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_user'),
            ['owner_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => null],
            'FK_666D67077E3C61F9'
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_organization'),
            ['organization_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => null],
            'FK_666D670732C8A3DE'
        );
        $table->setPrimaryKey(['id']);
    }

    /**
     * @param Schema $schema
     */
    private function createCronofyEvent(Schema $schema)
    {
        $table = $schema->createTable('dfn_cronofy_event');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('calendar_event_id', 'integer', []);
        $table->addColumn('calendar_origin_id', 'integer', []);
        $table->addColumn('cronofy_id', 'string', []);
        $table->addColumn('reminders', 'json', []);
        $table->addColumn(
            'updated',
            'datetime',
            ['notnull' => false, 'comment' => '(DC2Type:datetime)']
        );
        $table->addIndex(['calendar_event_id'], 'IDX_E6B08D0D7495C8E3', []);
        $table->addIndex(['calendar_origin_id'], 'IDX_E6B08D0D15CEF886', []);
        $table->addUniqueIndex(['calendar_event_id', 'calendar_origin_id'], 'dfn_cronofy_event_origin', []);
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_calendar_event'),
            ['calendar_event_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'FK_E6B08D0D7495C8E3'
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('dfn_calendar_origin'),
            ['calendar_origin_id'],
            ['id'],
            [],
            'FK_E6B08D0D15CEF886'
        );
    }
}
