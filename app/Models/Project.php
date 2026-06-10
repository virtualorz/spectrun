<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'full_name',
        'display_name',
        'tech_stack',
        'default_branch',
        'is_private',
        'has_specflow',
        'is_tracked',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
            'has_specflow' => 'boolean',
            'is_tracked' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function changes(): HasMany
    {
        return $this->hasMany(ProjectChange::class);
    }
}
