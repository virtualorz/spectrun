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

    /**
     * 並行批次偵測多個 repo 是否含 specflow/ 目錄。
     *
     * @param  array<int, array{full_name: string, ref?: ?string}>  $repos
     * @return array{flags: array<string, bool>, rateLimited: bool}
     */
    public function detectSpecflowDirs(string $token, array $repos): array;

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
