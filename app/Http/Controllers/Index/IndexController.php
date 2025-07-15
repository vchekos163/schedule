<?php

namespace App\Http\Controllers\Index;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class IndexController extends Controller
{
    /**
     * Show the default homepage.
     * Redirect users to role-specific pages if authenticated.
     */
    public function index()
    {
        return view('index.index');
    }
}
