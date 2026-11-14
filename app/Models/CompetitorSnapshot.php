<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitorSnapshot extends Model
{
    protected $fillable = [
        'competitor_id', 'scan_id', 'overall_score', 'category_scores', 'cta_summary',
        'trust_summary', 'content_summary', 'form_summary', 'captured_at',
    ];

    protected $casts = [
        'category_scores' => 'array', 'cta_summary' => 'array', 'trust_summary' => 'array',
        'content_summary' => 'array', 'form_summary' => 'array', 'captured_at' => 'datetime',
        'overall_score' => 'float',
    ];

    public function competitor(): BelongsTo { return $this->belongsTo(Competitor::class); }
    public function scan(): BelongsTo { return $this->belongsTo(Scan::class); }
}
