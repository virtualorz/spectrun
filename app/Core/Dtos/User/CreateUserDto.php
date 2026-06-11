<?php

namespace App\Core\Dtos\User;

use DateTimeInterface;

readonly class CreateUserDto
{
    public function __construct(
        public string $account,
        public string $password,
        public string $accessToken,
        public string $githubUsername,
        public int $githubUserId,
        public ?string $avatarUrl,
        public DateTimeInterface $connectedAt,
    ) {}

    /**
     * 對應 users 欄位;password/access_token 由 User model 的 cast 處理(勿自行 Hash/Crypt)。
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'account' => $this->account,
            'password' => $this->password,
            'access_token' => $this->accessToken,
            'github_username' => $this->githubUsername,
            'github_user_id' => $this->githubUserId,
            'avatar_url' => $this->avatarUrl,
            'connected_at' => $this->connectedAt,
        ];
    }
}
