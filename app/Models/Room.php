<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Lesson;

class Room extends Model
{
    protected $fillable = ['name', 'code', 'capacity', 'purpose'];

    public function subjects()
    {
        return $this->belongsToMany(Subject::class)->withPivot('priority');
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class);
    }
}

