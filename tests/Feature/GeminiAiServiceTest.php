<?php

use App\Models\Document;
use App\Services\DocumentTextExtractor;
use App\Services\GeminiAiService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->artisan('db:seed');
});

test('DocumentTextExtractor reads a seeded PDF', function () {
    $doc = Document::where('title', 'Internal QA Programme Requirements')->first();
    expect($doc)->not->toBeNull();

    $extractor = new DocumentTextExtractor;
    $text = $extractor->extract($doc);

    expect($text)->toBeString();
    expect(strlen($text))->toBeGreaterThan(50);
    expect($text)->toContain('NCAA');
});

test('GeminiAiService parses questions when Gemini responds with valid JSON', function () {
    Config::set('services.gemini.key', 'test-gemini-key');
    Config::set('services.gemini.model', 'gemini-2.5-flash');

    $mockJsonResponse = [
        'candidates' => [
            [
                'content' => [
                    'parts' => [
                        [
                            'text' => json_encode([
                                [
                                    'question' => 'Is fuel contamination testing performed daily before dispatch?',
                                    'type' => 'radio',
                                    'options' => ['Compliant', 'Non-compliant', 'N/A'],
                                    'requirement_ref' => 'Nig.CARs Part 8.8.2',
                                    'guidance' => 'Inspect fuel water detector paste log.',
                                ],
                                [
                                    'question' => 'Confirm all maintenance personnel have completed Nig.CARs human factors training.',
                                    'type' => 'radio',
                                    'options' => ['Compliant', 'Non-compliant', 'N/A'],
                                    'requirement_ref' => 'Nig.CARs Part 6.2.2',
                                    'guidance' => 'Check training matrix.',
                                ],
                            ]),
                        ],
                    ],
                ],
            ],
        ],
    ];

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response($mockJsonResponse, 200),
    ]);

    $service = new GeminiAiService;
    $questions = $service->generateChecklist('Document sample text', 'Fuel Safety Verification', 2);

    expect($questions)->toBeArray();
    expect($questions)->toHaveCount(2);
    expect($questions[0]['question'])->toBe('Is fuel contamination testing performed daily before dispatch?');
    expect($questions[0]['type'])->toBe('radio');
    expect($questions[0]['requirement_ref'])->toBe('Nig.CARs Part 8.8.2');
    expect($questions[0]['source'])->toBe('ai');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'generativelanguage.googleapis.com')
            && $request->hasHeader('x-goog-api-key', 'test-gemini-key');
    });
});

test('GeminiAiService falls back to FakeAiService on 500 HTTP error', function () {
    Config::set('services.gemini.key', 'test-gemini-key');
    Config::set('services.gemini.model', 'gemini-2.5-flash');

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response('Internal Server Error', 500),
    ]);

    $service = new GeminiAiService;
    $questions = $service->generateChecklist('Document sample text', 'Engine Overhaul Shop', 3);

    // Fallback should return 3 realistic questions
    expect($questions)->toBeArray();
    expect($questions)->toHaveCount(3);
    expect($questions[0])->toHaveKeys(['question', 'type', 'options', 'requirement_ref', 'guidance', 'source']);
    expect($questions[0]['source'])->toBe('ai');
});

test('GeminiAiService summariseAudit returns text on success', function () {
    Config::set('services.gemini.key', 'test-gemini-key');

    Http::fake([
        '*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => "Paragraph 1\n\nParagraph 2\n\nParagraph 3"]]]],
            ],
        ], 200),
    ]);

    $service = new GeminiAiService;
    $summary = $service->summariseAudit('Audit findings data context');
    expect($summary)->toBe("Paragraph 1\n\nParagraph 2\n\nParagraph 3");
});

test('GeminiAiService summariseAudit falls back to FakeAiService on error', function () {
    Config::set('services.gemini.key', 'test-gemini-key');

    Http::fake([
        '*' => Http::response('Server Error', 500),
    ]);

    $service = new GeminiAiService;
    $fallbackSummary = $service->summariseAudit('Audit findings data context');
    expect($fallbackSummary)->toBeString();
    expect($fallbackSummary)->toContain('Directorate of Special Duties');
});
