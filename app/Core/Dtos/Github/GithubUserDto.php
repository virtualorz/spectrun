<?php

namespace App\Core\Dtos\Github;

readonly class GithubUserDto
{
    public function __construct(
        public string $login,
        public int $id,
        public ?string $name,
        public ?string $avatarUrl,
    ) {}

    /**
     * @param  array<string, mixed>  $json  GitHub GET /user response
     */
    public static function fromApi(array $json): self
    {
        return new self(
            login: (string) $json['login'],
            id: (int) $json['id'],
            name: $json['name'] ?? null,
            avatarUrl: $json['avatar_url'] ?? null,
        );
    }
}
