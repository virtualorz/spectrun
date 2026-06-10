<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeTask extends Model
{
    protected $fillable = [
        'change_id',
        'position',
        'description',
        'is_done',
    ];

    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
        ];
    }

    public function change(): BelongsTo
    {
        return $this->belongsTo(ProjectChange::class, 'change_id');
    }
}
