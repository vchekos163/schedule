<?php
// app/Models/Teacher.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    protected $fillable = [
        'user_id',
        'max_lessons',
        'max_days',
        'max_gaps',
    ];

    protected $casts = [
        'availability' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);

    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class)->withPivot('quantity');
    }

    public function lessons()
    {
        return $this->belongsToMany(Lesson::class, 'lesson_teacher', 'teacher_id', 'lesson_id');
    }
}
