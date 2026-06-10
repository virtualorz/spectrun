<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GithubConnection extends Model
{
    protected $fillable = [
        'github_username',
        'github_user_id',
        'avatar_url',
        'access_token',
        'connected_at',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'connected_at' => 'datetime',
        ];
    }
}
