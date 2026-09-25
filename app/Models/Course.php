<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'hours',
        'periods',
        'description',
        'curriculum_path',
        'notes_path',
        'gemini_curriculum_file',
        'gemini_notes_file',
        'status',
    ];

    protected $appends = [
        'curriculum_url',
        'notes_url',
    ];

    public function getCurriculumUrlAttribute(): ?string
    {
        if (! $this->curriculum_path) {
            return null;
        }

        return url(Storage::url($this->curriculum_path));
    }

    public function getNotesUrlAttribute(): ?string
    {
        if (! $this->notes_path) {
            return null;
        }

        return url(Storage::url($this->notes_path));
    }

    protected function casts(): array
    {
        return [
            'hours' => 'integer',
            'periods' => 'integer',
            'gemini_curriculum_file' => 'array',
            'gemini_notes_file' => 'array',
        ];
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(
            Assessment::class,
            'course_id'
        );
    }

    public function learningUnits(): HasMany
    {
        return $this->hasMany(
            CourseLearningUnit::class
        )->orderBy('position');
    }

    public function teachingMaterials(): HasMany
    {
        return $this->hasMany(
            TeachingMaterial::class
        );
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
