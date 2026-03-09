<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LoadImportJobsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('loadimport_jobs')->updateOrInsert(
            ['id' => 3],
            [
                'jobname' => 'Spectre-Crescent-SIMUL Washburn',
                'signature' => json_encode((object) []),
                'created_at' => '2026-02-08 11:25:43',
                'updated_at' => '2026-02-08 11:25:43',
            ]
        );

        DB::table('loadimport_jobs')->updateOrInsert(
            ['id' => 7],
            [
                'jobname' => '(OLYMPUS) Petro Hunt - WC West C',
                'signature' => null,
                'created_at' => null,
                'updated_at' => null,
            ]
        );

        DB::table('loadimport_jobs')->updateOrInsert(
            ['id' => 9],
            [
                'jobname' => 'Jonah Studhorse Butte',
                'signature' => null,
                'created_at' => null,
                'updated_at' => null,
            ]
        );

        DB::table('loadimport_jobs')->updateOrInsert(
            ['id' => 10],
            [
                'jobname' => 'Apache-Warwick-Kopecki',
                'signature' => null,
                'created_at' => null,
                'updated_at' => null,
            ]
        );

        DB::table('loadimport_jobs')->updateOrInsert(
            ['id' => 11],
            [
                'jobname' => 'Frac 94 - Murphy - ERB-King PSA 1H / 2H / 3H / 4H',
                'signature' => null,
                'created_at' => null,
                'updated_at' => null,
            ]
        );

        DB::statement('ALTER TABLE loadimport_jobs AUTO_INCREMENT = 12');
    }
}