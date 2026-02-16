<?php

declare(strict_types=1);

namespace BMN\Schools\Tests\Unit\Migration;

use BMN\Schools\Migration\CreateSchoolsTable;
use BMN\Schools\Migration\CreateSchoolDistrictsTable;
use BMN\Schools\Migration\CreateSchoolTestScoresTable;
use BMN\Schools\Migration\CreateSchoolFeaturesTable;
use BMN\Schools\Migration\CreateSchoolDemographicsTable;
use BMN\Schools\Migration\CreateSchoolRankingsTable;
use BMN\Schools\Migration\CreateDistrictRankingsTable;
use PHPUnit\Framework\TestCase;

final class MigrationsTest extends TestCase
{
    public function testCreateSchoolsTableExtendsBaseMigration(): void
    {
        $migration = new CreateSchoolsTable();
        $this->assertInstanceOf(\BMN\Platform\Database\Migration::class, $migration);
    }

    public function testCreateSchoolsTableHasVersionString(): void
    {
        $migration = new CreateSchoolsTable();
        $this->assertSame('CreateSchoolsTable', $migration->getVersion());
    }

    public function testCreateSchoolDistrictsTableExtendsBaseMigration(): void
    {
        $migration = new CreateSchoolDistrictsTable();
        $this->assertInstanceOf(\BMN\Platform\Database\Migration::class, $migration);
    }

    public function testCreateSchoolDistrictsTableHasVersionString(): void
    {
        $migration = new CreateSchoolDistrictsTable();
        $this->assertSame('CreateSchoolDistrictsTable', $migration->getVersion());
    }

    public function testCreateSchoolTestScoresTableExtendsBaseMigration(): void
    {
        $migration = new CreateSchoolTestScoresTable();
        $this->assertInstanceOf(\BMN\Platform\Database\Migration::class, $migration);
    }

    public function testCreateSchoolTestScoresTableHasVersionString(): void
    {
        $migration = new CreateSchoolTestScoresTable();
        $this->assertSame('CreateSchoolTestScoresTable', $migration->getVersion());
    }

    public function testCreateSchoolFeaturesTableExtendsBaseMigration(): void
    {
        $migration = new CreateSchoolFeaturesTable();
        $this->assertInstanceOf(\BMN\Platform\Database\Migration::class, $migration);
    }

    public function testCreateSchoolFeaturesTableHasVersionString(): void
    {
        $migration = new CreateSchoolFeaturesTable();
        $this->assertSame('CreateSchoolFeaturesTable', $migration->getVersion());
    }

    public function testCreateSchoolDemographicsTableExtendsBaseMigration(): void
    {
        $migration = new CreateSchoolDemographicsTable();
        $this->assertInstanceOf(\BMN\Platform\Database\Migration::class, $migration);
    }

    public function testCreateSchoolDemographicsTableHasVersionString(): void
    {
        $migration = new CreateSchoolDemographicsTable();
        $this->assertSame('CreateSchoolDemographicsTable', $migration->getVersion());
    }

    public function testCreateSchoolRankingsTableExtendsBaseMigration(): void
    {
        $migration = new CreateSchoolRankingsTable();
        $this->assertInstanceOf(\BMN\Platform\Database\Migration::class, $migration);
    }

    public function testCreateSchoolRankingsTableHasVersionString(): void
    {
        $migration = new CreateSchoolRankingsTable();
        $this->assertSame('CreateSchoolRankingsTable', $migration->getVersion());
    }

    public function testCreateDistrictRankingsTableExtendsBaseMigration(): void
    {
        $migration = new CreateDistrictRankingsTable();
        $this->assertInstanceOf(\BMN\Platform\Database\Migration::class, $migration);
    }

    public function testCreateDistrictRankingsTableHasVersionString(): void
    {
        $migration = new CreateDistrictRankingsTable();
        $this->assertSame('CreateDistrictRankingsTable', $migration->getVersion());
    }
}
