<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Experiment extends Model
{
    protected $fillable = ['project_id', 'name', 'hypothesis', 'status', 'variant_a', 'variant_b', 'started_at', 'ended_at', 'result'];
    protected $casts = ['result' => 'array', 'started_at' => 'datetime', 'ended_at' => 'datetime'];

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
}
