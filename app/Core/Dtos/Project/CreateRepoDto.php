<?php

namespace App\Core\Dtos\Project;

readonly class CreateRepoDto
{
    public function __construct(
        public string $fullName,
        public bool $isPrivate,
        public ?string $defaultBranch,
        public bool $hasSpecflow,
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
        ];
    }
}
