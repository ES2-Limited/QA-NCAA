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
        // Add role & department fields to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('auditor')->after('email'); // admin, lead_auditor, auditor, directorate_rep
            $table->string('department')->nullable()->after('role');
            $table->string('phone')->nullable()->after('department');
            $table->string('staff_id')->nullable()->after('phone');
        });

        // Directorates / Departments
        Schema::create('directorates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // e.g. DSD, DAWS, DOLG, DATR, DEA, DFS
            $table->string('head_of_directorate')->nullable();
            $table->string('email')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Checklist Templates (ICAO / NCAA standards)
        Schema::create('checklists', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('code')->unique();
            $table->string('category')->default('flight_safety'); // safety, security, ground_ops, airworthiness, records, aerodrome
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Checklist Items
        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained()->cascadeOnDelete();
            $table->string('item_code'); // e.g. DSD-OPS-01
            $table->text('requirement');
            $table->text('guidance')->nullable();
            $table->string('regulatory_reference')->nullable(); // e.g. Nig.CARs Part 8.4, ICAO Annex 6
            $table->integer('order_index')->default(0);
            $table->timestamps();
        });

        // Audits (QA / IA Engagements)
        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique(); // e.g. AUD-2026-DSD-001
            $table->string('title');
            $table->string('type')->default('internal_audit'); // internal_audit, surveillance, compliance_review, special_inspection
            $table->foreignId('directorate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checklist_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_auditor_id')->constrained('users')->cascadeOnDelete();
            $table->string('location')->default('NCAA HQ Abuja'); // e.g. Abuja (ABV), Lagos (LOS), Kano (KAN), Port Harcourt (PHC)
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status')->default('scheduled'); // scheduled, in_progress, review, completed, closed
            $table->text('scope')->nullable();
            $table->text('executive_summary')->nullable();
            $table->text('ai_executive_summary')->nullable();
            $table->timestamps();
        });

        // Audit Responses (field assessment for each item)
        Schema::create('audit_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('compliant'); // compliant, non_compliant, observation, not_applicable
            $table->text('notes')->nullable();
            $table->text('evidence_path')->nullable(); // Photo or attachment path
            $table->foreignId('auditor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Findings (Non-Conformances & Deficiencies)
        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audit_response_id')->nullable()->constrained()->nullOnDelete();
            $table->string('finding_number')->unique(); // e.g. FND-2026-001
            $table->string('title');
            $table->string('severity')->default('major'); // critical, major, minor, observation
            $table->text('condition'); // What was found
            $table->text('criteria'); // Regulatory standard violated (Nig.CARs, ICAO Doc 9859)
            $table->text('cause')->nullable(); // Root cause
            $table->text('effect')->nullable(); // Risk impact
            $table->text('recommendation')->nullable();
            $table->string('status')->default('open'); // open, cap_submitted, cap_approved, verified_closed
            $table->date('due_date')->nullable();
            $table->text('ai_analysis')->nullable(); // AI risk severity & root-cause guidance
            $table->timestamps();
        });

        // Corrective Action Plans (CAP)
        Schema::create('corrective_action_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finding_id')->constrained()->cascadeOnDelete();
            $table->text('proposed_action');
            $table->text('root_cause_analysis');
            $table->string('responsible_person');
            $table->date('target_completion_date');
            $table->string('status')->default('pending_approval'); // pending_approval, approved, rejected, implemented, verified_closed
            $table->text('verification_notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('corrective_action_plans');
        Schema::dropIfExists('findings');
        Schema::dropIfExists('audit_responses');
        Schema::dropIfExists('audits');
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('checklists');
        Schema::dropIfExists('directorates');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'department', 'phone', 'staff_id']);
        });
    }
};
