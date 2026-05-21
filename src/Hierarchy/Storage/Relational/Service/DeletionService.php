<?php

namespace App\Hierarchy\Storage\Relational\Service;

use App\Hierarchy\Changeset\Deletion;
use App\Hierarchy\Data;
use App\Hierarchy\Storage\Relational\Exception;
use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Storage\Relational\Algebra\Value\Parameter;
use App\Hierarchy\Storage\Relational\ColumnCoder;
use App\Hierarchy\Storage\Relational\Dialect\DialectInterface;
use Doctrine\DBAL\Connection;

class DeletionService
{
    public function __construct(private SchemaDefinition $schemaDef, private DeletionCommandBuilder $commandBuilder, private Connection $connection, private DialectInterface $dialect, private ColumnCoder $coder)
    {
    }
    /**
     * @param mixed $keyId
     * @param mixed $nodeId
     */
    public function getDeletionPlan($keyId, $nodeId): Deletion
    {
        $cascadingDeletions = $this->collectChildNodesByNodeIds($keyId, [$nodeId]);

        [$referencedLeafs, $referencedInners] = $this->collectReferencedNodesByIds($keyId, [$nodeId]);
        $cascadingDeletions = array_merge($cascadingDeletions, array_filter($referencedLeafs));
        $blockers = $referencedInners;

        foreach ($cascadingDeletions as $willkey => $rows) {
            $willIds = array_keys($rows);
            [$referencedLeafs, $referencedInners] = $this->collectReferencedNodesByIds($willkey, $willIds);

            $cascadingDeletions = array_merge($cascadingDeletions, array_filter($referencedLeafs));
            $blockers = array_merge($blockers, $referencedInners);
        }

        return new Deletion(
            $keyId, $nodeId,
            new Data\MultiCollection(null, null, array_reverse($cascadingDeletions), null, null),
            new Data\MultiCollection(null, null, array_filter($blockers), null, null)
        );
    }

