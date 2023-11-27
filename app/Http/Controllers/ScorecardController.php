<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ScorecardController extends Controller
{
    public function create()
    {
        return view('scorecards.create');
    }
}
