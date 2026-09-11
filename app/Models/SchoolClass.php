<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolClass extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'school_classes';

    protected $fillable = [
        'department_program_id',
        'representative_teacher_id',
        'name',
        'code',
        'level',
        'description',
        'status',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(
            DepartmentProgram::class,
            'department_program_id'
        );
    }

    public function representativeTeacher(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'representative_teacher_id'
        );
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(
            TeacherAssignment::class,
            'school_class_id'
        );
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'teacher_assignments',
            'school_class_id',
            'teacher_id'
        )
            ->withPivot([
                'course_id',
                'status',
            ])
            ->withTimestamps();
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(
            Course::class,
            'teacher_assignments',
            'school_class_id',
            'course_id'
        )
            ->withPivot([
                'teacher_id',
                'status',
            ])
            ->withTimestamps();
    }

    public function students(): HasMany
    {
        return $this->hasMany(
            Student::class,
            'school_class_id'
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
