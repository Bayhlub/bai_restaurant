<?php

namespace Database\Seeders;

use App\Models\Table;
use Illuminate\Database\Seeder;

class TableSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 10) as $number) {
            Table::firstOrCreate(['number' => (string) $number], ['seats' => 4]);
        }
    }
}
