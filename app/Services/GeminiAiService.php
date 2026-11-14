<?php

namespace App\Services;

use App\Contracts\AiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeminiAiService implements AiService, AiServiceInterface
{
    protected ?FakeAiService $fallback;

    public function __construct(?FakeAiService $fallback = null)
    {
        $this->fallback = $fallback ?: new FakeAiService;
    }

    /**
     * Generate aviation QA checklist questions using Gemini API.
     */
    public function generateChecklist(string $requirementText, string $scope, int $count = 8): array
    {
        $apiKey = config('services.gemini.key');
        $model = config('services.gemini.model', 'gemini-2.5-flash');

        if (empty($apiKey)) {
            Log::info('GeminiAiService: GEMINI_API_KEY is not set. Falling back to FakeAiService.');

            return $this->fallback->generateChecklist($requirementText, $scope, $count);
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $prompt = "You are an aviation quality assurance auditor for the Nigeria Civil Aviation Authority.\n"
            ."From the requirement document below, write {$count} audit checklist questions for this\n"
            ."scope: {$scope}. Each question must check ONE verifiable requirement.\n"
            ."Return ONLY a JSON array. Each item:\n"
            ."{\"question\": string, \"type\": \"checkbox\"|\"radio\"|\"text\",\n"
            ." \"options\": string[] (radio: [\"Compliant\",\"Non-compliant\",\"N/A\"]; text: []),\n"
            ." \"requirement_ref\": string (clause/section from the document),\n"
            ." \"guidance\": string (what evidence the auditor should look for)}\n"
            ."Requirement document:\n"
            ."\"\"\"{$requirementText}\"\"\"";

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $apiKey,
            ])->timeout(60)->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.3,
                    'responseMimeType' => 'application/json',
                ],
            ]);

            if (! $response->successful()) {
                Log::warning('GeminiAiService: generateChecklist HTTP error: '.$response->status().' - '.$response->body());

                return $this->fallback->generateChecklist($requirementText, $scope, $count);
            }

            $json = $response->json();
            $rawContent = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';

            $decoded = json_decode($rawContent, true);

            // Handle potential wrapped structures (e.g. {"questions": [...]} or pure [...])
            $items = is_array($decoded) && isset($decoded['questions']) && is_array($decoded['questions'])
                ? $decoded['questions']
                : $decoded;

            if (! is_array($items)) {
                Log::warning('GeminiAiService: Invalid JSON response format from Gemini: '.$rawContent);

                return $this->fallback->generateChecklist($requirementText, $scope, $count);
            }

            $validated = [];
            foreach ($items as $item) {
                if (! is_array($item) || empty($item['question']) || ! is_string($item['question'])) {
                    continue;
                }

                $type = strtolower(trim((string) ($item['type'] ?? 'radio')));
                if (! in_array($type, ['checkbox', 'radio', 'text'])) {
                    $type = 'radio';
                }

                $options = null;
                if ($type === 'radio') {
                    $options = ! empty($item['options']) && is_array($item['options'])
                        ? array_values(array_filter($item['options'], 'is_string'))
                        : ['Compliant', 'Non-compliant', 'N/A'];
                } elseif ($type === 'checkbox') {
                    $options = ! empty($item['options']) && is_array($item['options'])
                        ? array_values(array_filter($item['options'], 'is_string'))
                        : ['Verified', 'Not Verified'];
                } elseif ($type === 'text') {
                    $options = [];
                }

                $validated[] = [
                    'question' => trim($item['question']),
                    'type' => $type,
                    'options' => $options,
                    'requirement_ref' => ! empty($item['requirement_ref']) ? trim((string) $item['requirement_ref']) : null,
                    'guidance' => ! empty($item['guidance']) ? trim((string) $item['guidance']) : null,
                    'source' => 'ai',
                ];
            }

            if (empty($validated)) {
                Log::warning('GeminiAiService: No valid checklist questions extracted from Gemini response.');

                return $this->fallback->generateChecklist($requirementText, $scope, $count);
            }

            return $validated;
        } catch (Throwable $e) {
            Log::warning('GeminiAiService: Exception in generateChecklist: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            return $this->fallback->generateChecklist($requirementText, $scope, $count);
        }
    }

    /**
     * Summarise audit findings and context for leadership using Gemini API.
     */
    public function summariseAudit(string $auditContext): string
    {
        $apiKey = config('services.gemini.key');
        $model = config('services.gemini.model', 'gemini-2.5-flash');

        if (empty($apiKey)) {
            Log::info('GeminiAiService: GEMINI_API_KEY is not set. Falling back to FakeAiService.');

            return $this->fallback->summariseAudit($auditContext);
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $prompt = "Write a clear, formal audit summary for the Director of Special\n"
            ."Duties, NCAA, in 3 short paragraphs: scope and overall result, key non-compliances,\n"
            ."recommended actions. Audit data: {$auditContext}";

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $apiKey,
            ])->timeout(60)->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.3,
                ],
            ]);

            if (! $response->successful()) {
                Log::warning('GeminiAiService: summariseAudit HTTP error: '.$response->status().' - '.$response->body());

                return $this->fallback->summariseAudit($auditContext);
            }

            $json = $response->json();
            $text = trim($json['candidates'][0]['content']['parts'][0]['text'] ?? '');

            if (empty($text)) {
                Log::warning('GeminiAiService: Empty summary response from Gemini.');

                return $this->fallback->summariseAudit($auditContext);
            }

            return $text;
        } catch (Throwable $e) {
            Log::warning('GeminiAiService: Exception in summariseAudit: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            return $this->fallback->summariseAudit($auditContext);
        }
    }

    /**
     * Generate an executive QA & internal audit management report using Gemini API.
     */
    public function generateReport(string $context): string
    {
        $apiKey = config('services.gemini.key');
        $model = config('services.gemini.model', 'gemini-2.5-flash');

        if (empty($apiKey)) {
            Log::info('GeminiAiService: GEMINI_API_KEY is not set. Falling back to FakeAiService.');

            return $this->fallback->generateReport($context);
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $prompt = 'Write a concise management report for the Director of Special Duties, NCAA: '
            .'overview, key risks, recurring non-compliances, overdue actions, recommendations. '
            ."Use short headings and bullet points. Data: {$context}";

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $apiKey,
            ])->timeout(60)->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.3,
                ],
            ]);

            if (! $response->successful()) {
                Log::warning('GeminiAiService: generateReport HTTP error: '.$response->status().' - '.$response->body());

                return $this->fallback->generateReport($context);
            }

            $json = $response->json();
            $text = trim($json['candidates'][0]['content']['parts'][0]['text'] ?? '');

            if (empty($text)) {
                Log::warning('GeminiAiService: Empty report response from Gemini.');

                return $this->fallback->generateReport($context);
            }

            return $text;
        } catch (Throwable $e) {
            Log::warning('GeminiAiService: Exception in generateReport: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            return $this->fallback->generateReport($context);
        }
    }

    public function analyzeFinding(string $title, string $condition, ?string $criteria = null): array
    {
        return $this->fallback->analyzeFinding($title, $condition, $criteria);
    }

    public function generateExecutiveSummary(string $auditTitle, string $directorate, int $complianceScore, array $findingsSummary): string
    {
        return $this->fallback->generateExecutiveSummary($auditTitle, $directorate, $complianceScore, $findingsSummary);
    }
}
