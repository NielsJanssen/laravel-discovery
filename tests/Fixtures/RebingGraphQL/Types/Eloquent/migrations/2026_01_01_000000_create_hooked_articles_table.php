<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hooked_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->string('status')->default('draft');
            $table->unsignedInteger('copies_sold')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hooked_articles');
    }
};
