<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'jonathanalano123@gmail.com'],
            [
                'name' => 'Jonathan Alano',
                'password' => Hash::make('d29pbGFoY2lr'),
                'role' => 'both',
                'email_verified_at' => now(),
                'is_active' => true,
                'last_active_at' => now(),
            ]
        );

        // Force is_admin since it's not in $fillable
        $admin->forceFill(['is_admin' => true])->save();

        $admin2 = User::firstOrCreate(
            ['email' => 'fiqormhd@gmail.com'],
            [
                'name' => 'Muhhamad Dzulfiqor',
                'password' => Hash::make('am9lZ2FudGVuZzQx'),
                'role' => 'both',
                'email_verified_at' => now(),
                'is_active' => true,
                'last_active_at' => now(),
            ]
        );

        $admin2->forceFill(['is_admin' => true])->save();

        $this->command->info('Admin users created: jonathanalano123@gmail.com, fiqormhd@gmail.com');
    }
}
