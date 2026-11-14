<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['user_id', 'name', 'url', 'primary_url', 'description', 'is_primary'];

    protected $casts = ['is_primary' => 'boolean'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function scans(): HasMany { return $this->hasMany(Scan::class); }
    public function competitors(): HasMany { return $this->hasMany(Competitor::class); }
    public function reports(): HasMany { return $this->hasMany(Report::class); }
    public function integrations(): HasMany { return $this->hasMany(Integration::class); }
    public function experiments(): HasMany { return $this->hasMany(Experiment::class); }

    public function latestScan(): ?Scan
    {
        return $this->scans()->latest('id')->first();
    }

    public function scopeOwnedBy($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
