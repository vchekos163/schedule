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
        Schema::table('subject_user', function (Blueprint $table) {
            $table->integer('quantity')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('subject_user', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};
