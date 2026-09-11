<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->string('yandex_id')->nullable()->unique();
            $table->string('title')->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->integer('ratings_count')->default(0);
            $table->integer('reviews_count')->default(0);
            $table->string('parse_status')->default('pending');
            $table->text('parse_error')->nullable();
            $table->timestamp('last_parsed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
