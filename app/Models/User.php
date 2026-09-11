<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'role',
        'password',
        'status',
        'must_change_password',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'must_change_password' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    public function representativeClass(): HasOne
    {
        return $this->hasOne(
            SchoolClass::class,
            'representative_teacher_id'
        );
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(
            TeacherAssignment::class,
            'teacher_id'
        );
    }

    public function teachingClasses(): BelongsToMany
    {
        return $this->belongsToMany(
            SchoolClass::class,
            'teacher_assignments',
            'teacher_id',
            'school_class_id'
        )
            ->withPivot([
                'course_id',
                'status',
            ])
            ->withTimestamps();
    }

    public function teachingCourses(): BelongsToMany
    {
        return $this->belongsToMany(
            Course::class,
            'teacher_assignments',
            'teacher_id',
            'course_id'
        )
            ->withPivot([
                'school_class_id',
                'status',
            ])
            ->withTimestamps();
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(
            new ResetPasswordNotification($token)
        );
    }
}
