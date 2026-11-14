<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiAiService implements AiServiceInterface
{
    protected string $apiKey;

    protected string $model;

    protected FakeAiService $fallback;

    public function __construct(string $apiKey, string $model = 'gemini-1.5-flash')
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->fallback = new FakeAiService;
    }

    public function analyzeFinding(string $title, string $condition, ?string $criteria = null): array
    {
        if (empty($this->apiKey)) {
            return $this->fallback->analyzeFinding($title, $condition, $criteria);
        }

        try {
            $prompt = <<<PROMPT
You are a senior Aviation Safety & Internal Audit Expert for the Nigeria Civil Aviation Authority (NCAA) Directorate of Special Duties (DSD).
Analyze this audit non-conformance finding:

Finding Title: {$title}
Condition (Observed Deficiency): {$condition}
Regulatory Criteria / Standard: {$criteria}

Provide an aviation quality & safety analysis in JSON format with exactly these keys:
{
  "severity_recommendation": "critical" | "major" | "minor",
  "risk_level": "short phrase describing risk level",
  "root_cause_analysis": "probable root cause",
  "safety_impact": "impact on flight safety / aerodrome ops / regulatory compliance",
  "recommended_actions": ["action 1", "action 2", "action 3"],
  "regulatory_citations": "relevant Nig.CARs or ICAO references",
  "ai_generated_note": "A concise expert remark"
}
Output pure JSON only, without markdown fences if possible.
PROMPT;

            $response = Http::timeout(10)->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'responseMimeType' => 'application/json',
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                $decoded = json_decode($text, true);
                if (is_array($decoded) && isset($decoded['severity_recommendation'])) {
                    return $decoded;
                }
            }
            Log::warning('Gemini API call failed or returned invalid JSON. Falling back to FakeAiService.');
        } catch (\Throwable $e) {
            Log::warning('Gemini AI exception: '.$e->getMessage().'. Using FakeAiService.');
        }

        return $this->fallback->analyzeFinding($title, $condition, $criteria);
    }

    public function generateExecutiveSummary(string $auditTitle, string $directorate, int $complianceScore, array $findingsSummary): string
    {
        if (empty($this->apiKey)) {
            return $this->fallback->generateExecutiveSummary($auditTitle, $directorate, $complianceScore, $findingsSummary);
        }

        try {
            $prompt = <<<PROMPT
You are the Director/Lead Quality Auditor for the Nigeria Civil Aviation Authority (NCAA) Directorate of Special Duties (DSD).
Draft a professional Executive Audit Summary for this completed audit:

Audit Engagement: {$auditTitle}
Audited Directorate/Station: {$directorate}
Compliance Score: {$complianceScore}%
Findings Breakdown: Critical: {$findingsSummary['critical']}, Major: {$findingsSummary['major']}, Minor: {$findingsSummary['minor']}

Include sections:
1. Executive Overview & Compliance Rating
2. Key Operational & Safety Risks Identified
3. NCAA Corrective Action Protocol & Recommendations
Write in formal aviation audit tone adhering to Nig.CARs and ICAO SMS standards.
PROMPT;

            $response = Http::timeout(10)->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.4,
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if (! empty($text)) {
                    return trim($text);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Gemini executive summary error: '.$e->getMessage());
        }

        return $this->fallback->generateExecutiveSummary($auditTitle, $directorate, $complianceScore, $findingsSummary);
    }
}
