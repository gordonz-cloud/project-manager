<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_reply_edges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_request_reply_id')->constrained('request_replies')->cascadeOnDelete();
            $table->foreignId('to_request_reply_id')->constrained('request_replies')->cascadeOnDelete();
            $table->string('kind');
            $table->string('condition')->nullable();
            $table->timestamps();

            $table->unique(['from_request_reply_id', 'to_request_reply_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_reply_edges');
    }
};
