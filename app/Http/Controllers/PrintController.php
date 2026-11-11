<?php

namespace App\Http\Controllers;

use App\Models\Competition;

class PrintController extends Controller
{
    public function competition(Competition $competition)
    {
        $calculator = $competition->calculator();

        return view('print.competition', [
            'competition' => $competition,
            'candidates' => $competition->rankedCandidates(),
            'labels' => collect($calculator->criterionLabels())->filter(fn (string $label, string $key): bool => $calculator->maxima()[$key] > 0)->all(),
            'maxima' => $calculator->maxima(),
            'maxTotal' => $calculator->maxTotal(),
        ]);
    }
}
