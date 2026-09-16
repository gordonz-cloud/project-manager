<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Splits `location` ("path::method()", "path · method()", "path::method",
     * or bare "path") into `file` and `function`. The separator is `::` or
     * ` · `; a trailing `()` on the function is dropped. Anything that does
     * not split cleanly stays whole in `file` with a null `function`.
     */
    public function up(): void
    {
        Schema::table('flow_steps', function (Blueprint $table) {
            $table->string('file')->nullable();
            $table->string('function')->nullable();
        });

        DB::table('flow_steps')->whereNotNull('location')->orderBy('id')->chunkById(500, function ($steps) {
            foreach ($steps as $step) {
                [$file, $function] = self::splitLocation($step->location);

                DB::table('flow_steps')->where('id', $step->id)->update([
                    'file' => $file,
                    'function' => $function,
                ]);
            }
        });

        Schema::table('flow_steps', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flow_steps', function (Blueprint $table) {
            $table->string('location')->nullable();
        });

        DB::table('flow_steps')->whereNotNull('file')->orderBy('id')->chunkById(500, function ($steps) {
            foreach ($steps as $step) {
                $location = $step->function ? "{$step->file}::{$step->function}()" : $step->file;

                DB::table('flow_steps')->where('id', $step->id)->update([
                    'location' => $location,
                ]);
            }
        });

        Schema::table('flow_steps', function (Blueprint $table) {
            $table->dropColumn(['file', 'function']);
        });
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    public static function splitLocation(string $location): array
    {
        foreach (['::', ' · '] as $separator) {
            if (str_contains($location, $separator)) {
                [$file, $function] = explode($separator, $location, 2);

                return [trim($file), rtrim(trim($function), '()')];
            }
        }

        return [$location, null];
    }
};
