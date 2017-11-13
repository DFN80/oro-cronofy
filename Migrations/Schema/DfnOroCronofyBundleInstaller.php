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
        $table->addIndex(['owner_id'], 'IDX_666D67077E3C61F9', []);
        $table->addIndex(['organization_id'], 'IDX_666D670732C8A3DE', []);
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
}
