<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Default settings dictionary representing today's hardcoded business values.
     */
    public static function defaults(): array
    {
        return [
            // General & Branding (Group 1)
            'general.system_name' => [
                'group' => 'general',
                'value' => 'Nigeria Civil Aviation Authority — DSD QA/IA System',
                'type' => 'string',
            ],
            'general.organisation_name' => [
                'group' => 'general',
                'value' => 'Nigeria Civil Aviation Authority',
                'type' => 'string',
            ],
            'general.logo_url' => [
                'group' => 'general',
                'value' => '/images/ncaa-logo.png',
                'type' => 'string',
            ],
            'general.primary_color' => [
                'group' => 'general',
                'value' => '#2a1770',
                'type' => 'string',
            ],
            'general.accent_color' => [
                'group' => 'general',
                'value' => '#008751',
                'type' => 'string',
            ],
            'general.support_email' => [
                'group' => 'general',
                'value' => 'qa.support@ncaa.gov.ng',
                'type' => 'string',
            ],
            'general.timezone' => [
                'group' => 'general',
                'value' => 'Africa/Lagos',
                'type' => 'string',
            ],
            'general.date_format' => [
                'group' => 'general',
                'value' => 'M d, Y',
                'type' => 'string',
            ],

            // Audit Rules (Group 2)
            'audit.finding_levels' => [
                'group' => 'audit',
                'value' => [
                    [
                        'level' => 1,
                        'name' => 'Level 1 — Critical / Safety Issue',
                        'cap_submission_days' => 3,
                        'closure_days' => 14,
                    ],
                    [
                        'level' => 2,
                        'name' => 'Level 2 — Major Non-Conformance',
                        'cap_submission_days' => 14,
                        'closure_days' => 30,
                    ],
                    [
                        'level' => 3,
                        'name' => 'Level 3 — Minor Observation',
                        'cap_submission_days' => 30,
                        'closure_days' => 90,
                    ],
                ],
                'type' => 'json',
            ],
            'audit.routine_alert_days' => [
                'group' => 'audit',
                'value' => [14, 7],
                'type' => 'json',
            ],
            'audit.allowed_frequencies' => [
                'group' => 'audit',
                'value' => ['weekly', 'monthly', 'quarterly', 'bi-annual', 'annual'],
                'type' => 'json',
            ],
            'audit.default_answer_options' => [
                'group' => 'audit',
                'value' => ['Compliant', 'Non-compliant', 'N/A'],
                'type' => 'json',
            ],
            'audit.non_compliant_answers' => [
                'group' => 'audit',
                'value' => ['Non-compliant', 'non_compliant'],
                'type' => 'json',
            ],
            'audit.reference_format.ncr' => [
                'group' => 'audit',
                'value' => 'NCR-{YYYY}-{####}',
                'type' => 'string',
            ],
            'audit.reference_format.certificate' => [
                'group' => 'audit',
                'value' => 'DSD-QA-{YYYY}-{####}',
                'type' => 'string',
            ],
            'audit.reference_format.audit' => [
                'group' => 'audit',
                'value' => 'AUD-{YYYY}-{CODE}-{###}',
                'type' => 'string',
            ],
            'audit.uploads.evidence' => [
                'group' => 'audit',
                'value' => [
                    'allowed_types' => 'jpg,jpeg,png,webp,pdf,docx,doc',
                    'max_size_kb' => 10240,
                ],
                'type' => 'json',
            ],
            'audit.uploads.documents' => [
                'group' => 'audit',
                'value' => [
                    'allowed_types' => 'pdf,docx',
                    'max_size_kb' => 20480,
                ],
                'type' => 'json',
            ],

            // Notifications per event (Group: notifications)
            'notifications.events.audit_assigned' => [
                'group' => 'notifications',
                'value' => [
                    'email' => true,
                    'in_app' => true,
                    'subject' => 'NCAA QA Audit Assignment: {audit_title}',
                    'body' => "You have been assigned to audit {audit_title} ({audit_ref}) scheduled for {due_date}.\nPlease review the regulatory checklists and scope before on-site inspection.",
                ],
                'type' => 'json',
            ],
            'notifications.events.routine_due' => [
                'group' => 'notifications',
                'value' => [
                    'email' => true,
                    'in_app' => true,
                    'subject' => 'Quality Routine Audit Due in {days_remaining} Days: {process_name}',
                    'body' => "Recurring quality audit procedure {process_name} is due on {due_date} ({days_remaining} days remaining).\nPlease schedule the required field inspection engagement.",
                ],
                'type' => 'json',
            ],
            'notifications.events.audit_submitted' => [
                'group' => 'notifications',
                'value' => [
                    'email' => true,
                    'in_app' => true,
                    'subject' => 'Audit Completed & Submitted for Review: {audit_title}',
                    'body' => "Audit {audit_title} ({audit_ref}) was completed in the field by {submitted_by} and is now awaiting quality review.\nQuestions evaluated: {questions_count}.",
                ],
                'type' => 'json',
            ],
            'notifications.events.ncr_raised' => [
                'group' => 'notifications',
                'value' => [
                    'email' => true,
                    'in_app' => true,
                    'subject' => 'Action Required: Level {level} NCR Raised - {ncr_ref}',
                    'body' => "A Level {level} Non-Conformance Report ({ncr_ref}) was issued for {audit_title}.\nRequirement ref: {requirement_ref}.\nCAP Submission Due Date: {due_date}.",
                ],
                'type' => 'json',
            ],
            'notifications.events.cap_submitted' => [
                'group' => 'notifications',
                'value' => [
                    'email' => true,
                    'in_app' => true,
                    'subject' => 'Corrective Action Plan Submitted for NCR {ncr_ref}',
                    'body' => "A Corrective Action Plan for NCR {ncr_ref} was submitted by {submitted_by} on {submission_date}.\nPlease review the root cause analysis and proposed containment measures.",
                ],
                'type' => 'json',
            ],
            'notifications.events.cap_reviewed' => [
                'group' => 'notifications',
                'value' => [
                    'email' => true,
                    'in_app' => true,
                    'subject' => 'CAP Decision: NCR {ncr_ref} Plan {status}',
                    'body' => "Your Corrective Action Plan for NCR {ncr_ref} was {status} by the quality reviewer on {review_date}.\nReviewer notes: {comments}.",
                ],
                'type' => 'json',
            ],
            'notifications.events.approval_needed' => [
                'group' => 'notifications',
                'value' => [
                    'email' => true,
                    'in_app' => true,
                    'subject' => 'Approval Required: {step_name} for Audit {audit_title}',
                    'body' => "Audit {audit_title} ({audit_ref}) has reached workflow stage \"{step_name}\" and is awaiting your review and authorization.\nDue Date: {due_date}.",
                ],
                'type' => 'json',
            ],
            'notifications.events.certificate_issued' => [
                'group' => 'notifications',
                'value' => [
                    'email' => true,
                    'in_app' => true,
                    'subject' => 'Official Quality Audit Certificate Issued: {certificate_number}',
                    'body' => "Official Quality Audit Certificate {certificate_number} has been issued for audit {audit_title} ({audit_ref}) on {issue_date}.\nDownload or verify via the NCAA QA portal.",
                ],
                'type' => 'json',
            ],

            // AI Settings (Group: ai)
            'ai.enabled' => [
                'group' => 'ai',
                'value' => true,
                'type' => 'boolean',
            ],
            'ai.model' => [
                'group' => 'ai',
                'value' => 'gemini-2.5-flash',
                'type' => 'string',
            ],
            'ai.api_key' => [
                'group' => 'ai',
                'value' => '', // Stored encrypted
                'type' => 'string',
            ],
            'ai.prompts.checklist_generation' => [
                'group' => 'ai',
                'value' => "You are an aviation quality assurance auditor for the Nigeria Civil Aviation Authority.\nFrom the requirement document below, write {count} audit checklist questions for this scope: {scope}. Each question must check ONE verifiable requirement.\nReturn ONLY a JSON array. Each item:\n{\"question\": string, \"type\": \"checkbox\"|\"radio\"|\"text\", \"options\": string[] (radio: [\"Compliant\",\"Non-compliant\",\"N/A\"]; text: []), \"requirement_ref\": string, \"guidance\": string}\nRequirement document:\n\"\"\"{requirement_text}\"\"\"",
                'type' => 'string',
            ],
            'ai.prompts.audit_summary' => [
                'group' => 'ai',
                'value' => "You are an executive aviation safety analyst for the Nigeria Civil Aviation Authority.\nSummarise the audit findings below in 3 concise bullet points for the Director of Special Duties, highlighting critical risks, overall compliance status, and required containment actions.\nAudit details:\n\"\"\"{audit_context}\"\"\"",
                'type' => 'string',
            ],
            'ai.prompts.management_report' => [
                'group' => 'ai',
                'value' => "You are a senior aviation quality assurance director. Draft a formal executive audit report for NCAA Director of Special Duties and Department Heads.\nInclude Executive Summary, Directorate Conformance Overview, Top Non-Conformance Areas, and Recommendations.\nAudit data:\n\"\"\"{context}\"\"\"",
                'type' => 'string',
            ],

            // Templates (Group: templates)
            'templates.certificate.title' => [
                'group' => 'templates',
                'value' => 'Internal Quality Audit Certificate',
                'type' => 'string',
            ],
            'templates.certificate.body' => [
                'group' => 'templates',
                'value' => 'This is to certify that an internal statutory quality assurance inspection and procedural audit was conducted in full accordance with the NCAA Quality Management Manual, statutory provisions of the Nigeria Civil Aviation Regulations (Nig.CARs), and ICAO Annex standards.',
                'type' => 'string',
            ],
            'templates.certificate.signature_left_label' => [
                'group' => 'templates',
                'value' => 'Head of Internal Audit',
                'type' => 'string',
            ],
            'templates.certificate.signature_right_label' => [
                'group' => 'templates',
                'value' => 'Director of Special Duties, NCAA',
                'type' => 'string',
            ],
            'templates.report.header_title' => [
                'group' => 'templates',
                'value' => 'Nigeria Civil Aviation Authority — DSD QA/IA System • Executive Report',
                'type' => 'string',
            ],
            'templates.report.header_subtitle' => [
                'group' => 'templates',
                'value' => 'Directorate of Special Duties | Quality Assurance & Internal Audit Division',
                'type' => 'string',
            ],
            'templates.report.footer_text' => [
                'group' => 'templates',
                'value' => 'Confidential • Nigeria Civil Aviation Authority • For internal executive oversight only.',
                'type' => 'string',
            ],
        ];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::defaults() as $key => $data) {
            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'group' => $data['group'],
                    'value' => $data['value'],
                    'type' => $data['type'],
                ]
            );
        }

        app(SettingService::class)->clearCache();
    }
}
