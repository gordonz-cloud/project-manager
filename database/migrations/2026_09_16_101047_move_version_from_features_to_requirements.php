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
        Schema::table('requirements', function (Blueprint $table) {
            $table->string('version')->nullable();
        });

        $requirementIds = DB::table('features')
            ->where('version', '1')
            ->whereNotNull('requirement_id')
            ->distinct()
            ->pluck('requirement_id');

        DB::table('requirements')->whereIn('id', $requirementIds)->update(['version' => '1']);

        Schema::table('features', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('features', function (Blueprint $table) {
            $table->string('version')->nullable();
        });

        $requirementIds = DB::table('requirements')->where('version', '1')->pluck('id');

        DB::table('features')->whereIn('requirement_id', $requirementIds)->update(['version' => '1']);

        Schema::table('requirements', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }
};
