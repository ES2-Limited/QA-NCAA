<?php

namespace App\Services;

use App\Contracts\AiService as AiServiceContract;

class FakeAiService implements AiServiceContract, AiServiceInterface
{
    /**
     * Generate realistic aviation QA checklist questions based on requirement text, scope, and requested quantity.
     */
    public function generateChecklist(string $requirementText, string $scope, int $count = 8): array
    {
        $pool = [
            [
                'question' => 'Are all mandatory Technical Logbook entries and deferred defect sheets signed by authorized type-rated engineers?',
                'type' => 'radio',
                'options' => ['Compliant', 'Non-compliant', 'N/A'],
                'requirement_ref' => 'Nig.CARs Part 5.4.1 & ICAO Annex 6',
                'guidance' => 'Inspect the previous 30 days of sector sheets and check cross-references to the Minimum Equipment List (MEL).',
                'source' => 'ai',
            ],
            [
                'question' => 'Verify that precision torque wrenches and pitot-static test equipment possess valid ISO 17025 calibration certificates.',
                'type' => 'radio',
                'options' => ['Compliant', 'Non-compliant', 'N/A'],
                'requirement_ref' => 'Nig.CARs Part 6.5.2 & ICAO Doc 9760',
                'guidance' => 'Sample 5 calibrated tools in the avionics and sheet metal workshops.',
                'source' => 'ai',
            ],
            [
                'question' => 'Are bonded chemical and sealant stores maintained within manufacturer temperature and shelf-life limits?',
                'type' => 'radio',
                'options' => ['Compliant', 'Non-compliant', 'N/A'],
                'requirement_ref' => 'Nig.CARs Part 6.3.4 & ICAO Annex 8',
                'guidance' => 'Check refrigerator temperature loggers and inspect expiry labels on structural adhesives.',
                'source' => 'ai',
            ],
            [
                'question' => 'Confirm all Ground Support Equipment (GSE) active on apron possess valid NCAA mechanical inspection safety passes.',
                'type' => 'radio',
                'options' => ['Compliant', 'Non-compliant', 'N/A'],
                'requirement_ref' => 'Nig.CARs Part 8.4.1.2 & ICAO Doc 10121',
                'guidance' => 'Conduct physical inspection of ramp tugs, baggage loaders, and fuel bowsers.',
                'source' => 'ai',
            ],
            [
                'question' => 'Document any FOD mitigation discrepancies or unsealed ramp rubbish collection bins observed during the audit walkthrough.',
                'type' => 'text',
                'options' => [],
                'requirement_ref' => 'Nig.CARs Part 12 & ICAO Doc 9137 Part 2',
                'guidance' => 'Record specific aircraft bay numbers and proximity to active jet taxiways.',
                'source' => 'ai',
            ],
            [
                'question' => 'Select all mandatory ramp PPE verified in use by line engineers and ground handling handlers during turnaround.',
                'type' => 'checkbox',
                'options' => ['High-visibility vest', 'Ear protection (muffs/plugs)', 'Steel-toe safety boots', 'Protective eyewear'],
                'requirement_ref' => 'Nig.CARs Part 8.5.3',
                'guidance' => '100% compliance required for all ground handlers in active engine ingestion hazard zones.',
                'source' => 'ai',
            ],
            [
                'question' => 'Has aviation fuel hydrant bonding continuity resistance been verified at under 0.5 ohms prior to delivery into aircraft tanks?',
                'type' => 'radio',
                'options' => ['Compliant', 'Non-compliant', 'N/A'],
                'requirement_ref' => 'Nig.CARs Part 8.8.2.1 & NFPA 407',
                'guidance' => 'Inspect bonding reel cables and calibration certification stamp on bowser meters.',
                'source' => 'ai',
            ],
            [
                'question' => 'Are aircraft parts and rotable components accompanied by FAA Form 8130-3, EASA Form 1, or NCAA Form One release certificates?',
                'type' => 'radio',
                'options' => ['Compliant', 'Non-compliant', 'N/A'],
                'requirement_ref' => 'Nig.CARs Part 5.6.1',
                'guidance' => 'Perform physical bin check in quarantine and serviceable bonded stores.',
                'source' => 'ai',
            ],
            [
                'question' => 'State the root cause analysis and immediate containment actions for any open recurring safety action notices.',
                'type' => 'text',
                'options' => [],
                'requirement_ref' => 'ICAO Annex 19 Safety Management Systems (SMS)',
                'guidance' => 'Evaluate whether closed corrective actions prevented hazard recurrence within the past 90 days.',
                'source' => 'ai',
            ],
            [
                'question' => 'Confirm flight crew fatigue tracking records and rest period logs comply with NCAA statutory duty time limitations.',
                'type' => 'radio',
                'options' => ['Compliant', 'Non-compliant', 'N/A'],
                'requirement_ref' => 'Nig.CARs Part 8.10.1 & ICAO Annex 6',
                'guidance' => 'Sample duty roster records for line pilots operating multi-sector domestic flight schedules.',
                'source' => 'ai',
            ],
        ];

        // Return up to count questions
        $results = array_slice($pool, 0, min($count, count($pool)));

        // If requested count > available in pool, duplicate with variation
        while (count($results) < $count) {
            $idx = count($results) + 1;
            $results[] = [
                'question' => "Additional compliance verification #{$idx}: Verify documentation conformity against NCAA statutory standards for {$scope}.",
                'type' => 'radio',
                'options' => ['Compliant', 'Non-compliant', 'N/A'],
                'requirement_ref' => 'Nig.CARs Part 8.4',
                'guidance' => 'Assess operational manual adherence and staff licensing records.',
                'source' => 'ai',
            ];
        }

        return $results;
    }

