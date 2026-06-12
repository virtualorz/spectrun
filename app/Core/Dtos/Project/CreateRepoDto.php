<?php

namespace App\Core\Dtos\Project;

use DateTimeInterface;

readonly class CreateRepoDto
{
    public function __construct(
        public string $fullName,
        public bool $isPrivate,
        public ?string $defaultBranch,
        public bool $hasSpecflow,
        public ?string $displayName = null,
        public ?string $techStack = null,
        public ?DateTimeInterface $lastSyncedAt = null,
        public ?string $specflowBranch = null,
    ) {}

    /**
     * 對應 projects 欄位;追蹤即 is_tracked=true。
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'full_name' => $this->fullName,
            'is_private' => $this->isPrivate,
            'default_branch' => $this->defaultBranch,
            'has_specflow' => $this->hasSpecflow,
            'is_tracked' => true,
            'display_name' => $this->displayName,
            'tech_stack' => $this->techStack,
            'last_synced_at' => $this->lastSyncedAt,
            'specflow_branch' => $this->specflowBranch ?? $this->defaultBranch,
        ];
    }
}
