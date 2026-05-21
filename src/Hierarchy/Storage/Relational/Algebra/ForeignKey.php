<?php

namespace App\Hierarchy\Storage\Relational\Algebra;

class ForeignKey
{
    public const CASCADE = 'CASCADE';
    public const RESTRICT = 'RESTRICT';
    public const SET_NULL = 'SET NULL';

    /**
     * @param array<int,mixed> $ownColumns
     * @param array<int,mixed> $targetColumns
     */
    public function __construct(
        private array $ownColumns,
        private Identifier $foreignTable,
        private array $targetColumns,
        private string $onDelete = 'CASCADE',
    ) {
    }

    /**
     * @return array<int,mixed>
     */
    public function getOwnColumns(): array
    {
        return $this->ownColumns;
    }

    public function getForeignTable(): Identifier
    {
        return $this->foreignTable;
    }

    /**
     * @return array<int,mixed>
     */
    public function getTargetColumns(): array
    {
        return $this->targetColumns;
    }

    public function getOnDelete(): string
    {
        return $this->onDelete;
    }
}
