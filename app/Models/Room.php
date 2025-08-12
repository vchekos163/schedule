<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $fillable = ['name', 'code', 'capacity', 'purpose'];

    public function subjects()
    {
        return $this->belongsToMany(Subject::class)->withPivot('priority');
    }
}

