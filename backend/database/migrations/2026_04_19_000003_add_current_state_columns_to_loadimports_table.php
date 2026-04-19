<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loadimports', function (Blueprint $table) {
            if (!Schema::hasColumn('loadimports', 'is_inserted')) {
                $table->boolean('is_inserted')
                    ->default(false)
                    ->after('updated_at');
            }

            if (!Schema::hasColumn('loadimports', 'id_load')) {
                $table->unsignedInteger('id_load')
                    ->nullable()
                    ->after('is_inserted');
            }

            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = array_map('strtolower', array_keys($sm->listTableIndexes('loadimports')));

            if (!in_array('idx_loadimports_is_inserted', $indexes, true) && Schema::hasColumn('loadimports', 'is_inserted')) {
                $table->index('is_inserted', 'idx_loadimports_is_inserted');
            }

            if (!in_array('idx_loadimports_id_load', $indexes, true) && Schema::hasColumn('loadimports', 'id_load')) {
                $table->index('id_load', 'idx_loadimports_id_load');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loadimports', function (Blueprint $table) {
            if (Schema::hasColumn('loadimports', 'id_load')) {
                try {
                    $table->dropIndex('idx_loadimports_id_load');
                } catch (\Throwable $e) {
                }

                $table->dropColumn('id_load');
            }

            if (Schema::hasColumn('loadimports', 'is_inserted')) {
                try {
                    $table->dropIndex('idx_loadimports_is_inserted');
                } catch (\Throwable $e) {
                }

                $table->dropColumn('is_inserted');
            }
        });
    }
};
