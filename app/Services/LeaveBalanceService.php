<?php

namespace App\Services;

use Carbon\Carbon;

class LeaveBalanceService
{
    public function calendarDaysByYear(string $startDate, string $endDate): array
    {
        $daysByYear = [];
        $date = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        while ($date->lte($end)) {
            $daysByYear[$date->year] = ($daysByYear[$date->year] ?? 0) + 1;
            $date->addDay();
        }

        return $daysByYear;
    }
}