<?php

namespace App\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;

final class SqliteForeignKeyMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new class($driver) implements Driver {
            public function __construct(
                private Driver $driver,
            ) {
            }

            public function connect(
                array $params,
            ): Connection {
                $connection = $this->driver->connect($params);

                $platform = $params['driver'] ?? '';

                if (str_contains($platform, 'sqlite')) {
                    $connection->exec('PRAGMA foreign_keys = ON');
                }

                return $connection;
            }

            public function getDatabasePlatform(
                \Doctrine\DBAL\ServerVersionProvider $versionProvider,
            ): \Doctrine\DBAL\Platforms\AbstractPlatform {
                return $this->driver->getDatabasePlatform($versionProvider);
            }

            public function getExceptionConverter(): Driver\API\ExceptionConverter
            {
                return $this->driver->getExceptionConverter();
            }
        };
    }
}
