<?php

namespace App\Repositories;

use App\Core\Dtos\User\CreateUserDto;
use App\Models\User;

class UserRepository
{
    public function hasAnyUser(): bool
    {
        return User::query()->exists();
    }

    /**
     * 取唯一 user(單機單人)。
     */
    public function current(): ?User
    {
        return User::query()->first();
    }

    public function createFromSetup(CreateUserDto $dto): User
    {
        // password / access_token 由 User model 的 hashed / encrypted cast 處理
        return User::create($dto->toArray());
    }
}
