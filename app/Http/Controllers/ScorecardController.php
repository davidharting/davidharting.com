<?php

namespace App\Http\Controllers;

use App\Models\Scorecard;
use Illuminate\Http\Request;

class ScorecardController extends Controller
{
    public function create()
    {
        return view('scorecards.create');
    }

    public function show(Scorecard $scorecard)
    {
        return view('scorecards.show', [
            'scorecard' => $scorecard
        ]);
    }
}
