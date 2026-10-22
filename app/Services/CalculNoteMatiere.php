<?php

namespace App\Services;

class CalculNoteMatiere
{
    public function calculer(array $notesTd, float $noteExamen): float
    {
        $moyenneTd = array_sum($notesTd) / \count($notesTd);

        return round(($moyenneTd * 0.4) + ($noteExamen * 0.6), 2);
    }
}
