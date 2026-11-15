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
        Schema::create('lookups', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // process_category, document_type, audit_status, ncr_status
            $table->string('key')->nullable(); // system identifier e.g. scheduled, open
            $table->string('name');
            $table->string('colour')->default('zinc'); // Tailwind / Flux color: emerald, zinc, amber, blue, etc.
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_active', 'sort_order']);
        });

        Schema::table('processes', function (Blueprint $table) {
            $table->string('category')->nullable()->after('description');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->string('type')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('processes', function (Blueprint $table) {
            $table->dropColumn('category');
        });

        Schema::dropIfExists('lookups');
    }
};
