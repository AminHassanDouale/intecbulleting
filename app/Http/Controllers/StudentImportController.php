<?php

namespace App\Http\Controllers;

use App\Imports\StudentsImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class StudentImportController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate([
            'importFile' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ], [
            'importFile.required' => 'Veuillez sélectionner un fichier Excel.',
            'importFile.mimes'    => 'Le fichier doit être au format .xlsx, .xls ou .csv.',
            'importFile.max'      => 'Le fichier ne doit pas dépasser 10 Mo.',
        ]);

        $importer = new StudentsImport();

        try {
            Excel::import($importer, $request->file('importFile'));

            $stats = $importer->getStats();

            $parts = ["{$stats['imported']} ajouté(s)"];
            if ($stats['duplicates']  > 0) $parts[] = "{$stats['duplicates']} doublon(s) ignoré(s)";
            if ($stats['alreadyInDb'] > 0) $parts[] = "{$stats['alreadyInDb']} déjà en base";
            if ($stats['skipped']     > 0) $parts[] = "{$stats['skipped']} erreur(s)";
            $msg = implode(', ', $parts) . '.';

            if (! empty($stats['errors'])) {
                $preview = implode(' | ', array_slice($stats['errors'], 0, 5));
                return back()->with('import_warning', "Import terminé. {$msg} — {$preview}");
            }

            return back()->with('import_success', "Import réussi ! {$msg}");

        } catch (\Throwable $e) {
            return back()->with('import_error', 'Erreur : ' . $e->getMessage());
        }
    }
}
