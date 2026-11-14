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
        // 1. Audit status update + submitted_at
        Schema::table('audits', function (Blueprint $table) {
            if (! Schema::hasColumn('audits', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('status');
            }
        });

        // 2. Audit Answers (audit_answers)
        if (! Schema::hasTable('audit_answers')) {
            Schema::create('audit_answers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('audit_id')->constrained('audits')->cascadeOnDelete();
                $table->unsignedInteger('question_key'); // index in snapshot (0-based or 1-based, consistent)
                $table->json('value')->nullable(); // response content: string, array, etc.
                $table->text('comment')->nullable();
                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('lng', 10, 7)->nullable();
                $table->decimal('location_accuracy', 8, 2)->nullable();
                $table->timestamp('answered_at')->nullable();
                $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['audit_id', 'question_key']);
            });
        }

        // 3. Attachments (polymorphic)
        if (! Schema::hasTable('attachments')) {
            Schema::create('attachments', function (Blueprint $table) {
                $table->id();
                $table->morphs('attachable');
                $table->string('path');
                $table->string('original_name');
                $table->string('mime_type');
                $table->unsignedBigInteger('size');
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('audit_answers');
        Schema::table('audits', function (Blueprint $table) {
            $table->dropColumn('submitted_at');
        });
    }
};
