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

            // Font size at last save (used as default if not provided on next request)
            $table->unsignedTinyInteger('font_size')->default(16);

            // Only one book per user can be active at a time.
            // Enforced by deactivating all before activating a new one.
            $table->boolean('is_active')->default(false);

            $table->timestamps();

            // Unique: each user can add a book only once
            $table->unique(['user_id', 'book_id']);

            // Fast lookup for active book
            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'book_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_books');
    }
};
