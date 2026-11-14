<?php

namespace App\Services;

interface AiServiceInterface
{
    /**
     * Analyze a non-conformance finding and generate severity classification,
     * regulatory risk assessment, root cause suggestions, and corrective action advice.
     */
    public function analyzeFinding(string $title, string $condition, ?string $criteria = null): array;

    /**
     * Generate an executive audit summary based on the findings, compliance score, and scope.
     */
    public function generateExecutiveSummary(string $auditTitle, string $directorate, int $complianceScore, array $findingsSummary): string;
}
