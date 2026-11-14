<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competitor extends Model
{
    protected $fillable = ['project_id', 'name', 'url'];

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function snapshots(): HasMany { return $this->hasMany(CompetitorSnapshot::class); }
    public function latestSnapshot(): ?CompetitorSnapshot { return $this->snapshots()->latest('id')->first(); }
}
