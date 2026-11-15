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
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('order')->default(1)->index();
            $table->string('name');
            $table->string('role')->index(); // e.g. reviewer, head_of_audit, director
            $table->string('action_status')->nullable(); // e.g. reviewed, head_approved, director_approved
            $table->boolean('can_return')->default(true);
            $table->boolean('required')->default(true);
            $table->boolean('is_final')->default(false); // marks final step that issues certificate
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
    }
};
