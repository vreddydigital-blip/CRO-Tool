<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversionEvent extends Model
{
    protected $fillable = ['project_id', 'visitor_event_id', 'name', 'value', 'occurred_at', 'payload'];
    protected $casts = ['payload' => 'array', 'occurred_at' => 'datetime', 'value' => 'float'];

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
}
