<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoring_settings', function (Blueprint $table) {
            $table->id();
            $table->json('rules');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_settings');
    }
};
