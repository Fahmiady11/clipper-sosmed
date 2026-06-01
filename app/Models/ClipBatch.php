<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ClipBatch extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'youtube_url',
        'video_id',
        'layout_mode',
        'clip_count',
        'status',
        'error_msg',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected $casts = [
        'clip_count' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function clips(): HasMany
    {
        return $this->hasMany(ClipJob::class, 'batch_id');
    }
}
