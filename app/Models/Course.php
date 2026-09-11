<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'hours',
        'periods',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'hours' => 'integer',
            'periods' => 'integer',
        ];
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(
            TeacherAssignment::class,
            'course_id'
        );
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'teacher_assignments',
            'course_id',
            'teacher_id'
        )
            ->withPivot([
                'school_class_id',
                'status',
            ])
            ->withTimestamps();
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(
            SchoolClass::class,
            'teacher_assignments',
            'course_id',
            'school_class_id'
        )
            ->withPivot([
                'teacher_id',
                'status',
            ])
            ->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
