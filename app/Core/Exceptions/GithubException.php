<?php

namespace App\Core\Exceptions;

use RuntimeException;

class GithubException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : $reason);
    }

    public static function invalidToken(): self
    {
        return new self('invalid_token', 'GitHub token 無效');
    }

    public static function rateLimited(): self
    {
        return new self('rate_limited', 'GitHub API rate limit 已用盡');
    }

    public static function upstreamError(string $message = ''): self
    {
        return new self('upstream_error', $message !== '' ? $message : 'GitHub 上游錯誤');
    }

    public static function badResponse(string $message = ''): self
    {
        return new self('bad_response', $message !== '' ? $message : 'GitHub 回傳非預期格式');
    }
}