    /**
     * Canned 3-paragraph summary of audit findings.
     */
    public function summariseAudit(string $auditContext): string
    {
        return "The NCAA Directorate of Special Duties completed a quality assurance audit assessing operational conformance against Nig.CARs and approved operational procedures. The engagement verified personnel qualifications, records continuity, equipment calibrations, and facility safety controls across the designated inspection areas.\n\n"
            ."During the audit cycle, primary non-compliances were identified in maintenance tracking records, adherence to preventive inspection intervals, and documentation of ground safety checks. Corrective action requests have been issued for all findings to prevent recurring procedural non-conformances.\n\n"
            .'It is recommended that the audited directorate institute mandatory refresher briefings, enforce daily supervisory sign-offs, and submit formal Corrective Action Plans (CAP) within 14 calendar days for follow-up verification by NCAA inspectors.';
    }

    public function analyzeFinding(string $title, string $condition, ?string $criteria = null): array
    {
        $lowered = strtolower($title.' '.$condition);
        $severity = 'major';
        if (str_contains($lowered, 'danger') || str_contains($lowered, 'grounding') || str_contains($lowered, 'fuel') || str_contains($lowered, 'security breach') || str_contains($lowered, 'unauthorized')) {
            $severity = 'critical';
        } elseif (str_contains($lowered, 'documentation') || str_contains($lowered, 'typo') || str_contains($lowered, 'filing') || str_contains($lowered, 'sign-off delay')) {
            $severity = 'minor';
        }

        return [
            'severity_recommendation' => $severity,
            'risk_level' => $severity === 'critical' ? 'High Risk to Aviation Safety' : ($severity === 'major' ? 'Medium Operational Risk' : 'Low Administrative Non-Compliance'),
            'root_cause_analysis' => 'Potential systemic breakdown in internal quality oversight procedures, coupled with insufficient recurrent training on Nig.CARs and ICAO standard operational protocols.',
            'safety_impact' => 'Non-compliance undermines safety margins specified under NCAA statutory directives and elevates regulatory sanction exposure during ICAO USOAP surveillance.',
            'recommended_actions' => [
                'Issue an immediate Mandatory Action Directive (MAD) to the responsible operational unit.',
                'Enforce strict chain-of-custody documentation and secondary verification signatures.',
                'Conduct refresher compliance briefing for duty staff within 14 calendar days.',
                'Schedule a follow-up 30-day surveillance review to verify corrective measure effectiveness.',
            ],
            'regulatory_citations' => $criteria ?: 'Nigeria Civil Aviation Regulations (Nig.CARs) Part 8 & ICAO Annex 6 Safety Management Systems (SMS)',
            'ai_generated_note' => '[Offline Demo Engine] Assessment generated via NCAA Quality & Compliance intelligence model.',
        ];
    }

    public function generateExecutiveSummary(string $auditTitle, string $directorate, int $complianceScore, array $findingsSummary): string
    {
        $criticalCount = $findingsSummary['critical'] ?? 0;
        $majorCount = $findingsSummary['major'] ?? 0;
        $minorCount = $findingsSummary['minor'] ?? 0;
        $totalFindings = $criticalCount + $majorCount + $minorCount;

        return "### Executive Audit Summary (NCAA Directorate of Special Duties)
**Engagement:** {$auditTitle}
**Audited Directorate/Station:** {$directorate}
**Overall Quality Compliance Index:** {$complianceScore}%

#### 1. Strategic Compliance Assessment
The Quality Assurance & Internal Audit team of the Directorate of Special Duties (DSD) has concluded the internal audit engagement in accordance with the NCAA Quality Management Manual and Nig.CARs statutory audit provisions. The directorate demonstrated an overall compliance posture of **{$complianceScore}%**.

#### 2. Summary of Findings
During the surveillance cycle, a total of **{$totalFindings} non-conformances** were documented:
- **Critical Safety Findings:** {$criticalCount}
- **Major Operational Findings:** {$majorCount}
- **Minor Administrative Discrepancies:** {$minorCount}

#### 3. Risk Horizon & Auditor Conclusion
".($criticalCount > 0
    ? "⚠️ **Elevated Risk Alert:** Immediate executive intervention is mandated due to the presence of {$criticalCount} Critical findings. Corrective Action Plans (CAP) must be finalized and submitted within 7 calendar days."
    : '✅ **Managed Risk Level:** No immediate grounding or catastrophic safety vulnerabilities were uncovered. Corrective actions should be instituted within the standard 30-day resolution timeline.').'

*Generated by NCAA DSD Internal Audit Intelligence Engine (Offline / Standalone Mode).*';
    }
}
