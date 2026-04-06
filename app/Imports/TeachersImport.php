<?php

namespace App\Imports;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import teachers from Excel/CSV.
 *
 * Expected header row (row 1):
 *   Nom | Email | Mot de Passe | Code Classe
 *
 * - Mot de Passe : optional (defaults to "Intec@2026")
 * - Code Classe  : optional — classroom code to assign the teacher to (e.g. "CP", "CPA")
 */
class TeachersImport implements ToModel, WithHeadingRow
{
    public int   $imported     = 0;
    public int   $skipped      = 0;
    public int   $updated      = 0;
    public array $importErrors = [];

    private const DEFAULT_PASSWORD = 'Intec@2026';

    public function model(array $row): ?User
    {
        // Skip fully empty rows
        if (empty(array_filter(array_values($row), fn($v) => $v !== null && $v !== ''))) {
            return null;
        }

        try {
            $name  = trim((string) ($row['nom']           ?? ''));
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            $pass  = trim((string) ($row['mot_de_passe']  ?? ''));
            $code  = strtoupper(trim((string) ($row['code_classe'] ?? '')));

            if (!$name) {
                $this->importErrors[] = "Nom manquant (ligne ignorée)";
                $this->skipped++;
                return null;
            }

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->importErrors[] = "Email invalide ou manquant pour : {$name}";
                $this->skipped++;
                return null;
            }

            // ── Duplicate check ───────────────────────────────────────────────
            $existing = User::where('email', $email)->first();

            if ($existing) {
                // Update name if changed
                $changed = false;
                if ($existing->name !== $name) {
                    $existing->name = $name;
                    $changed = true;
                }
                if ($pass) {
                    $existing->password = Hash::make($pass);
                    $changed = true;
                }
                if ($changed) {
                    $existing->save();
                    $this->updated++;
                } else {
                    $this->importErrors[] = "Déjà en base (non modifié) : {$email}";
                    $this->skipped++;
                }

                // Assign classroom even for existing user
                $this->assignClassroom($existing, $code, $name);

                return null; // ToModel must return null for updates
            }

            // ── Create new teacher ────────────────────────────────────────────
            $user = new User([
                'name'     => $name,
                'email'    => $email,
                'password' => Hash::make($pass ?: self::DEFAULT_PASSWORD),
            ]);

            $user->save();
            $user->assignRole('teacher');

            $this->assignClassroom($user, $code, $name);

            $this->imported++;

            return null; // Already saved manually (needed for role assignment before return)

        } catch (\Throwable $e) {
            $this->importErrors[] = "Erreur : " . $e->getMessage();
            Log::error('TeachersImport', ['row' => $row, 'error' => $e->getMessage()]);
            $this->skipped++;
            return null;
        }
    }

    private function assignClassroom(User $user, string $code, string $name): void
    {
        if (!$code) return;

        // Support "CPA" → code="CP" section="A" or just "CP"
        $section = '';
        if (strlen($code) > 1 && ctype_alpha(substr($code, -1)) && !ctype_alpha($code)) {
            $section = substr($code, -1);
            $code    = substr($code, 0, -1);
        }

        $query = Classroom::where('code', $code);
        if ($section) $query->where('section', $section);

        $classroom = $query->first();

        if (!$classroom) {
            $this->importErrors[] = "Classe introuvable « {$code}{$section} » pour {$name} (enseignant créé sans classe)";
            return;
        }

        $classroom->teacher_id = $user->id;
        $classroom->save();
    }

    public function getStats(): array
    {
        return [
            'imported' => $this->imported,
            'updated'  => $this->updated,
            'skipped'  => $this->skipped,
            'errors'   => $this->importErrors,
        ];
    }
}
