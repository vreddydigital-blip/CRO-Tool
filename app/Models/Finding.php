<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Finding extends Model
{
    protected $fillable = [
        'scan_id', 'page_id', 'rule_id', 'category', 'rule_name', 'severity', 'confidence',
        'evidence', 'explanation', 'recommended_action', 'implementation_guidance', 'limitations',
        'impact', 'effort', 'reach', 'priority_score', 'priority_label', 'status',
    ];

    protected $casts = ['priority_score' => 'float'];

    public function scan(): BelongsTo { return $this->belongsTo(Scan::class); }
    public function page(): BelongsTo { return $this->belongsTo(Page::class); }

    public function severityRank(): int
    {
        return ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1][$this->severity] ?? 0;
    }
}
