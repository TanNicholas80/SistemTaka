<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Branch;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Buat branch Bandung
        $bandung = Branch::firstOrCreate(
            ['customer_id' => 'CUST001'], // wajib unik
            [
                'name' => 'Bandung',
                'photo' => null,
            ]
        );

        // Buat branch Magelang
        $magelang = Branch::firstOrCreate(
            ['customer_id' => 'CUST002'],
            [
                'name' => 'Magelang',
                'photo' => null,
            ]
        );

        // Buat super_admin
        $admin = User::firstOrCreate(
            ['username' => 'DT_NIC'],
            [
                'name' => 'Super Admin',
                'role' => 'super_admin',
                'password' => Hash::make('pass@123'),
            ]
        );

        // Pastikan super_admin punya akses ke semua cabang
        $admin->branches()->syncWithoutDetaching([$bandung->id, $magelang->id]);
    }
}
