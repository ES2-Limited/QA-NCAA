<?php

use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->artisan('db:seed');
    Storage::fake('public');
});

test('lead_auditor can upload a PDF document', function () {
    $leadAuditor = User::where('email', 'lead_auditor@ncaa.gov.ng')->first();
    expect($leadAuditor)->not->toBeNull();

    $this->actingAs($leadAuditor);

    $file = UploadedFile::fake()->create('ncaa_flight_manual.pdf', 500, 'application/pdf');

    Livewire::test('pages::admin.documents')
        ->set('title', 'NCAA Flight Safety Manual 2026')
        ->set('description', 'Operating manual for safety surveillance.')
        ->set('file', $file)
        ->call('uploadDocument')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('documents', [
        'title' => 'NCAA Flight Safety Manual 2026',
        'original_name' => 'ncaa_flight_manual.pdf',
        'uploaded_by' => $leadAuditor->id,
    ]);

    $doc = Document::where('title', 'NCAA Flight Safety Manual 2026')->first();
    expect(Storage::disk('public')->exists($doc->file_path))->toBeTrue();
});

test('field_auditor gets 403 on document library routes', function () {
    $fieldAuditor = User::where('email', 'auditor@ncaa.gov.ng')->first();
    expect($fieldAuditor)->not->toBeNull();

    $this->actingAs($fieldAuditor);

    $response = $this->get('/admin/documents');
    $response->assertStatus(403);
});

test('a non-PDF/DOCX file is rejected during upload', function () {
    $leadAuditor = User::where('email', 'lead_auditor@ncaa.gov.ng')->first();
    $this->actingAs($leadAuditor);

    $invalidFile = UploadedFile::fake()->create('malicious_executable.exe', 300, 'application/x-msdownload');

    Livewire::test('pages::admin.documents')
        ->set('title', 'Invalid Binary Executable')
        ->set('file', $invalidFile)
        ->call('uploadDocument')
        ->assertHasErrors(['file']);

    $this->assertDatabaseMissing('documents', [
        'title' => 'Invalid Binary Executable',
    ]);
});
