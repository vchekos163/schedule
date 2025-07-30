<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;

class RoomsController extends Controller
{
    public function index()
    {
        return view('admin.rooms.index');
    }

    public function create()
    {
        return view('admin.rooms.edit');
    }

    public function edit($room_id)
    {
        $room = Room::findOrFail($room_id);

        return view('admin.rooms.edit', compact('room'));
    }
}
