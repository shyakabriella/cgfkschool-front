<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DepartmentProgram extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'department_id',
        'name',
        'code',
        'type',
        'description',
        'status',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function activeClasses(): HasMany
    {
        return $this->classes()->where('status', 'active');
    }

    public function isTrade(): bool
    {
        return $this->type === 'trade';
    }

    public function isOption(): bool
    {
        return $this->type === 'option';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
