<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AudioOverview extends Model
{
    protected $fillable = [
        'user_id',
        'notebook_id',
        'status',
        'storage_path',
        'duration_seconds',
        'script',
        'error',
    ];

    public function notebook(): BelongsTo
    {
        return $this->belongsTo(Notebook::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
