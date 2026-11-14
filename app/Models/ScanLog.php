<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanLog extends Model
{
    public $timestamps = false;
    protected $fillable = ['scan_id', 'level', 'message', 'context', 'created_at'];
    protected $casts = ['context' => 'array', 'created_at' => 'datetime'];

    public function scan(): BelongsTo { return $this->belongsTo(Scan::class); }
}
