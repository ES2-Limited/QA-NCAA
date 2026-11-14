<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Aligns legacy `checklists` -> `processes` and `checklist_items` -> `checklist_questions`.
     */
    public function up(): void
    {
        // 1. Drop index on code first before renaming/dropping
        Schema::table('checklists', function (Blueprint $table) {
            $table->dropUnique(['code']);
        });

        // 2. Rename and reshape checklists -> processes
        Schema::rename('checklists', 'processes');

        Schema::table('processes', function (Blueprint $table) {
            $table->renameColumn('title', 'name');
            $table->dropColumn(['code', 'category', 'is_active']);

            $table->foreignId('directorate_id')->nullable()->after('description')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->after('directorate_id')->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
            $table->string('type')->default('routine')->after('unit_id'); // routine, unplanned
            $table->string('frequency')->nullable()->after('type'); // weekly, monthly, quarterly, yearly
            $table->date('next_due_at')->nullable()->after('frequency');
            $table->foreignId('created_by')->nullable()->after('next_due_at')->constrained('users')->nullOnDelete();
            $table->string('status')->default('draft')->after('created_by'); // draft, active
        });

        // 3. Rename and reshape checklist_items -> checklist_questions
        Schema::rename('checklist_items', 'checklist_questions');

        Schema::table('checklist_questions', function (Blueprint $table) {
            $table->renameColumn('checklist_id', 'process_id');
            $table->renameColumn('requirement', 'question');
            $table->renameColumn('order_index', 'order');
            $table->renameColumn('regulatory_reference', 'requirement_ref');
            $table->dropColumn(['item_code']);
            $table->string('type')->default('radio'); // checkbox, radio, text
            $table->json('options')->nullable();
            $table->string('source')->default('manual'); // manual, ai
        });

        // 4. Document <-> Process many-to-many pivot
        Schema::create('document_process', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('process_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_process');

        Schema::table('checklist_questions', function (Blueprint $table) {
            $table->dropColumn(['source', 'options', 'type']);
            $table->string('item_code')->default('ITEM-01');
            $table->renameColumn('requirement_ref', 'regulatory_reference');
            $table->renameColumn('order', 'order_index');
            $table->renameColumn('question', 'requirement');
            $table->renameColumn('process_id', 'checklist_id');
        });

        Schema::rename('checklist_questions', 'checklist_items');

        Schema::table('processes', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['unit_id']);
            $table->dropForeign(['department_id']);
            $table->dropForeign(['directorate_id']);
            $table->dropColumn(['status', 'created_by', 'next_due_at', 'frequency', 'type', 'unit_id', 'department_id', 'directorate_id']);
            $table->boolean('is_active')->default(true);
            $table->string('category')->default('flight_safety');
            $table->string('code')->unique();
            $table->renameColumn('name', 'title');
        });

        Schema::rename('processes', 'checklists');
    }
};
