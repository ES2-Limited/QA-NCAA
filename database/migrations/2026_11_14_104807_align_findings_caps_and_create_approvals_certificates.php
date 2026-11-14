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
        // 1. Align audits table: add ai_summary
        Schema::table('audits', function (Blueprint $table) {
            if (! Schema::hasColumn('audits', 'ai_summary')) {
                $table->text('ai_summary')->nullable()->after('ai_executive_summary');
            }
        });

        // 2. Align findings (NCR) table
        Schema::table('findings', function (Blueprint $table) {
            $table->string('finding_number')->nullable()->change();
            $table->string('title')->nullable()->change();
            $table->text('condition')->nullable()->change();
            $table->text('criteria')->nullable()->change();

            if (! Schema::hasColumn('findings', 'audit_answer_id')) {
                $table->foreignId('audit_answer_id')->nullable()->after('audit_id')->constrained('audit_answers')->nullOnDelete();
            }
            if (! Schema::hasColumn('findings', 'reference')) {
                $table->string('reference')->nullable()->after('audit_answer_id');
            }
            if (! Schema::hasColumn('findings', 'level')) {
                $table->unsignedTinyInteger('level')->default(2)->after('reference'); // 1 or 2
            }
            if (! Schema::hasColumn('findings', 'description')) {
                $table->text('description')->nullable()->after('level');
            }
            if (! Schema::hasColumn('findings', 'requirement_ref')) {
                $table->string('requirement_ref')->nullable()->after('description');
            }
            if (! Schema::hasColumn('findings', 'assigned_unit_id')) {
                $table->foreignId('assigned_unit_id')->nullable()->after('requirement_ref')->constrained('units')->nullOnDelete();
            }
            if (! Schema::hasColumn('findings', 'raised_by')) {
                $table->foreignId('raised_by')->nullable()->after('assigned_unit_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('findings', 'cap_due_at')) {
                $table->timestamp('cap_due_at')->nullable()->after('raised_by');
            }
        });

        // 3. Align corrective_action_plans (CAP) table
        Schema::table('corrective_action_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('corrective_action_plans', 'ncr_id')) {
                $table->foreignId('ncr_id')->nullable()->after('id')->constrained('findings')->cascadeOnDelete();
            }
            if (! Schema::hasColumn('corrective_action_plans', 'description')) {
                $table->text('description')->nullable()->after('ncr_id');
            }
            if (! Schema::hasColumn('corrective_action_plans', 'root_cause')) {
                $table->text('root_cause')->nullable()->after('description');
            }
            if (! Schema::hasColumn('corrective_action_plans', 'target_date')) {
                $table->date('target_date')->nullable()->after('root_cause');
            }
            if (! Schema::hasColumn('corrective_action_plans', 'submitted_by')) {
                $table->foreignId('submitted_by')->nullable()->after('target_date')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('corrective_action_plans', 'reviewer_comment')) {
                $table->text('reviewer_comment')->nullable()->after('status');
            }
            if (! Schema::hasColumn('corrective_action_plans', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('reviewer_comment')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('corrective_action_plans', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
        });

        // 4. Audit approvals trail
        if (! Schema::hasTable('audit_approvals')) {
            Schema::create('audit_approvals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('audit_id')->constrained('audits')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('role');
                $table->string('action'); // approved, returned, etc.
                $table->text('comment')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // 5. Certificates
        if (! Schema::hasTable('certificates')) {
            Schema::create('certificates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('audit_id')->constrained('audits')->cascadeOnDelete();
                $table->string('number')->unique(); // DSD-QA-2026-0001
                $table->timestamp('issued_at')->nullable();
                $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('file_path')->nullable();
                $table->string('verify_token')->unique();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('audit_approvals');

        Schema::table('corrective_action_plans', function (Blueprint $table) {
            $table->dropColumn(['ncr_id', 'description', 'root_cause', 'target_date', 'submitted_by', 'reviewer_comment', 'reviewed_by', 'reviewed_at']);
        });

        Schema::table('findings', function (Blueprint $table) {
            $table->dropColumn(['audit_answer_id', 'reference', 'level', 'description', 'requirement_ref', 'assigned_unit_id', 'raised_by', 'cap_due_at']);
        });

        Schema::table('audits', function (Blueprint $table) {
            $table->dropColumn('ai_summary');
        });
    }
};
