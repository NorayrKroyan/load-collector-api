<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FakeJobsSeeder extends Seeder
{
    public function run(): void
    {
        $jobs = [
            'Renegade-Formentera-Pe...',
            '(OLYMPUS) Petro Hunt - ...',
        ];

        foreach ($jobs as $jobname) {
            DB::table('fake_jobs')->updateOrInsert(
                ['jobname' => $jobname],
                [
                    'signature' => json_encode((object)[]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
