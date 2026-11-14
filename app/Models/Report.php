<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $fillable = ['scan_id', 'project_id', 'user_id', 'filename', 'path', 'size_bytes', 'generated_at'];
    protected $casts = ['generated_at' => 'datetime'];

    public function scan(): BelongsTo { return $this->belongsTo(Scan::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
