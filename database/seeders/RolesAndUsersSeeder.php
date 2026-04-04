<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RolesAndUsersSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Roles ──────────────────────────────────────────────────────────
        $roles = ['admin', 'direction', 'pedagogie', 'finance', 'teacher', 'parent'];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // ── 2. Academic Year ──────────────────────────────────────────────────
        AcademicYear::firstOrCreate(
            ['label' => '2025/2026'],
            [
                'start_date' => '2025-09-01',
                'end_date'   => '2026-06-30',
                'is_current' => true,
            ]
        );

        // ── 3. Staff users ────────────────────────────────────────────────────
        $users = [
            [
                'name'     => 'Administrateur INTEC',
                'email'    => 'admin@intec.edu',
                'password' => Hash::make('password'),
                'role'     => 'admin',
            ],
            [
                'name'     => 'Directeur Général',
                'email'    => 'direction@intec.edu',
                'password' => Hash::make('password'),
                'role'     => 'direction',
            ],
            [
                'name'     => 'Responsable Pédagogique',
                'email'    => 'pedagogie@intec.edu',
                'password' => Hash::make('password'),
                'role'     => 'pedagogie',
            ],
            [
                'name'     => 'Service Financier',
                'email'    => 'finance@intec.edu',
                'password' => Hash::make('password'),
                'role'     => 'finance',
            ],
        ];

        foreach ($users as $data) {
            $role = $data['role'];
            unset($data['role']);

            $user = User::firstOrCreate(['email' => $data['email']], $data);

            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }
        }
    }
}
