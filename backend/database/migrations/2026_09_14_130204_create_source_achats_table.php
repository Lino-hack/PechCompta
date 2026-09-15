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
        Schema::create('source_achats', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['pirogue', 'detaillant']);
            $table->foreignId('pecheur_id')->nullable()->constrained('pecheurs')->nullOnDelete();
            $table->foreignId('detaillant_id')->nullable()->constrained('detaillants')->nullOnDelete();
            $table->date('date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('source_achats');
    }
};
