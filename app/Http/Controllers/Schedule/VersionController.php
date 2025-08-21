<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\Version;
use Illuminate\Http\Request;

class VersionController extends Controller
{
    public function create(Request $request)
    {
        $name = $request->input('name', '');
        if ($name === '') {
            return response()->json(['error' => 'Name is required'], 422);
        }

        $version = Version::create(['name' => $name]);

        return response()->json(['id' => $version->id]);
    }
}
