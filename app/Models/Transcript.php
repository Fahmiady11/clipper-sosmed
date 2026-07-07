<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transcript extends Model
{
    protected $fillable = [
        'clip_project_id',
        'language',
        'content',
    ];

    protected $casts = [
        'content' => 'array',
    ];

    public function clipProject(): BelongsTo
    {
        return $this->belongsTo(ClipProject::class);
    }
}
