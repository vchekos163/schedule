<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $fillable = ['name', 'capacity', 'purpose'];

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'room_subject', 'room_id', 'subject_id');
    }
}