    public function performDeletion(Deletion $deletionPlan): void
    {
        if (!$deletionPlan->isNotBlocked()) {
            throw new Exception\DeletionBlockedException('Deletion is blocked');
        }

        try {
            $this->connection->beginTransaction();
            foreach ($deletionPlan->getCascadingKeys() as $key) {
                $nodeIds = $deletionPlan->getCascadingIdsFor($key);
                if (empty($nodeIds)) {
                    continue;
                }
                $nodeIdParams = array_map(fn ($n) => new Parameter($n), range(1, count($nodeIds)));

                if ($this->schemaDef->isKeyReflexive($key)) {
                    $deleteClosure = $this->commandBuilder->getCommandForDeleteMultipleNodesClosure($key, $nodeIdParams);

                    $stmtClosure = $this->connection->prepare($this->dialect->deleteToString($deleteClosure));

                    foreach ($nodeIdParams as $i => $p) {
                        $stmtClosure->bindValue($this->dialect->parameterToString($p), $nodeIds[$i], $this->coder->getPrimaryColumnBindingType($key));
                    }
                    $stmtClosure->executeStatement();
                }

                $delete = $this->commandBuilder->getCommandForDeleteMultipleNodes($key, $nodeIdParams);

                $stmt = $this->connection->prepare($this->dialect->deleteToString($delete));

                foreach ($nodeIdParams as $i => $p) {
                    $stmt->bindValue($this->dialect->parameterToString($p), $nodeIds[$i], $this->coder->getPrimaryColumnBindingType($key));
                }
                $stmt->executeQuery();
            }
            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }
    /**
     * @param array<int,mixed> $nodeIds
     * @return array
     */
    private function collectChildNodesByNodeIds(string $keyId, array $nodeIds) : array
    {
        if (empty($nodeIds)) {
            return [];
        }
        if ($this->schemaDef->isKeyReflexive($keyId)) {
            $nodeIdParams = array_map(fn ($n) => new Parameter($n), range(1, count($nodeIds)));
            $select = $this->commandBuilder->getSelectForCollectChildByIdReflexive($keyId, $nodeIdParams);
            $stmt = $this->connection->prepare($this->dialect->selectToString($select));
            foreach ($nodeIdParams as $i => $p) {
                $stmt->bindValue($this->dialect->parameterToString($p), $nodeIds[$i], $this->coder->getPrimaryColumnBindingType($keyId));
            }
            $stmtResult = $stmt->executeQuery();
            $rows = $stmtResult->fetchAllAssociativeIndexed();
        } else {
            $nodeIdParams = array_map(fn ($n) => new Parameter($n), range(1, count($nodeIds)));
            $select = $this->commandBuilder->getSelectForCollectSelfById($keyId, $nodeIdParams);
            $stmt = $this->connection->prepare($this->dialect->selectToString($select));
            foreach ($nodeIdParams as $i => $p) {
                $stmt->bindValue($this->dialect->parameterToString($p), $nodeIds[$i], $this->coder->getPrimaryColumnBindingType($keyId));
            }
            $stmtResult = $stmt->executeQuery();
            $rows = $stmtResult->fetchAllAssociativeIndexed();
        }

        if (empty($rows)) {
            return [];
        }

        $ids = [
            $keyId => $rows,
        ];

        $reflexiveIds = array_keys($rows);

        foreach ($this->schemaDef->getKeyIdsScopedInside($keyId) as $scopeId) {
            $ids = array_merge($ids, $this->collectChildNodesByScopeIds($scopeId, $reflexiveIds));
        }

        return $ids;
    }
    /**
     * @param mixed $scopeIds
     * @return array|array<<missing>,<missing>>
     */
    private function collectChildNodesByScopeIds(string $keyId, array $scopeIds) : array
    {
        if (empty($scopeIds)) {
            return [];
        }
        $nodeIdParams = array_map(fn ($n) => new Parameter($n), range(1, count($scopeIds)));
        if ($this->schemaDef->isKeyReflexive($keyId)) {
            $select = $this->commandBuilder->getSelectForCollectChildByScopeReflexive($keyId, $nodeIdParams);
        } else {
            $select = $this->commandBuilder->getSelectForCollectChildByScope($keyId, $nodeIdParams);
        }

        $stmt = $this->connection->prepare($this->dialect->selectToString($select));
        foreach ($nodeIdParams as $i => $p) {
            $stmt->bindValue($this->dialect->parameterToString($p), $scopeIds[$i], $this->coder->getScopeColumnBindingType($keyId));
        }
        $stmtResult = $stmt->executeQuery();
        $rows = $stmtResult->fetchAllAssociativeIndexed();

        if (empty($rows)) {
            return [];
        }

        $ids = [
            $keyId => $rows,
        ];

        $reflexiveIds = array_keys($rows);

        foreach ($this->schemaDef->getKeyIdsScopedInside($keyId) as $scopeId) {
            $ids = array_merge($ids, $this->collectChildNodesByScopeIds($scopeId, $reflexiveIds));
        }

        return $ids;
    }
    /**
     * @param mixed $keyId
     * @param mixed $nodeIds
     * @return array
     */
    private function collectReferencedNodesByIds(string $keyId,  array $nodeIds) : array
    {
        $leafs = [];
        $inners = [];

        if (empty($nodeIds)) {
            return [[], []];
        }

        $nodeIdParams = array_map(fn ($n) => new Parameter($n), range(1, count($nodeIds)));

        foreach ($this->schemaDef->getAllKeyIds() as $refKey) {
            $columns = $this->schemaDef->getReferencingKeyColumns($keyId, $refKey);
            if (empty($columns)) {
                continue;
            }

            $select = $this->commandBuilder->getSelectForReferencedNodes($refKey, $columns, $nodeIdParams);

            $stmt = $this->connection->prepare($this->dialect->selectToString($select));
            foreach ($nodeIdParams as $i => $p) {
                $stmt->bindValue($this->dialect->parameterToString($p), $nodeIds[$i], $this->coder->getPrimaryColumnBindingType($keyId));
            }
            $stmtResult = $stmt->executeQuery();

            $canCascade = array_reduce($columns, fn ($can, $col) => $can && $col->getCoding()->canCascade(), true);

            $nodes = $stmtResult->fetchAllAssociativeIndexed();

            if ($this->schemaDef->isKeyLeaf($refKey) && $canCascade) {
                $leafs[$refKey] = $nodes;
            } else {
                $inners[$refKey] = $nodes;
            }
        }

        return [$leafs, $inners];
    }
}
