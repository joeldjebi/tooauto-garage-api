<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professionnels', function (Blueprint $table) {
            $table->text('fcm_token')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('professionnels', function (Blueprint $table) {
            $table->dropColumn('fcm_token');
        });
    }
};
