<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'status',
    ];

    public function programs(): HasMany
    {
        return $this->hasMany(DepartmentProgram::class);
    }

    public function activePrograms(): HasMany
    {
        return $this->programs()->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
