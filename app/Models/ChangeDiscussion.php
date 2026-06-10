<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeDiscussion extends Model
{
    protected $fillable = [
        'change_id',
        'position',
        'question',
        'conclusion',
        'impact',
        'discussed_on',
    ];

    protected function casts(): array
    {
        return [
            'discussed_on' => 'date',
        ];
    }

    public function change(): BelongsTo
    {
        return $this->belongsTo(ProjectChange::class, 'change_id');
    }
}
