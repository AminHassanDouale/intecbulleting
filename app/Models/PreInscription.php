<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PreInscription extends Model
{
    protected $fillable = [
        'academic_year',
        'student_firstname',
        'student_lastname',
        'student_birth_date',
        'student_gender',
        'niveau_souhaite',
        'student_photo',
        'student_birth_certificate',
        'parent_documents',
        'parent_name',
        'parent_phone',
        'parent_email',
        'message',
        'status',
        'admin_notes',
    ];

    protected $casts = [
        'student_birth_date' => 'date',
        'parent_documents'   => 'array',
    ];

    public function getStudentFullNameAttribute(): string
    {
        return $this->student_firstname . ' ' . $this->student_lastname;
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending'   => 'En attente',
            'contacted' => 'Contacté',
            'accepted'  => 'Accepté',
            'rejected'  => 'Refusé',
            default     => $this->status,
        };
    }

    public static function niveaux(): array
    {
        return [
            'PS'   => 'Petite Section (PS)',
            'MS'   => 'Moyenne Section (MS)',
            'GS'   => 'Grande Section (GS)',
            'CP'   => 'CP — Cours Préparatoire',
            'CE1'  => 'CE1 — Cours Élémentaire 1',
            'CE2'  => 'CE2 — Cours Élémentaire 2',
            'CM1'  => 'CM1 — Cours Moyen 1',
            'CM2'  => 'CM2 — Cours Moyen 2',
            '6ème' => '6ème — Sixième (Collège)',
            '5ème' => '5ème — Cinquième (Collège)',
            '4ème' => '4ème — Quatrième (Collège)',
            '3ème' => '3ème — Troisième (Collège)',
            '2nde' => '2nde — Seconde (Lycée)',
            '1ère' => '1ère — Première (Lycée)',
            'Tle'  => 'Terminale (Lycée)',
        ];
    }
}
