<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeDecision extends Model
{
    protected $fillable = [
        'change_id',
        'position',
        'title',
        'body',
        'is_checked',
    ];

    protected function casts(): array
    {
        return [
            'is_checked' => 'boolean',
        ];
    }

    public function change(): BelongsTo
    {
        return $this->belongsTo(ProjectChange::class, 'change_id');
    }
}
