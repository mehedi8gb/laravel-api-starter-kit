<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        UserFactory::$password = '123456';

        // Create Super Admin
//        $superAdmin = User::factory()->create([
//            'email' => 'admin@demo.com',
//            'phone' => '123456'
//        ]);
//        $superAdmin->assignRole('admin');
//
//        UserFactory::times(50)->create();
//
//        $users = User::whereNotIn('email', 'admin@demo.com')->get();
//        $users->each(function ($user) {
//            $user->assignRole('customer');
//        });

//        // Create Customer
//        $superAgent = User::factory()->create([
//            'email' => 'customer@demo.com',
//        ]);
//        $superAgent->assignRole('customer');
//
//        // Create Staff
//        User::factory()
//            ->count(5)
//            ->create()
//            ->each(function ($user) {
//                $user->assignRole('staff');
//            });
//
    }
}
