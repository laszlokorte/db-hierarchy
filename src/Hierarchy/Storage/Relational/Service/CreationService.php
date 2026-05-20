<?php

namespace App\Hierarchy\Storage\Relational\Service;

use App\Hierarchy\Changeset\Creation;
use App\Hierarchy\Data\Node;
use App\Hierarchy\Schema\Definition\SchemaDefinition;
use App\Hierarchy\Storage\Relational\Algebra\Value\Parameter;
use App\Hierarchy\Storage\Relational\ColumnCoder;
use App\Hierarchy\Storage\Relational\Dialect\DialectInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

class CreationService
{
    public function __construct(private SchemaDefinition $schemaDef, private CreationCommandBuilder $commandBuilder, private Connection $connection, private DialectInterface $dialect, private ColumnCoder $coder)
    {
    }

    private function genUUID(): string
    {
        return sprintf('%04x%04x%04x%04x%04x%04x%04x%04x',
            mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0x0FFF) | 0x4000,
            mt_rand(0, 0x3FFF) | 0x8000,
            mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF)
        );
    }

    public function getFreshCreation(string $keyId, ?Node $superNode = null): Creation
    {
        if (null === $superNode) {
            $scopeId = null;
            $parentId = null;
        } elseif ($superNode->getKey() === $keyId) {
            $scopeId = $superNode->getScope();
            $parentId = $superNode->getId();
        } else {
            $scopeId = $superNode->getId();
            $parentId = null;
        }

        return new Creation(
            $keyId,
            $scopeId,
            $parentId,
            [],
            null
        );
    }

    /**
     * @param array<int,mixed> $fieldData
     */
    public function getValidatedCreation(string $keyId, array $fieldData, ?string $scopeId, ?string $parentId): Creation
    {
        $fieldErrors = [];
        $scopeErrors = [];
        $parentErrors = [];

        $this->validateRequiredField($fieldErrors, $keyId, $fieldData);
        $this->validateNodePosition($scopeErrors, $parentErrors, $keyId, $scopeId, $parentId);
        $this->validateUniquenessForNew($fieldErrors, $keyId, $fieldData, $scopeId, $parentId);

        $allColumnData = [];

        foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
            $columnData = $this->schemaDef->convertKeyFieldDataToColumnData($keyId, $fieldId, $fieldData[$fieldId] ?? null);

            foreach ($this->schemaDef->getKeyFieldColumns($keyId, $fieldId) as $ci => $column) {
                $allColumnData[$column->getName()] = $columnData[$ci];
            }
        }

        return new Creation(
            $keyId,
            $scopeId,
            $parentId,
            $allColumnData,
            $fieldErrors,
            $scopeErrors,
            $parentErrors
        );
    }

    private function validateRequiredField(&$errors, $keyId, $fieldData): void
    {
        foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
            if (!$this->schemaDef->isKeyFieldRequired($keyId, $fieldId)) {
                continue;
            }

            $fieldsToCheck = [];
            $valuesToCheck = [];
            $fieldsToCheck[$fieldId] = [];
            $valuesToCheck[$fieldId] = [];

            $columnData = $this->schemaDef->convertKeyFieldDataToColumnData($keyId, $fieldId, $fieldData[$fieldId] ?? null);

            if (empty(array_filter($columnData, fn ($d) => '' !== $d && null !== $d))) {
                $errors[$fieldId][] = 'is required';
            }
        }
    }

    /**
     * @param array<int,mixed> $scopeErrors
     * @param array<int,mixed> $parentErrors
     */
    private function validateNodePosition(array &$scopeErrors, array &$parentErrors, string $keyId, ?string $scopeId, ?string $parentId): void
    {
        if ($this->schemaDef->isKeyScoped($keyId) !== !empty($scopeId)) {
            $scopeErrors[] = 'is required';
        }

        if (!$this->schemaDef->isKeyReflexive($keyId) && !empty($parentId)) {
            $parentErrors[] = 'is not expected';
        }

        $scopeParam = new Parameter('_scope');
        $parentParam = new Parameter('_parent');

        if (!empty($scopeId) && !empty($parentId)) {
            $selectMoveTargetExists = $this->commandBuilder->getSelectForScopeParentCheck($keyId, $scopeParam, $parentParam);

            $validPositionStmt = $this->connection->prepare($this->dialect->selectToString($selectMoveTargetExists));

            $validPositionStmt->bindValue($this->dialect->parameterToString($scopeParam), $scopeId, ParameterType::INTEGER);
            $validPositionStmt->bindValue($this->dialect->parameterToString($parentParam), $parentId, ParameterType::INTEGER);
            $stmtResult = $validPositionStmt->executeQuery();

            if (!$stmtResult->fetchOne()) {
                $parentErrors[] = 'is not matching';
            }
        }
    }

    private function validateUniquenessForNew(&$errors, $keyId, $fieldData, $scopeId, $parentId): void
    {
        $scopeParam = new Parameter('_scope');
        $parentParam = new Parameter('_parent');

        $fieldsToCheck = [];
        $valuesToCheck = [];

        foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
            if (!$this->schemaDef->isKeyFieldUnique($keyId, $fieldId)) {
                continue;
            }
            $columnData = $this->schemaDef->convertKeyFieldDataToColumnData($keyId, $fieldId, $fieldData[$fieldId] ?? null);

            if (empty(array_filter($columnData))) {
                continue;
            }

            $fieldsToCheck[$fieldId] = [];
            $valuesToCheck[$fieldId] = [];

            foreach ($this->schemaDef->getKeyFieldColumns($keyId, $fieldId) as $ci => $column) {
                $fieldsToCheck[$fieldId][] = new Parameter($column->getName());
                $valuesToCheck[$fieldId][] = $columnData[$ci];
            }
        }

        $select = $this->commandBuilder->getSelectForUniquenessCheckNew($keyId, $scopeParam, $parentParam, $fieldsToCheck);
        $stmt = $this->connection->prepare($this->dialect->selectToString($select));

        if ($this->schemaDef->isKeyScoped($keyId)) {
            $stmt->bindValue(
                $this->dialect->parameterToString($scopeParam),
                $scopeId, ParameterType::INTEGER
            );
        }

        if ($this->schemaDef->isKeyReflexive($keyId)) {
            $stmt->bindValue(
                $this->dialect->parameterToString($parentParam),
                $parentId, ParameterType::INTEGER
            );
        }

        foreach ($fieldsToCheck as $fieldId => $params) {
            foreach ($params as $i => $param) {
                $stmt->bindValue(
                    $this->dialect->parameterToString($param),
                    $valuesToCheck[$fieldId][$i]
                );
            }
        }

        $stmtResult = $stmt->executeQuery();
        $result = $stmtResult->fetchAssociative();

        if ($result) {
            foreach ($fieldsToCheck as $fieldId => $params) {
                if ($result[$fieldId]) {
                    $errors[$fieldId][] = 'not unique';
                }
            }
        }
    }

    /**
     * @return int|string
     */
    public function createNode(string $keyId, $fieldData, $scopeId, $parentId): string
    {
        if ($this->schemaDef->isKeyScoped($keyId) !== !empty($scopeId)) {
            throw new \Exception('missing scope');
        }

        if (!$this->schemaDef->isKeyReflexive($keyId) && !empty($parentId)) {
            throw new \Exception($parentId);
        }

        $idParam = new Parameter('_id');
        $scopeParam = new Parameter('_scope');
        $parentParam = new Parameter('_parent');

        if (!empty($scopeId) && !empty($parentId)) {
            $selectMoveTargetExists = $this->commandBuilder->getSelectForScopeParentCheck($keyId, $scopeParam, $parentParam);

            $validPositionStmt = $this->connection->prepare($this->dialect->selectToString($selectMoveTargetExists));

            $validPositionStmt->bindValue($this->dialect->parameterToString($scopeParam), $scopeId, $this->coder->getScopeColumnBindingType($keyId));
            $validPositionStmt->bindValue($this->dialect->parameterToString($parentParam), $parentId, $this->coder->getPrimaryColumnBindingType($keyId));
            $stmtResult = $validPositionStmt->executeQuery();

            if (!$stmtResult->fetchOne()) {
                throw new \Exception('invalid position');
            }
        }

        $insert = $this->commandBuilder->getCommandForCreateNode($keyId, $idParam, $scopeParam, $parentParam);

        $this->connection->beginTransaction();

        $stmt = $this->connection->prepare($this->dialect->insertToString($insert));

        $generatedId = null;
        switch ($this->schemaDef->getKeyIdentityColumnType($keyId)) {
            case 'uuid':
                $generatedId = $this->genUUID();
                $stmt->bindValue(
                    $this->dialect->parameterToString($idParam),
                    $generatedId, ParameterType::STRING
                );
                break;
            case 'manual':
                $generatedId = 'affecaffee';
                $stmt->bindValue(
                    $this->dialect->parameterToString($idParam),
                    $generatedId, ParameterType::STRING
                );
                break;
        }

        if ($this->schemaDef->isKeyScoped($keyId)) {
            $stmt->bindValue(
                $this->dialect->parameterToString($scopeParam),
                $scopeId, $this->coder->getScopeColumnBindingType($keyId)
            );
        }

        if ($this->schemaDef->isKeyReflexive($keyId) && $this->schemaDef->isKeyOrdered($keyId)) {
            $stmt->bindValue(
                $this->dialect->parameterToString($parentParam),
                $parentId, $this->coder->getPrimaryColumnBindingType($keyId)
            );
        }

        foreach ($this->schemaDef->getKeyFieldIds($keyId) as $fieldId) {
            $columnData = $this->schemaDef->convertKeyFieldDataToColumnData($keyId, $fieldId, $fieldData[$fieldId] ?? null);

            foreach ($this->schemaDef->getKeyFieldColumns($keyId, $fieldId) as $ci => $column) {
                $stmt->bindValue(
                    $this->dialect->parameterToString(new Parameter($column->getName())),
                    $columnData[$ci]
                );
            }
        }

        $stmt->executeQuery();

        if (null === $generatedId) {
            $newNodeId = $this->connection->lastInsertId();
        } else {
            $newNodeId = $generatedId;
        }

        if ($this->schemaDef->isKeyReflexive($keyId)) {
            $parentParam = new Parameter('_parent');
            $childParam = new Parameter('_child');
            $depthParam = new Parameter('_depth');
            $scopeParam = new Parameter('_scope');

            $closureInsert = $this->commandBuilder->getCommandForClosureInsert($keyId, $scopeParam, $parentParam, $childParam, $depthParam);
            $closureStmt = $this->connection->prepare($this->dialect->insertToString($closureInsert));

            $closureStmt->bindValue($this->dialect->parameterToString($parentParam), $newNodeId, $this->coder->getPrimaryColumnBindingType($keyId));
            $closureStmt->bindValue($this->dialect->parameterToString($childParam), $newNodeId, $this->coder->getPrimaryColumnBindingType($keyId));
            $closureStmt->bindValue($this->dialect->parameterToString($depthParam), 0, ParameterType::INTEGER);

            if ($this->schemaDef->isKeyScoped($keyId)) {
                $closureStmt->bindValue(
                    $this->dialect->parameterToString($scopeParam),
                    $scopeId, $this->coder->getScopeColumnBindingType($keyId)
                );
            }

            $closureStmt->executeQuery();

            if (!empty($parentId)) {
                $closureInsertParent = $this->commandBuilder->getCommandForClosureParentInsert($keyId, $scopeParam, $childParam, $parentParam);
                $closureStmt = $this->connection->prepare($this->dialect->insertToString($closureInsertParent));

                $closureStmt->bindValue($this->dialect->parameterToString($parentParam), $parentId, $this->coder->getPrimaryColumnBindingType($keyId));
                $closureStmt->bindValue($this->dialect->parameterToString($childParam), $newNodeId, $this->coder->getPrimaryColumnBindingType($keyId));

                if ($this->schemaDef->isKeyScoped($keyId)) {
                    $closureStmt->bindValue(
                        $this->dialect->parameterToString($scopeParam),
                        $scopeId, $this->coder->getScopeColumnBindingType($keyId)
                    );
                }

                $closureStmt->executeQuery();
            }

            $this->connection->prepare($this->dialect->insertToString(
                $this->commandBuilder->getInsertForClosureRepair($keyId)
            ));
        }

        $this->connection->commit();

        return $newNodeId;
    }
}
