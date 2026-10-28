<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\CompanySettings;
use App\Models\User;
use Carbon\CarbonImmutable;

class AttendanceStatusService
{
    public function resolve(
        User $user,
        CarbonImmutable $date,
        ?Attendance $attendance,
        bool $approvedLeave,
        bool $holiday,
        CompanySettings $settings,
    ): string {
        $timezone = $settings->timezone ?: config('app.timezone');
        $date = $date->setTimezone($timezone)->startOfDay();

        if ($attendance?->check_in) {
            $checkIn = CarbonImmutable::parse(
                $date->toDateString() . ' ' . $attendance->check_in,
                $timezone,
            );
            $workStart = CarbonImmutable::parse(
                $date->toDateString() . ' ' . ($settings->work_start ?: '09:00'),
                $timezone,
            );

            if ($attendance->check_out && in_array($attendance->status, ['EARLY_DEPARTURE', 'HALF_DAY'], true)) {
                $checkOut = CarbonImmutable::parse(
                    $date->toDateString() . ' ' . $attendance->check_out,
                    $timezone,
                );
                $workEnd = CarbonImmutable::parse(
                    $date->toDateString() . ' ' . ($settings->work_end ?: '17:00'),
                    $timezone,
                );
                if ($checkOut->lt($workEnd)) {
                    if ($attendance->status === 'HALF_DAY') {
                        $midpoint = $workStart->addMinutes((int) ($workStart->diffInMinutes($workEnd) / 2));

                        return $checkOut->lte($midpoint) ? 'HALF_DAY' : 'EARLY_DEPARTURE';
                    }

                    return 'EARLY_DEPARTURE';
                }
            }

            return $checkIn->gt($workStart) ? 'LATE' : 'PRESENT';
        }

        if ($approvedLeave) {
            return 'LEAVE';
        }

        if ($holiday) {
            return 'HOLIDAY';
        }

        if (!in_array(strtoupper($date->format('D')), $settings->working_days ?? [], true)) {
            return 'NON_WORKING_DAY';
        }

        $now = CarbonImmutable::now($timezone);
        $workEnd = CarbonImmutable::parse(
            $date->toDateString() . ' ' . ($settings->work_end ?: '17:00'),
            $timezone,
        );
        $absenceAllowed = $date->lt($now->startOfDay())
            || ($date->isSameDay($now) && $now->greaterThanOrEqualTo($workEnd));

        return $absenceAllowed ? 'ABSENT' : 'NOT_CHECKED_IN';
    }
}
