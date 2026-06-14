<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_books', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();

            // Core: store raw character position, NOT page number.
            // This makes reading position font-size-agnostic.
            // Page is computed at read time based on current font size.
            $table->unsignedInteger('last_read_char_position')->default(0);

            $table->unsignedTinyInteger('font_size')->default(16);

            $table->boolean('is_active')->default(false);

            $table->timestamps();

            $table->unique(['user_id', 'book_id']);
            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'book_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_books');
    }
};
