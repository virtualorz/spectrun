<?php

namespace App\Core\Dtos\User;

readonly class CreateUserDto
{
    public function __construct(
        public string $account,
        public string $password,
        public string $accessToken,
    ) {}

    /**
     * 對應 users 欄位;password/access_token 由 User model 的 cast 處理(勿自行 Hash/Crypt)。
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'account' => $this->account,
            'password' => $this->password,
            'access_token' => $this->accessToken,
        ];
    }
}
