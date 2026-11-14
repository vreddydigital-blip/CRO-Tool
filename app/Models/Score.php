<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Score extends Model
{
    protected $fillable = ['scan_id', 'category', 'score', 'weight', 'issues_count', 'evidence_coverage'];
    protected $casts = ['score' => 'float', 'weight' => 'float', 'evidence_coverage' => 'float'];

    public function scan(): BelongsTo { return $this->belongsTo(Scan::class); }
}
