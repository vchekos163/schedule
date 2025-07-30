<?php
// app/Models/Teacher.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    protected $fillable = [
        'user_id', 'working_days', 'time_slots', 'max_lessons', 'max_gaps'
    ];

    protected $casts = [
        'availability' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function coTeachers()
    {
        return $this->belongsToMany(Teacher::class, 'co_teacher_teacher', 'teacher_id', 'co_teacher_id');
    }

    public function isCoTeacherOf()
    {
        return $this->belongsToMany(Teacher::class, 'co_teacher_teacher', 'co_teacher_id', 'teacher_id');
    }
}
