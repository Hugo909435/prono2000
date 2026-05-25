<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('point_boosters');
    }

    public function down(): void
    {
        // Recréation si rollback nécessaire — voir 2026_01_18_180422_create_point_boosters_table.php
    }
};
