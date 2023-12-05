<?php

namespace App\Http\Controllers;

use App\Models\Scorecard;

class ScorecardController extends Controller
{
    public function create()
    {
        return view('scorecards.create');
    }

    public function show(Scorecard $scorecard)
    {
        return view('scorecards.show', [
            'scorecard' => $scorecard,
        ]);
    }
}
