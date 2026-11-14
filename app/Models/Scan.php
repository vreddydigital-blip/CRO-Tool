<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scan extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'user_id', 'url', 'status', 'progress', 'pages_crawled', 'pages_failed',
        'overall_score', 'confidence', 'evidence_coverage', 'critical_count', 'high_count',
        'medium_count', 'low_count', 'error_message', 'meta', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'overall_score' => 'float',
        'confidence' => 'float',
        'evidence_coverage' => 'float',
    ];

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function pages(): HasMany { return $this->hasMany(Page::class); }
    public function findings(): HasMany { return $this->hasMany(Finding::class); }
    public function recommendations(): HasMany { return $this->hasMany(Recommendation::class); }
    public function scores(): HasMany { return $this->hasMany(Score::class); }
    public function logs(): HasMany { return $this->hasMany(ScanLog::class); }
    public function reports(): HasMany { return $this->hasMany(Report::class); }

    public function isComplete(): bool { return $this->status === 'completed'; }

    public function scoreFor(string $category): ?float
    {
        return $this->scores->firstWhere('category', $category)?->score;
    }
}
