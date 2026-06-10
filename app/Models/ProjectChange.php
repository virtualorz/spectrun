<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectChange extends Model
{
    protected $fillable = [
        'project_id',
        'number',
        'slug',
        'title',
        'problem',
        'base_branch',
        'status',
        'issued_at',
        'designed_at',
        'ran_at',
        'closed_at',
        'tokens_at_new',
        'tokens_at_close',
        'deviation',
        'decisions_done',
        'decisions_total',
        'tasks_done',
        'tasks_total',
        'discussion_count',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'designed_at' => 'datetime',
            'ran_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ChangeDecision::class, 'change_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ChangeTask::class, 'change_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ChangeFile::class, 'change_id');
    }

    public function discussions(): HasMany
    {
        return $this->hasMany(ChangeDiscussion::class, 'change_id');
    }
}
