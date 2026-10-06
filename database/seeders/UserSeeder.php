<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Root User (Full unrestricted bypass)
        $root = User::updateOrCreate(
            ['login_name' => 'root'],
            [
                'username'           => 'Root',
                'password'           => Hash::make('password'),
                'encrypted_password' => Crypt::encryptString('password'),
                'type'               => 'root',
                'status'             => true,
                'remark'             => null,
            ]
        );
        $rootRole = Role::where('name', 'root')->first();
        if ($rootRole) {
            $root->assignRole($rootRole);
        }

        // 2. Super Senior User (Full permissions)
        $superSenior = User::updateOrCreate(
            ['login_name' => 'supersenior'],
            [
                'username'           => 'Super Senior',
                'password'           => Hash::make('password'),
                'encrypted_password' => Crypt::encryptString('password'),
                'type'               => 'super_senior',
                'status'             => true,
                'remark'             => null,
            ]
        );
        $superSeniorRole = Role::where('name', 'super_senior')->first() ?? Role::where('name', 'Admin')->first();
        if ($superSeniorRole) {
            $superSenior->assignRole($superSeniorRole);
        }

        // 3. Senior User (View & Edit permissions)
        $senior = User::updateOrCreate(
            ['login_name' => 'senior'],
            [
                'username'           => 'Senior',
                'password'           => Hash::make('password'),
                'encrypted_password' => Crypt::encryptString('password'),
                'type'               => 'senior',
                'status'             => true,
                'remark'             => null,
            ]
        );
        $seniorRole = Role::where('name', 'senior')->first() ?? Role::where('name', 'Editor')->first();
        if ($seniorRole) {
            $senior->assignRole($seniorRole);
        }

        // 4. Junior User (View only)
        $junior = User::updateOrCreate(
            ['login_name' => 'junior'],
            [
                'username'           => 'Junior',
                'password'           => Hash::make('password'),
                'encrypted_password' => Crypt::encryptString('password'),
                'type'               => 'junior',
                'status'             => true,
                'remark'             => null,
            ]
        );
        $juniorRole = Role::where('name', 'junior')->first() ?? Role::where('name', 'Viewer')->first();
        if ($juniorRole) {
            $junior->assignRole($juniorRole);
        }

        // 5. Banned User (Status = false: tests "User is ban" alert)
        $banned = User::updateOrCreate(
            ['login_name' => 'banned'],
            [
                'username'           => 'Banned User',
                'password'           => Hash::make('password'),
                'encrypted_password' => Crypt::encryptString('password'),
                'type'               => 'junior',
                'status'             => false,
                'remark'             => null,
            ]
        );
        if ($juniorRole) {
            $banned->assignRole($juniorRole);
        }
    }
}
