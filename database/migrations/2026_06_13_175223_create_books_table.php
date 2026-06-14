<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('author');
            $table->string('isbn', 20)->unique()->nullable();
            $table->text('description')->nullable();
            // Store total character count to compute pages dynamically per font size
            $table->unsignedInteger('total_chars')->default(0);
            // Path to content file in storage (e.g., books/book-1.txt)
            $table->string('file_path')->nullable();
            $table->timestamps();

            $table->index('isbn');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
