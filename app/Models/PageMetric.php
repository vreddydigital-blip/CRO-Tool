<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageMetric extends Model
{
    protected $fillable = ['page_id', 'key', 'value'];
    protected $casts = ['value' => 'array'];

    public function page(): BelongsTo { return $this->belongsTo(Page::class); }
}
