<?php

declare(strict_types=1);

namespace BMN\Users\Tests\Unit\Migration;

use BMN\Users\Migration\CreateFavoritesTable;
use BMN\Users\Migration\CreatePasswordResetsTable;
use BMN\Users\Migration\CreateRevokedTokensTable;
use BMN\Users\Migration\CreateSavedSearchesTable;
use PHPUnit\Framework\TestCase;

final class MigrationsTest extends TestCase
{
    public function testCreateFavoritesTableExtendsBaseMigration(): void
    {
        $migration = new CreateFavoritesTable();
        $this->assertInstanceOf(\BMN\Platform\Database\Migration::class, $migration);
    }

    public function testCreateFavoritesTableHasVersionString(): void
    {
        $migration = new CreateFavoritesTable();
        $this->assertSame('CreateFavoritesTable', $migration->getVersion());
    }

    public function testCreateSavedSearchesTableExtendsBaseMigration(): void
    {
        $migration = new CreateSavedSearchesTable();
        $this->assertInstanceOf(\BMN\Platform\Database\Migration::class, $migration);
    }

    public function testCreateSavedSearchesTableHasVersionString(): void
    {
        $migration = new CreateSavedSearchesTable();
        $this->assertSame('CreateSavedSearchesTable', $migration->getVersion());
    }

    public function testCreateRevokedTokensTableExtendsBaseMigration(): void
    {
        $migration = new CreateRevokedTokensTable();
        $this->assertInstanceOf(\BMN\Platform\Database\Migration::class, $migration);
    }

    public function testCreateRevokedTokensTableHasVersionString(): void
    {
        $migration = new CreateRevokedTokensTable();
        $this->assertSame('CreateRevokedTokensTable', $migration->getVersion());
    }

    public function testCreatePasswordResetsTableExtendsBaseMigration(): void
    {
        $migration = new CreatePasswordResetsTable();
        $this->assertInstanceOf(\BMN\Platform\Database\Migration::class, $migration);
    }

    public function testCreatePasswordResetsTableHasVersionString(): void
    {
        $migration = new CreatePasswordResetsTable();
        $this->assertSame('CreatePasswordResetsTable', $migration->getVersion());
    }
}
