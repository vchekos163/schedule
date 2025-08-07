<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $fillable = [
        'name',
        'code',
        'priority',
        'color',
    ];

    public static function getPriority(): array
    {
        return [
            'must' => 'MUST',
            'spec' => 'SPEC',
            'consult' => 'CONSULT',
            'homework' => 'H/W',
            'online_consult' => 'ONLINE CONSULT',
        ];
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('quantity');
    }

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'room_subject', 'subject_id', 'room_id');
    }
}
