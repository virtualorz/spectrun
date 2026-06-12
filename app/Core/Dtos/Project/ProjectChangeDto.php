<?php

namespace App\Core\Dtos\Project;

use DateTimeInterface;

readonly class ProjectChangeDto
{
    public function __construct(
        public string $number,
        public string $slug,
        public string $title,
        public ?string $problem,
        public ?string $baseBranch,
        public string $status,
        public ?DateTimeInterface $issuedAt,
        public ?DateTimeInterface $designedAt,
        public ?DateTimeInterface $ranAt,
        public ?DateTimeInterface $closedAt,
        public ?int $tokensAtNew,
        public ?int $tokensAtClose,
        public ?string $deviation,
        public int $decisionsDone,
        public int $decisionsTotal,
        public int $tasksDone,
        public int $tasksTotal,
        public int $discussionCount,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'slug' => $this->slug,
            'title' => $this->title,
            'problem' => $this->problem,
            'base_branch' => $this->baseBranch,
            'status' => $this->status,
            'issued_at' => $this->issuedAt,
            'designed_at' => $this->designedAt,
            'ran_at' => $this->ranAt,
            'closed_at' => $this->closedAt,
            'tokens_at_new' => $this->tokensAtNew,
            'tokens_at_close' => $this->tokensAtClose,
            'deviation' => $this->deviation,
            'decisions_done' => $this->decisionsDone,
            'decisions_total' => $this->decisionsTotal,
            'tasks_done' => $this->tasksDone,
            'tasks_total' => $this->tasksTotal,
            'discussion_count' => $this->discussionCount,
        ];
    }
}
