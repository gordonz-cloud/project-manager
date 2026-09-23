<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('use_case_id')->constrained()->cascadeOnDelete();
            $table->string('trigger');
            $table->string('method')->nullable();
            $table->string('entry');
            $table->string('title');
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['use_case_id', 'method', 'entry']);
        });

        Schema::create('feature_request_reply', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->foreignId('request_reply_id')->constrained()->cascadeOnDelete();
            $table->unique(['feature_id', 'request_reply_id']);
        });

        Schema::table('implementation_nodes', function (Blueprint $table) {
            $table->foreignId('request_reply_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('implementation_nodes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('request_reply_id');
        });
        Schema::dropIfExists('feature_request_reply');
        Schema::dropIfExists('request_replies');
    }
};
