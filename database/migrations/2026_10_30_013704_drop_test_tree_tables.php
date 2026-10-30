<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('requirement_test');
        Schema::dropIfExists('tests');
    }

    public function down(): void
    {
        // The test tree was retired; its backed-up data is not restored by rollback.
    }
};
