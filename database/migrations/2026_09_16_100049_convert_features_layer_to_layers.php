<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('features', function (Blueprint $table) {
            $table->json('layers')->nullable();
        });

        DB::table('features')->whereNotNull('layer')->orderBy('id')->chunkById(500, function ($features) {
            foreach ($features as $feature) {
                DB::table('features')->where('id', $feature->id)->update([
                    'layers' => json_encode([$feature->layer]),
                ]);
            }
        });

        Schema::table('features', function (Blueprint $table) {
            $table->dropColumn('layer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('features', function (Blueprint $table) {
            $table->string('layer')->nullable();
        });

        DB::table('features')->whereNotNull('layers')->orderBy('id')->chunkById(500, function ($features) {
            foreach ($features as $feature) {
                $layers = json_decode((string) $feature->layers, true);
                DB::table('features')->where('id', $feature->id)->update([
                    'layer' => $layers[0] ?? null,
                ]);
            }
        });

        Schema::table('features', function (Blueprint $table) {
            $table->dropColumn('layers');
        });
    }
};
