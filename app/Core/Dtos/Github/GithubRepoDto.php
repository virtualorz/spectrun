<?php

namespace App\Core\Dtos\Github;

readonly class GithubRepoDto
{
    public function __construct(
        public string $fullName,
        public bool $private,
        public ?string $defaultBranch,
        public ?string $language,
    ) {}

    /**
     * @param  array<string, mixed>  $json  一筆 GitHub repo
     */
    public static function fromApi(array $json): self
    {
        return new self(
            fullName: (string) $json['full_name'],
            private: (bool) ($json['private'] ?? false),
            defaultBranch: $json['default_branch'] ?? null,
            language: $json['language'] ?? null,
        );
    }
}
