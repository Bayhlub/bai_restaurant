<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $staff = [
            ['name' => 'Admin',   'email' => 'admin@bai.local',   'role' => UserRole::Admin],
            ['name' => 'Kitchen', 'email' => 'kitchen@bai.local', 'role' => UserRole::Kitchen],
            ['name' => 'Cashier', 'email' => 'cashier@bai.local', 'role' => UserRole::Cashier],
        ];

        foreach ($staff as $member) {
            User::updateOrCreate(
                ['email' => $member['email']],
                $member + ['password' => 'password', 'email_verified_at' => now()],
            );
        }
    }
}
