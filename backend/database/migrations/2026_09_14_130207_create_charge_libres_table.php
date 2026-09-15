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
        Schema::create('charge_libres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charge_journaliere_id')->constrained('charge_journalieres')->cascadeOnDelete();
            $table->string('libelle');
            $table->decimal('montant', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('charge_libres');
    }
};
