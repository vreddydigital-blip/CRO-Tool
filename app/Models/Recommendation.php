<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recommendation extends Model
{
    protected $fillable = [
        'scan_id', 'finding_id', 'title', 'body', 'type', 'priority_label', 'priority_score',
        'impact', 'confidence', 'effort', 'reach', 'source',
    ];

    protected $casts = ['priority_score' => 'float'];

    public function scan(): BelongsTo { return $this->belongsTo(Scan::class); }
    public function finding(): BelongsTo { return $this->belongsTo(Finding::class); }
}
