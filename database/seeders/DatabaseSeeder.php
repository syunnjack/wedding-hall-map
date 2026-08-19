<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 掲載する会場は、出典をたどれるものだけにする。
        $this->call(OsmVenueSeeder::class);
    }
}
