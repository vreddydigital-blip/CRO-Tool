<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Integration extends Model
{
    protected $fillable = ['project_id', 'user_id', 'provider', 'credentials', 'config', 'status', 'last_synced_at'];
    protected $casts = ['credentials' => 'encrypted:array', 'config' => 'array', 'last_synced_at' => 'datetime'];
    protected $hidden = ['credentials'];

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
