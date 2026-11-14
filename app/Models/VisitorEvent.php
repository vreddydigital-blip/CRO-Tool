<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitorEvent extends Model
{
    protected $fillable = ['project_id', 'integration_id', 'event_name', 'source', 'occurred_at', 'payload'];
    protected $casts = ['payload' => 'array', 'occurred_at' => 'datetime'];

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
}
