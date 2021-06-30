<?php

namespace App\Doctrine;

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Events;

class SqliteForeignKeyActivationSubscriber implements EventSubscriber
{
    /**
     * {@inheritdoc}
     */
    public function getSubscribedEvents()
    {
        return [
            Events::postConnect,
        ];
    }

    /**
     * @param ConnectionEventArgs $args
     * @throws \Doctrine\DBAL\DBALException
     */
    public function postConnect(ConnectionEventArgs $args)
    {       
        if ('sqlite' !== strtolower($args->getDatabasePlatform()->getName())) {
            return;
        }

        $args->getConnection()->exec('PRAGMA foreign_keys = ON;');
    }
}
