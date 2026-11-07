<?php

declare(strict_types=1);

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
        Schema::create('column_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sheet_tab_id')->constrained()->cascadeOnDelete();
            $table->string('source_column');
            $table->string('target_table');
            $table->string('target_column');
            $table->string('column_type');
            $table->boolean('is_unique_key')->default(false);
            $table->unique(['sheet_tab_id', 'target_table', 'target_column']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('column_mappings');
    }
};
