<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $fillable = [
        'name'
    ];

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('quantity');
    }

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'room_subject', 'subject_id', 'room_id');
    }
}
