<?php

namespace App\Http\Controllers;

use App\Exports\GradeSheetExportCSV;
use Illuminate\Http\Request;

class GradeSheetCSVController extends Controller
{
    /**
     * Stream a CSV grade sheet download.
     *
     * Route: GET /grades/export-csv
     * Params: classroom_id, period, academic_year_id, niveau_code, (optional) teacher_id
     */
    public function export(Request $request)
    {
        $request->validate([
            'classroom_id'     => ['required', 'integer', 'exists:classrooms,id'],
            'period'           => ['required', 'string', 'in:T1,T2,T3'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'niveau_code'      => ['required', 'string'],
        ]);

        // Only allow teachers to export their own subjects; admins get all
        $user      = auth()->user();
        $teacherId = ($user->hasRole('teacher') && $user->subjects()->exists())
            ? $user->id
            : null;

        // Honour explicit teacher_id override for admins
        if ($request->filled('teacher_id') && ! $user->hasRole('teacher')) {
            $teacherId = (int) $request->teacher_id;
        }

        $export = new GradeSheetExportCSV(
            classroomId:    (int) $request->classroom_id,
            period:         $request->period,
            academicYearId: (int) $request->academic_year_id,
            niveauCode:     $request->niveau_code,
            teacherId:      $teacherId,
        );

        return $export->download();
    }
}