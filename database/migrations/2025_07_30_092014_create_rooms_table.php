<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Название/номер
            $table->unsignedInteger('capacity')->nullable(); // Кол-во мест
            $table->string('purpose')->nullable(); // Назначение (опционально)
            $table->timestamps();
        });

        Schema::create('room_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->unique(['room_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_subject');
        Schema::dropIfExists('rooms');
    }
};
