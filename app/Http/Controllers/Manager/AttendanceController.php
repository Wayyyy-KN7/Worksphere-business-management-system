<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function history(Request $request)
    {
        abort_unless($request->user()->canViewCompanyAttendance(), 403);

        return redirect()->route('hr.attendance.history', $request->query());
    }
}