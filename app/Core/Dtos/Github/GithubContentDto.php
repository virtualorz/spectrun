<?php

namespace App\Core\Dtos\Github;

readonly class GithubContentDto
{
    public function __construct(
        public string $path,
        public string $type,
        public ?string $encoding,
        public ?string $content,
    ) {}

    /**
     * @param  array<string, mixed>  $json  GitHub GET /repos/{o}/{r}/contents/{path} response(單檔)
     */
    public static function fromApi(array $json): self
    {
        $encoding = $json['encoding'] ?? null;
        $raw = $json['content'] ?? null;

        $content = $raw !== null && $encoding === 'base64'
            ? (base64_decode(str_replace("\n", '', $raw), true) ?: null)
            : $raw;

        return new self(
            path: (string) ($json['path'] ?? ''),
            type: (string) ($json['type'] ?? 'file'),
            encoding: $encoding,
            content: $content,
        );
    }
}
