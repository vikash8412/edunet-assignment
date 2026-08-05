<?php

use App\Services\Import\ExcelParser;
use App\Services\Import\TypeInferrer;
use App\Services\Import\WordParser;
use App\Services\Schema\SchemaNormalizer;
use App\Services\Schema\SchemaValidator;

const SAMPLES = __DIR__.'/../../../samples';

it('parses the job application sample docx', function () {
    $result = (new WordParser())->parse(SAMPLES.'/job-application.docx');
    $schema = $result['schema'];

    expect($schema['title'])->toBe('Job Application Form')
        ->and($schema['description'])->toContain('engineering positions')
        ->and(count($schema['sections']))->toBeGreaterThanOrEqual(3);

    $fields = collect($schema['sections'])->flatMap(fn ($s) => $s['fields']);
    $byLabel = fn (string $label) => $fields->firstWhere('label', $label);

    expect($byLabel('Full name')['required'])->toBeTrue()
        ->and($byLabel('Email address')['type'])->toBe('email')
        ->and($byLabel('Phone number')['type'])->toBe('phone')
        ->and($byLabel('Which role are you applying for?')['type'])->toBe('radio')
        ->and($byLabel('Which role are you applying for?')['options'])->toContain('Backend Developer')
        ->and($byLabel('Which technologies do you know?')['type'])->toBe('checkbox')
        ->and($byLabel('Which technologies do you know?')['options'])->toHaveCount(4)
        ->and($byLabel('How many years of experience do you have?')['type'])->toBe('number')
        ->and($byLabel('Describe your most interesting project')['type'])->toBe('textarea')
        ->and($byLabel('Upload your resume')['type'])->toBe('file')
        ->and($byLabel('Upload your resume')['required'])->toBeTrue()
        ->and($byLabel('Portfolio website')['validation'])->toBe(['url' => true]);
});

it('parses the table-based event feedback docx and flags noise', function () {
    $result = (new WordParser())->parse(SAMPLES.'/event-feedback.docx');
    $fields = collect($result['schema']['sections'])->flatMap(fn ($s) => $s['fields']);

    expect($fields->firstWhere('label', 'Attendee name'))->not->toBeNull()
        ->and($fields->firstWhere('label', 'Company email')['type'])->toBe('email')
        ->and($fields->firstWhere('label', 'How would you rate the event overall?')['type'])->toBe('rating')
        ->and($fields->firstWhere('label', 'Would you attend again?')['options'])->toContain('Definitely')
        ->and(implode(' ', $result['warnings']))->toContain('scribbled margin note');
});

it('parses the template-layout xlsx with explicit types', function () {
    $result = (new ExcelParser())->parse(SAMPLES.'/fields-template.xlsx', 'fields-template.xlsx');
    $fields = $result['schema']['sections'][0]['fields'];
    $byLabel = fn (string $label) => collect($fields)->firstWhere('label', $label);

    expect($fields)->toHaveCount(10)
        ->and($byLabel('Full name')['required'])->toBeTrue()
        ->and($byLabel('Department')['type'])->toBe('dropdown')
        ->and($byLabel('Department')['options'])->toContain('Engineering')
        ->and($byLabel('Skills')['type'])->toBe('checkbox')
        ->and($byLabel('ID document')['type'])->toBe('file')
        ->and($byLabel('Full name')['help'])->toBe('As on your ID card');
});

it('infers types from data samples in the header-row xlsx', function () {
    $result = (new ExcelParser())->parse(SAMPLES.'/header-row.xlsx', 'header-row.xlsx');
    $fields = $result['schema']['sections'][0]['fields'];
    $byLabel = fn (string $label) => collect($fields)->firstWhere('label', $label);

    expect($byLabel('Email')['type'])->toBe('email')
        ->and($byLabel('Age')['type'])->toBe('number')
        ->and($byLabel('Registration date')['type'])->toBe('date')
        ->and($byLabel('T-shirt size')['type'])->toBe('dropdown')
        ->and($byLabel('T-shirt size')['options'])->toContain('M');
});

it('produces schemas that pass the strict validator after normalization', function (string $file) {
    $result = str_ends_with($file, '.docx')
        ? (new WordParser())->parse(SAMPLES.'/'.$file)
        : (new ExcelParser())->parse(SAMPLES.'/'.$file, $file);

    $normalized = (new SchemaNormalizer())->normalize($result['schema']);
    $validation = (new SchemaValidator())->validate($normalized['schema']);

    expect($validation->valid)->toBeTrue()->and($validation->errors)->toBeEmpty();
})->with(['job-application.docx', 'event-feedback.docx', 'fields-template.xlsx', 'header-row.xlsx']);

it('survives hostile input files without crashing', function () {
    $dir = sys_get_temp_dir();

    // Not a real docx.
    file_put_contents($dir.'/fake.docx', 'plain text pretending to be a doc');
    $result = (new WordParser())->parse($dir.'/fake.docx');
    expect($result['warnings'])->not->toBeEmpty();

    // Empty xlsx.
    $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
    (new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($dir.'/empty.xlsx');
    $result = (new ExcelParser())->parse($dir.'/empty.xlsx', 'empty.xlsx');
    expect(implode(' ', $result['warnings']))->toContain('empty');
});

it('keyword-infers common field types', function () {
    $inferrer = new TypeInferrer();

    expect($inferrer->infer('Your e-mail')['type'])->toBe('email')
        ->and($inferrer->infer('WhatsApp number')['type'])->toBe('phone')
        ->and($inferrer->infer('Attach your certificate')['type'])->toBe('file')
        ->and($inferrer->infer('Interview date')['type'])->toBe('date')
        ->and($inferrer->infer('Rate our service')['type'])->toBe('rating')
        ->and($inferrer->infer('Tell us about yourself')['type'])->toBe('textarea')
        ->and($inferrer->infer('GitHub link')['validation'])->toBe(['url' => true])
        ->and($inferrer->infer('Favourite colour')['type'])->toBeNull();
});
