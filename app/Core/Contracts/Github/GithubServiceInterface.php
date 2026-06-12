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

    public function hasSpecflowDir(string $token, string $fullName, ?string $ref = null): bool;

    public function fetchProjectMd(string $token, string $fullName, ?string $ref = null): ?string;

    /**
     * @return array<int, string>
     */
    public function listSpecflowChanges(string $token, string $fullName, ?string $ref = null): array;

    public function fetchFileRaw(string $token, string $fullName, string $path, ?string $ref = null): ?string;

    /**
     * @return array<int, string>
     */
    public function listBranches(string $token, string $fullName): array;
}
