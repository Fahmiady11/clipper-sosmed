<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClipJob extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'batch_id',
        'youtube_url',
        'layout_mode',
        'start_time',
        'end_time',
        'status',
        'file_path',
        'error_msg',
        'gemini_hook',
        'gemini_caption',
        'gemini_hashtags',
        'thumbnail_url',
        'overlay_text',
        'overlay_position',
        'overlay_color',
        'overlay_fontsize',
        'expires_at',
        'auto_caption',
        'caption_language',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected $casts = [
        'expires_at'      => 'datetime',
        'auto_caption'    => 'boolean',
        'start_time'      => 'integer',
        'end_time'        => 'integer',
        'overlay_fontsize' => 'integer',
        'gemini_hashtags' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
