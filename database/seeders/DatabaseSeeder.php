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
            FullSchoolSeeder::class,         // CE1 A classroom + 10 students + 3 subject teachers
        ]);
    }
}
