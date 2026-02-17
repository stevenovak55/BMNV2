<?php

declare(strict_types=1);

namespace BMN\Agents\Tests\Unit\Migration;

use BMN\Agents\Migration\CreateActivityLogTable;
use BMN\Agents\Migration\CreateAgentProfilesTable;
use BMN\Agents\Migration\CreateReferralCodesTable;
use BMN\Agents\Migration\CreateReferralSignupsTable;
use BMN\Agents\Migration\CreateRelationshipsTable;
use BMN\Agents\Migration\CreateSharedPropertiesTable;
use BMN\Platform\Database\Migration;
use PHPUnit\Framework\TestCase;

final class MigrationsTest extends TestCase
{
    public function testCreateAgentProfilesTableExtendsBaseMigration(): void
    {
        $migration = new CreateAgentProfilesTable();
        $this->assertInstanceOf(Migration::class, $migration);
    }

    public function testCreateAgentProfilesTableHasVersionString(): void
    {
        $migration = new CreateAgentProfilesTable();
        $this->assertSame('CreateAgentProfilesTable', $migration->getVersion());
    }

    public function testCreateRelationshipsTableExtendsBaseMigration(): void
    {
        $migration = new CreateRelationshipsTable();
        $this->assertInstanceOf(Migration::class, $migration);
    }

    public function testCreateRelationshipsTableHasVersionString(): void
    {
        $migration = new CreateRelationshipsTable();
        $this->assertSame('CreateRelationshipsTable', $migration->getVersion());
    }

    public function testCreateSharedPropertiesTableExtendsBaseMigration(): void
    {
        $migration = new CreateSharedPropertiesTable();
        $this->assertInstanceOf(Migration::class, $migration);
    }

    public function testCreateSharedPropertiesTableHasVersionString(): void
    {
        $migration = new CreateSharedPropertiesTable();
        $this->assertSame('CreateSharedPropertiesTable', $migration->getVersion());
    }

    public function testCreateReferralCodesTableExtendsBaseMigration(): void
    {
        $migration = new CreateReferralCodesTable();
        $this->assertInstanceOf(Migration::class, $migration);
    }

    public function testCreateReferralCodesTableHasVersionString(): void
    {
        $migration = new CreateReferralCodesTable();
        $this->assertSame('CreateReferralCodesTable', $migration->getVersion());
    }

    public function testCreateReferralSignupsTableExtendsBaseMigration(): void
    {
        $migration = new CreateReferralSignupsTable();
        $this->assertInstanceOf(Migration::class, $migration);
    }

    public function testCreateReferralSignupsTableHasVersionString(): void
    {
        $migration = new CreateReferralSignupsTable();
        $this->assertSame('CreateReferralSignupsTable', $migration->getVersion());
    }

    public function testCreateActivityLogTableExtendsBaseMigration(): void
    {
        $migration = new CreateActivityLogTable();
        $this->assertInstanceOf(Migration::class, $migration);
    }

    public function testCreateActivityLogTableHasVersionString(): void
    {
        $migration = new CreateActivityLogTable();
        $this->assertSame('CreateActivityLogTable', $migration->getVersion());
    }
}
