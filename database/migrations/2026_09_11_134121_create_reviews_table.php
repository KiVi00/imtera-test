<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('author')->nullable();
            $table->timestamp('date')->nullable();
            $table->text('text')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
