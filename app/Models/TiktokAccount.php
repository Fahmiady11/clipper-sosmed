<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiktokAccount extends Model
{
    protected $fillable = [
        'user_id',
        'open_id',
        'display_name',
        'avatar_url',
        'access_token',
        'refresh_token',
        'access_expires_at',
        'refresh_expires_at',
        'scope',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected $casts = [
        'access_token'       => 'encrypted',
        'refresh_token'      => 'encrypted',
        'access_expires_at'  => 'datetime',
        'refresh_expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
