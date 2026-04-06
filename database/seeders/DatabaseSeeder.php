<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndUsersSeeder::class,      // roles + AcademicYear + admin/staff accounts
            SchoolStructureSeeder::class,    // niveaux + subjects + competences (all levels)
            FullSchoolSeeder::class,         // classrooms + 1 teacher each (no students)
        ]);
    }
}
