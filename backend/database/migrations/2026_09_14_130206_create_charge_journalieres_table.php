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
        Schema::create('charge_journalieres', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->integer('nb_bagues_glace')->default(0);
            $table->decimal('prix_bague_utilise', 10, 2)->default(0);
            $table->decimal('transport', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('charge_journalieres');
    }
};
