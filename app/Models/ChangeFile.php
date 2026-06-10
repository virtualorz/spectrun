<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeFile extends Model
{
    protected $fillable = [
        'change_id',
        'path',
        'change_kind',
    ];

    public function change(): BelongsTo
    {
        return $this->belongsTo(ProjectChange::class, 'change_id');
    }
}
