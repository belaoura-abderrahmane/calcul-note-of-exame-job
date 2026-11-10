<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Services\ScoreCalculator;

class PrintController extends Controller
{
    public function competition(Competition $competition)
    {
        return view('print.competition', [
            'competition' => $competition,
            'candidates' => $competition->rankedCandidates(),
            'labels' => ScoreCalculator::labels(),
            'maxima' => ScoreCalculator::make()->maxima(),
        ]);
    }
}
