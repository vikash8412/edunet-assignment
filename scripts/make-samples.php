<?php

/**
 * Generates the sample import files committed under samples/.
 * Run from the project root:  php scripts/make-samples.php
 */

require __DIR__.'/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Writer\Word2007;

$dir = __DIR__.'/../samples';
@mkdir($dir, 0755, true);

// ---------------------------------------------------------------------------
// 1. job-application.docx — headings, questions, choice lists, required marks
// ---------------------------------------------------------------------------

$word = new PhpWord();
$word->addTitleStyle(1, ['size' => 20, 'bold' => true]);
$word->addTitleStyle(2, ['size' => 14, 'bold' => true]);

$section = $word->addSection();
$section->addTitle('Job Application Form', 1);
$section->addText('Apply for the open engineering positions at our company.');

$section->addTitle('Personal Details', 2);
$section->addText('Full name (required):');
$section->addText('Email address:');
$section->addText('Phone number:');
$section->addText('Date of birth:');

$section->addTitle('Position', 2);
$section->addText('Which role are you applying for?');
$section->addListItem('Backend Developer');
$section->addListItem('Frontend Developer');
$section->addListItem('DevOps Engineer');

$section->addText('Which technologies do you know?');
$section->addText('☐ PHP');
$section->addText('☐ JavaScript');
$section->addText('☐ Python');
$section->addText('☐ SQL');

$section->addTitle('Experience', 2);
$section->addText('How many years of experience do you have?');
$section->addText('Describe your most interesting project:');
$section->addText('Upload your resume (required):');
$section->addText('Portfolio website:');

(new Word2007($word))->save($dir.'/job-application.docx');

// ---------------------------------------------------------------------------
// 2. event-feedback.docx — table-based layout + unparseable content
// ---------------------------------------------------------------------------

$word2 = new PhpWord();
$word2->addTitleStyle(1, ['size' => 20, 'bold' => true]);
$word2->addTitleStyle(2, ['size' => 14, 'bold' => true]);

$section2 = $word2->addSection();
$section2->addTitle('Event Feedback', 1);
$section2->addText('Help us make the next conference better.');

$section2->addTitle('About you', 2);
$table = $section2->addTable(['borderSize' => 4, 'borderColor' => 'AAAAAA']);
foreach (['Attendee name', 'Company email', 'Job title'] as $label) {
    $row = $table->addRow();
    $row->addCell(4000)->addText($label);
    $row->addCell(5000)->addText('');
}

$section2->addTitle('Your feedback', 2);
$section2->addText('How would you rate the event overall? (rating)');
$section2->addText('Would you attend again?');
$section2->addListItem('Definitely');
$section2->addListItem('Maybe');
$section2->addListItem('No');
$section2->addText('Any other comments?');

// Deliberately hard-to-parse content, to exercise the warnings path.
$section2->addTextBreak();
$section2->addText('~~ scribbled margin note, not a question ~~', null, ['alignment' => Jc::CENTER]);

(new Word2007($word2))->save($dir.'/event-feedback.docx');

// ---------------------------------------------------------------------------
// 3. fields-template.xlsx — the documented template layout
// ---------------------------------------------------------------------------

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Fields');

$rows = [
    ['Label', 'Type', 'Required', 'Options', 'Help', 'Placeholder'],
    ['Full name', 'text', 'yes', '', 'As on your ID card', 'Jane Doe'],
    ['Email address', 'email', 'yes', '', '', 'jane@example.com'],
    ['Phone', 'phone', 'no', '', 'Include country code', ''],
    ['Department', 'dropdown', 'yes', 'Engineering; Marketing; Sales; HR', '', ''],
    ['Start date', 'date', 'yes', '', '', ''],
    ['Employment type', 'radio', 'yes', 'Full-time; Part-time; Contract', '', ''],
    ['Skills', 'checkbox', 'no', 'PHP; JavaScript; Python; SQL', 'Pick all that apply', ''],
    ['Expected salary', 'number', 'no', '', 'Annual, in USD', ''],
    ['Cover letter', 'textarea', 'no', '', 'Tell us why you fit', ''],
    ['ID document', 'file', 'yes', '', 'PDF only', ''],
];

$sheet->fromArray($rows, null, 'A1');
$sheet->getStyle('A1:F1')->getFont()->setBold(true);
foreach (range('A', 'F') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

(new Xlsx($spreadsheet))->save($dir.'/fields-template.xlsx');

// ---------------------------------------------------------------------------
// 4. header-row.xlsx — plain export-style sheet with sample data rows
// ---------------------------------------------------------------------------

$spreadsheet2 = new Spreadsheet();
$sheet2 = $spreadsheet2->getActiveSheet();
$sheet2->setTitle('Responses');

$rows2 = [
    ['Participant name', 'Email', 'Age', 'City', 'T-shirt size', 'Registration date'],
    ['Aarav Sharma', 'aarav@example.com', 24, 'Mumbai', 'M', '2026-05-01'],
    ['Priya Patel', 'priya@example.com', 31, 'Ahmedabad', 'S', '2026-05-02'],
    ['Rohan Gupta', 'rohan@example.com', 28, 'Delhi', 'L', '2026-05-02'],
    ['Sneha Iyer', 'sneha@example.com', 26, 'Chennai', 'M', '2026-05-03'],
    ['Vikram Singh', 'vikram@example.com', 35, 'Jaipur', 'L', '2026-05-04'],
];

$sheet2->fromArray($rows2, null, 'A1');
$sheet2->getStyle('A1:F1')->getFont()->setBold(true);
foreach (range('A', 'F') as $col) {
    $sheet2->getColumnDimension($col)->setAutoSize(true);
}

(new Xlsx($spreadsheet2))->save($dir.'/header-row.xlsx');

echo "Samples written to samples/\n";
