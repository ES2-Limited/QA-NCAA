<?php

namespace App\Contracts;

interface AiService
{
    /**
     * Generate aviation QA checklist questions based on requirement text, scope, and requested quantity.
     *
     * @return array<int, array{
     *     question: string,
     *     type: string,
     *     options: array<string>|null,
     *     requirement_ref: string|null,
     *     guidance: string|null,
     *     source?: string
     * }>
     */
    public function generateChecklist(string $requirementText, string $scope, int $count = 8): array;

    /**
     * Summarise audit findings and context for leadership.
     */
    public function summariseAudit(string $auditContext): string;

    /**
     * Generate an executive QA & internal audit management report.
     */
    public function generateReport(string $context): string;
}
