<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('loader_writers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('loader_novels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('writer_id');
            $table->string('title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loader_novels');
        Schema::dropIfExists('loader_writers');
    }
};
