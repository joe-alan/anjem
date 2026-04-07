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
            ['email' => 'admin@anjem.app'],
            [
                'name' => 'Admin',
                'password' => Hash::make('admin123'),
                'role' => 'both',
                'email_verified_at' => now(),
                'is_active' => true,
                'last_active_at' => now(),
            ]
        );

        // Force is_admin since it's not in $fillable
        $admin->forceFill(['is_admin' => true])->save();

        $this->command->info('Admin user created: admin@anjem.app');
        $this->command->warn('Change the default password immediately!');
    }
}
