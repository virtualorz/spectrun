<?php

namespace App\Core\Contracts\Github;

use App\Core\Dtos\Github\GithubContentDto;
use App\Core\Dtos\Github\GithubRepoDto;
use App\Core\Dtos\Github\GithubUserDto;

interface GithubServiceInterface
{
    public function verifyToken(string $token): bool;

    public function fetchUser(string $token): GithubUserDto;

    /**
     * @return array<int, GithubRepoDto>
     */
    public function listRepos(string $token): array;

    public function fetchRepoContent(string $token, string $fullName, string $path = ''): GithubContentDto;

    public function hasSpecflowDir(string $token, string $fullName): bool;

    public function fetchProjectMd(string $token, string $fullName): ?string;
}
