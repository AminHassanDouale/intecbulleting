<?php

namespace App\Imports;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Validators\Failure;

/**
 * Import students from Excel file
 * Expected columns: Matricule, Nom, Prenom, Date Naissance, Genre, Code Classe, Section
 */
class StudentsImport implements
    ToModel,
    WithHeadingRow,
    WithValidation,
    SkipsOnError,
    SkipsOnFailure
{
    use SkipsErrors, SkipsFailures;

    public int $imported = 0;
    public int $skipped  = 0;
    public int $updated  = 0;
    public array $errors = [];

    public function model(array $row): ?Student
    {
        try {
            // Find classroom
            $classroom = Classroom::where('code', trim($row['code_classe'] ?? ''))
                ->where('section', trim($row['section'] ?? ''))
                ->first();

            if (!$classroom) {
                $this->errors[] = "Classroom not found: " . ($row['code_classe'] ?? '') . " " . ($row['section'] ?? '');
                $this->skipped++;
                return null;
            }

            // Get current academic year
            $year = AcademicYear::where('is_current', true)->first();

            if (!$year) {
                $this->errors[] = "No current academic year found";
                $this->skipped++;
                return null;
            }

            // Parse birth date
            $birthDate = $this->parseBirthDate($row['date_naissance'] ?? null);

            if (!$birthDate) {
                $this->errors[] = "Invalid birth date for: " . ($row['nom'] ?? '') . " " . ($row['prenom'] ?? '');
                $this->skipped++;
                return null;
            }

            $matricule = !empty($row['matricule']) ? trim($row['matricule']) : null;

            // Check if student exists
            if ($matricule) {
                $existing = Student::where('matricule', $matricule)->first();

                if ($existing) {
                    // Update existing student
                    $existing->update([
                        'first_name'       => trim($row['prenom']),
                        'last_name'        => trim($row['nom']),
                        'birth_date'       => $birthDate,
                        'gender'           => strtoupper(trim($row['genre'])),
                        'classroom_id'     => $classroom->id,
                        'academic_year_id' => $year->id,
                    ]);

                    $this->updated++;
                    return null; // Don't create new model
                }
            }

            // Create new student
            $this->imported++;

            return new Student([
                'matricule'        => $matricule,
                'first_name'       => trim($row['prenom']),
                'last_name'        => trim($row['nom']),
                'birth_date'       => $birthDate,
                'gender'           => strtoupper(trim($row['genre'])),
                'classroom_id'     => $classroom->id,
                'academic_year_id' => $year->id,
            ]);

        } catch (\Throwable $e) {
            $this->errors[] = "Error processing row: " . $e->getMessage();
            Log::error('StudentsImport error', [
                'row' => $row,
                'error' => $e->getMessage(),
            ]);
            $this->skipped++;
            return null;
        }
    }

    private function parseBirthDate(?string $dateString): ?string
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            // Try different date formats
            if (str_contains($dateString, '/')) {
                // d/m/Y format
                return Carbon::createFromFormat('d/m/Y', trim($dateString))->format('Y-m-d');
            } elseif (str_contains($dateString, '-')) {
                // Y-m-d or d-m-Y format
                $parts = explode('-', $dateString);
                if (strlen($parts[0]) === 4) {
                    // Y-m-d
                    return Carbon::parse(trim($dateString))->format('Y-m-d');
                } else {
                    // d-m-Y
                    return Carbon::createFromFormat('d-m-Y', trim($dateString))->format('Y-m-d');
                }
            } else {
                // Try general parse
                return Carbon::parse(trim($dateString))->format('Y-m-d');
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to parse birth date', [
                'input' => $dateString,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function rules(): array
    {
        return [
            'nom'         => 'required|string|max:255',
            'prenom'      => 'required|string|max:255',
            'genre'       => 'required|in:M,F,m,f',
            'code_classe' => 'required|string',
            'section'     => 'required|string',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'nom.required'         => 'Le nom est obligatoire',
            'prenom.required'      => 'Le prénom est obligatoire',
            'genre.required'       => 'Le genre est obligatoire',
            'genre.in'             => 'Le genre doit être M ou F',
            'code_classe.required' => 'Le code classe est obligatoire',
            'section.required'     => 'La section est obligatoire',
        ];
    }

    public function getStats(): array
    {
        return [
            'imported' => $this->imported,
            'updated'  => $this->updated,
            'skipped'  => $this->skipped,
            'errors'   => $this->errors,
        ];
    }
}
