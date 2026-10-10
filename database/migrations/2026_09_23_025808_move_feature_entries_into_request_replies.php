<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Once moved each use-case feature's free-text entry into request replies. It ran on every database that had data;
 * features and request replies have since been dropped (2026_10_10_092052), so on a fresh database it has nothing to do.
 */
return new class extends Migration
{
    public function up(): void {}
};
