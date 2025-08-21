<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    protected $fillable = [
        'subject_id',
        'room_id',
        'date',
        'period',
        'start_time',
        'end_time',
        'fixed',
    ];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function teachers()
    {
        return $this->belongsToMany(Teacher::class, 'lesson_teacher');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'lesson_users', 'lesson_id', 'user_id');
    }

    public function version()
    {
        return $this->belongsTo(Version::class);
    }
}
