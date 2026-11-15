<?php

namespace App\Services;

use App\Contracts\AiService;
use Illuminate\Support\Facades\Crypt;
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
     * Resolve whether AI is globally enabled and fetch key and model.
     */
    protected function getAiConfig(): array
    {
        $enabled = (bool) setting('ai.enabled', true);
        $model = setting('ai.model', config('services.gemini.model', 'gemini-2.5-flash'));

        $encryptedKey = setting('ai.api_key', '');
        $apiKey = '';

        if (! empty($encryptedKey)) {
            try {
                $apiKey = Crypt::decryptString($encryptedKey);
            } catch (Throwable) {
                $apiKey = $encryptedKey;
            }
        }

        if (empty($apiKey)) {
            $apiKey = config('services.gemini.key');
        }

        return [
            'enabled' => $enabled,
            'model' => $model,
            'api_key' => $apiKey,
        ];
    }

    /**
     * Generate aviation QA checklist questions using Gemini API or configured prompt template.
     */
    public function generateChecklist(string $requirementText, string $scope, int $count = 8): array
    {
        $config = $this->getAiConfig();

        if (! $config['enabled'] || empty($config['api_key'])) {
            Log::info('GeminiAiService: AI is disabled or GEMINI_API_KEY is not set. Falling back to FakeAiService.');

            return $this->fallback->generateChecklist($requirementText, $scope, $count);
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$config['model']}:generateContent";

        $defaultTemplate = "You are an aviation quality assurance auditor for the Nigeria Civil Aviation Authority.\n"
            ."From the requirement document below, write {count} audit checklist questions for this scope: {scope}. Each question must check ONE verifiable requirement.\n"
            ."Return ONLY a JSON array. Each item:\n"
            ."{\"question\": string, \"type\": \"checkbox\"|\"radio\"|\"text\", \"options\": string[] (radio: [\"Compliant\",\"Non-compliant\",\"N/A\"]; text: []), \"requirement_ref\": string, \"guidance\": string}\n"
            ."Requirement document:\n"
            .'"""{requirement_text}"""';

        $template = setting('ai.prompts.checklist_generation', $defaultTemplate);

        $prompt = str_replace(
            ['{count}', '{scope}', '{requirement_text}'],
            [$count, $scope, $requirementText],
            $template
        );

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $config['api_key'],
            ])->timeout(60)->post($url, [
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

            if ($response->failed()) {
                Log::warning('GeminiAiService: generateChecklist HTTP error: '.$response->status().' - '.$response->body());

                return $this->fallback->generateChecklist($requirementText, $scope, $count);
            }

            $data = $response->json();
            $rawContent = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

            if (empty($rawContent)) {
                return $this->fallback->generateChecklist($requirementText, $scope, $count);
            }

            $cleaned = trim($rawContent);
            if (str_starts_with($cleaned, '```json')) {
                $cleaned = substr($cleaned, 7);
            }
            if (str_starts_with($cleaned, '```')) {
                $cleaned = substr($cleaned, 3);
            }
            if (str_ends_with($cleaned, '```')) {
                $cleaned = substr($cleaned, 0, -3);
            }
            $cleaned = trim($cleaned);

            $parsed = json_decode($cleaned, true);

            if (! is_array($parsed)) {
                Log::warning('GeminiAiService: Invalid JSON response format from Gemini: '.$rawContent);

                return $this->fallback->generateChecklist($requirementText, $scope, $count);
            }

            $output = [];
            foreach ($parsed as $item) {
                if (! isset($item['question'])) {
                    continue;
                }

                $output[] = [
                    'question' => (string) $item['question'],
                    'type' => in_array($item['type'] ?? '', ['radio', 'checkbox', 'text']) ? $item['type'] : 'radio',
                    'options' => isset($item['options']) && is_array($item['options'])
                        ? array_values(array_map('strval', $item['options']))
                        : ['Compliant', 'Non-compliant', 'N/A'],
                    'requirement_ref' => (string) ($item['requirement_ref'] ?? ''),
                    'guidance' => (string) ($item['guidance'] ?? ''),
                    'source' => 'ai',
                ];
            }

            if (empty($output)) {
                Log::warning('GeminiAiService: No valid checklist questions extracted from Gemini response.');

                return $this->fallback->generateChecklist($requirementText, $scope, $count);
            }

            return array_slice($output, 0, $count);
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
        $config = $this->getAiConfig();

        if (! $config['enabled'] || empty($config['api_key'])) {
            Log::info('GeminiAiService: AI is disabled or GEMINI_API_KEY is not set. Falling back to FakeAiService.');

            return $this->fallback->summariseAudit($auditContext);
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$config['model']}:generateContent";

        $defaultTemplate = "You are an executive aviation safety analyst for the Nigeria Civil Aviation Authority.\n"
            ."Summarise the audit findings below in 3 concise bullet points for the Director of Special Duties, highlighting critical risks, overall compliance status, and required containment actions.\n"
            ."Audit details:\n\"\"\"{audit_context}\"\"\"";

        $template = setting('ai.prompts.audit_summary', $defaultTemplate);
        $prompt = str_replace('{audit_context}', $auditContext, $template);

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $config['api_key'],
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

            if ($response->failed()) {
                Log::warning('GeminiAiService: summariseAudit HTTP error: '.$response->status().' - '.$response->body());

                return $this->fallback->summariseAudit($auditContext);
            }

            $data = $response->json();
            $summary = trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '');

            if (empty($summary)) {
                Log::warning('GeminiAiService: Empty summary response from Gemini.');

                return $this->fallback->summariseAudit($auditContext);
            }

            return $summary;
        } catch (Throwable $e) {
            Log::warning('GeminiAiService: Exception in summariseAudit: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            return $this->fallback->summariseAudit($auditContext);
        }
    }

    /**
     * Generate an executive Markdown management report using Gemini API.
     */
    public function generateReport(string $context): string
    {
        $config = $this->getAiConfig();

        if (! $config['enabled'] || empty($config['api_key'])) {
            Log::info('GeminiAiService: AI is disabled or GEMINI_API_KEY is not set. Falling back to FakeAiService.');

            return $this->fallback->generateReport($context);
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$config['model']}:generateContent";

        $defaultTemplate = "You are a senior aviation quality assurance director. Draft a formal executive audit report for NCAA Director of Special Duties and Department Heads.\n"
            ."Include Executive Summary, Directorate Conformance Overview, Top Non-Conformance Areas, and Recommendations.\n"
            ."Audit data:\n\"\"\"{context}\"\"\"";

        $template = setting('ai.prompts.management_report', $defaultTemplate);
        $prompt = str_replace('{context}', $context, $template);

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $config['api_key'],
            ])->timeout(90)->post($url, [
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

            if ($response->failed()) {
                Log::warning('GeminiAiService: generateReport HTTP error: '.$response->status().' - '.$response->body());

                return $this->fallback->generateReport($context);
            }

            $data = $response->json();
            $report = trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '');

            if (empty($report)) {
                Log::warning('GeminiAiService: Empty report response from Gemini.');

                return $this->fallback->generateReport($context);
            }

            return $report;
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
