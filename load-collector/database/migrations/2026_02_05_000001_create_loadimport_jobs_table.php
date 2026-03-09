<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the loadimport_jobs table.
     *
     * This table is the single source of truth for the job list returned by:
     * GET /api/importjobs/list
     */
    public function up(): void
    {
        Schema::create('loadimport_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('jobname');
            $table->json('signature')->nullable();
            $table->timestamps();

            $table->index('jobname', 'idx_loadimport_jobs_jobname');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loadimport_jobs');
    }
};