<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn(['date', 'start_time', 'end_time']);
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->date('date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
        });
    }
};
