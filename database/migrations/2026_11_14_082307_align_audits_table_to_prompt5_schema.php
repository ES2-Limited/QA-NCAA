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
        Schema::table('audits', function (Blueprint $table) {
            if (! Schema::hasColumn('audits', 'process_id')) {
                $table->foreignId('process_id')->nullable()->after('id')->constrained('processes')->cascadeOnDelete();
            }

            if (! Schema::hasColumn('audits', 'scheduled_for')) {
                $table->date('scheduled_for')->nullable()->after('title');
            }

            if (! Schema::hasColumn('audits', 'reviewer_id')) {
                $table->foreignId('reviewer_id')->nullable()->after('lead_auditor_id')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('audits', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('reviewer_id')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('audits', 'questions_snapshot')) {
                $table->json('questions_snapshot')->nullable()->after('status');
            }
        });

        // Pivot table for assigned field auditors
        if (! Schema::hasTable('audit_user')) {
            Schema::create('audit_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('audit_id')->constrained('audits')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['audit_id', 'user_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_user');

        Schema::table('audits', function (Blueprint $table) {
            $table->dropForeign(['process_id']);
            $table->dropForeign(['reviewer_id']);
            $table->dropForeign(['created_by']);
            $table->dropColumn(['process_id', 'scheduled_for', 'reviewer_id', 'created_by', 'questions_snapshot']);
        });
    }
};
