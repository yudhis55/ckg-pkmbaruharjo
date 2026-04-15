<?php

namespace Database\Seeders;

use App\Models\Tahun;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TahunSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            ['tahun' => '2024', 'is_active' => false],
            ['tahun' => '2025', 'is_active' => false],
            ['tahun' => '2026', 'is_active' => true],
            ['tahun' => '2027', 'is_active' => false],
            ['tahun' => '2028', 'is_active' => false],
        ];

        foreach ($data as $item) {
            Tahun::create($item);
        }
    }
}
