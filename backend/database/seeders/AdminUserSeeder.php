<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Create admin test accounts for development and testing.
     *
     * Creates 2 admin accounts:
     * 1. Main admin - admin@anjem.app (password: admin123)
     * 2. Test admin - test@anjem.app (password: test123)
     */
    public function run(): void
    {
        // Main admin account
        $mainAdmin = User::firstOrCreate(
            ['email' => 'admin@anjem.app'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'email_verified_at' => now(),
                'is_active' => true,
                'last_active_at' => now(),
            ]
        );

        // Test admin account (for API testing)
        $testAdmin = User::firstOrCreate(
            ['email' => 'test@anjem.app'],
            [
                'name' => 'Test Admin',
                'password' => Hash::make('test123'),
                'role' => 'admin',
                'email_verified_at' => now(),
                'is_active' => true,
                'last_active_at' => now(),
            ]
        );

        $this->command->info('✅ Admin users created successfully:');
        $this->command->info('   - admin@anjem.app (password: admin123)');
        $this->command->info('   - test@anjem.app (password: test123)');
        $this->command->newLine();
        $this->command->warn('⚠️  Remember to change these passwords in production!');
    }
}
