<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    protected $fillable = [
        'scan_id', 'url', 'normalized_url', 'status_code', 'depth', 'title', 'meta_description',
        'word_count', 'html_hash', 'is_primary', 'load_time_ms', 'html_bytes', 'error', 'fetched_at',
    ];

    protected $casts = ['is_primary' => 'boolean', 'fetched_at' => 'datetime'];

    public function scan(): BelongsTo { return $this->belongsTo(Scan::class); }
    public function metrics(): HasMany { return $this->hasMany(PageMetric::class); }
    public function findings(): HasMany { return $this->hasMany(Finding::class); }

    public function metric(string $key, $default = null)
    {
        $row = $this->metrics->firstWhere('key', $key);
        return $row ? $row->value : $default;
    }
}
