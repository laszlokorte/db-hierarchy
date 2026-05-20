<?php

namespace App\Hierarchy\Storage\Relational;

use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Storage\Relational\Dialect\DialectInterface;
use App\Hierarchy\Storage\Relational\Dialect\MySql;
use App\Hierarchy\Storage\Relational\Dialect\Sqlite;
use App\Hierarchy\Storage\Relational\Service\CreationService;
use App\Hierarchy\Storage\Relational\Service\DeletionService;
use App\Hierarchy\Storage\Relational\Service\InstallationService;
use App\Hierarchy\Storage\Relational\Service\MovementService;
use App\Hierarchy\Storage\Relational\Service\OrderingService;
use App\Hierarchy\Storage\Relational\Service\QueryService;
use App\Hierarchy\Storage\Relational\Service\RepairService;
use App\Hierarchy\Storage\Relational\Service\UpdateService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;

class StorageConnection
{
    private Quirks $quirks;
    private Naming $naming;
    private ColumnCoder $coder;
    private DialectInterface $dialect;

    public function __construct(private SchemaDefinition $schemaDef, private Connection $connection)
    {
        $this->quirks = new Quirks(
            $connection->getDatabasePlatform() instanceof MySQLPlatform,
            $connection->getDatabasePlatform() instanceof SQLitePlatform
        );

        $this->naming = new Naming($schemaDef);
        $this->coder = new ColumnCoder($schemaDef, $this->naming);
        switch (get_class($connection->getDatabasePlatform())) {
            case MySQLPlatform::class: $this->dialect = new MySql();
                break;
            case SQLitePlatform::class:
                $connection->executeQuery('PRAGMA foreign_keys = ON;');
                // POLYFILL: SQlite has noe UNHEX function included.
                // currently the polyfill is not in use.
                $pdo = $connection->getNativeConnection();
                $pdo->sqliteCreateFunction('MY_HEX', 'bin2hex', 1, \PDO::SQLITE_DETERMINISTIC);
                $pdo->sqliteCreateFunction('MY_UNHEX', 'hex2bin', 1, \PDO::SQLITE_DETERMINISTIC);
                $this->dialect = new Sqlite();
                break;
            default: throw new \Exception(sprintf('database "%s" not supported', get_class($connection->getDatabasePlatform())));
        }
    }

    public function getQueryService(): QueryService
    {
        return new QueryService(
            $this->schemaDef,
            new Service\QueryCommandBuilder($this->schemaDef, $this->naming, $this->coder),
            $this->connection,
            $this->dialect,
            $this->coder
        );
    }

    public function getDeletionService(): DeletionService
    {
        return new DeletionService(
            $this->schemaDef,
            new Service\DeletionCommandBuilder($this->schemaDef, $this->naming, $this->coder),
            $this->connection,
            $this->dialect,
            $this->coder
        );
    }

    public function getCreationService(): CreationService
    {
        return new CreationService(
            $this->schemaDef,
            new Service\CreationCommandBuilder($this->schemaDef, $this->naming, $this->coder),
            $this->connection,
            $this->dialect,
            $this->coder
        );
    }

    public function getUpdateService(): UpdateService
    {
        return new UpdateService(
            $this->schemaDef,
            new Service\UpdateCommandBuilder($this->schemaDef, $this->naming, $this->coder),
            $this->connection,
            $this->dialect,
            $this->coder
        );
    }

    public function getMovementService(): MovementService
    {
        return new MovementService(
            $this->schemaDef,
            new Service\MovementCommandBuilder($this->schemaDef, $this->naming, $this->coder),
            $this->connection,
            $this->dialect,
            $this->coder
        );
    }

    public function getOrderingService(): OrderingService
    {
        return new OrderingService(
            $this->schemaDef,
            new Service\OrderingCommandBuilder($this->schemaDef, $this->naming, $this->coder),
            $this->connection,
            $this->dialect,
            $this->coder,
            $this->getQueryService()
        );
    }

    public function getRepairService(): RepairService
    {
        return new RepairService(
            $this->schemaDef,
            new Service\RepairCommandBuilder($this->schemaDef, $this->naming, $this->coder),
            $this->connection,
            $this->dialect,
            $this->coder
        );
    }

    public function getInstallationService(): InstallationService
    {
        return new InstallationService(
            new Service\InstallationCommandBuilder($this->schemaDef, $this->naming, $this->quirks),
            $this->connection,
            $this->dialect,
            $this->quirks
        );
    }
}
