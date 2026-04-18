<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('loadimports', function (Blueprint $table) {
            $table->id();

            $table->string('jobname', 255)->nullable();

            $table->string('payload_path');
            $table->string('payload_original')->nullable();
            $table->unsignedBigInteger('payload_size')->nullable();

            $table->string('image_path')->nullable();
            $table->string('image_original')->nullable();
            $table->unsignedBigInteger('image_size')->nullable();

            $table->json('payload_json')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loadimports');
    }
};
