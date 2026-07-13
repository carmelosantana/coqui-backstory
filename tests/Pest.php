<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

uses(TestCase::class)->in('Unit');

/**
 * Write a minimal Word2007 (.docx) document containing the given paragraphs.
 *
 * @param list<string> $paragraphs
 */
function createTestDocx(string $path, array $paragraphs): void
{
    $document = new \PhpOffice\PhpWord\PhpWord();
    $section = $document->addSection();

    foreach ($paragraphs as $paragraph) {
        $section->addText($paragraph);
    }

    $writer = \PhpOffice\PhpWord\IOFactory::createWriter($document, 'Word2007');
    $writer->save($path);
}

/**
 * Recursively delete a directory tree.
 */
function cleanupTree(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

/**
 * Create and return a unique temporary directory under the system temp dir.
 */
function makeTempDir(): string
{
    $dir = sys_get_temp_dir() . '/coqui-backstory-' . bin2hex(random_bytes(8));
    mkdir($dir, 0o777, true);

    return $dir;
}

/**
 * Recursively delete a directory tree, tolerating already-removed entries.
 */
function cleanupTestTree(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $files = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($files as $file) {
        $path = $file->getPathname();
        if ($file->isDir()) {
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }

    @rmdir($dir);
}

/**
 * @param list<string> $paragraphs
 */
function createTestOdt(string $path, array $paragraphs): void
{
    $zip = new \ZipArchive();
    $opened = $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    expect($opened)->toBeTrue();

    $body = implode('', array_map(
        static fn(string $paragraph): string => '<text:p>' . htmlspecialchars($paragraph, ENT_XML1) . '</text:p>',
        $paragraphs,
    ));

    $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.text');
    $zip->addFromString('content.xml', '<?xml version="1.0" encoding="UTF-8"?>'
        . '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">'
        . '<office:body><office:text>' . $body . '</office:text></office:body>'
        . '</office:document-content>');

    $zip->close();
}

function createRawOdt(string $path, string $textBodyXml): void
{
    $zip = new \ZipArchive();
    $opened = $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    expect($opened)->toBeTrue();

    $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.text');
    $zip->addFromString('content.xml', '<?xml version="1.0" encoding="UTF-8"?>'
        . '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">'
        . '<office:body><office:text>' . $textBodyXml . '</office:text></office:body>'
        . '</office:document-content>');

    $zip->close();
}

/**
 * @param array<string, list<list<string>>> $sheets
 */
function createTestOds(string $path, array $sheets): void
{
    $zip = new \ZipArchive();
    $opened = $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    expect($opened)->toBeTrue();

    $tables = [];
    foreach ($sheets as $sheetName => $rows) {
        $rowXml = [];
        foreach ($rows as $row) {
            $cells = [];
            foreach ($row as $value) {
                $cells[] = '<table:table-cell office:value-type="string"><text:p>'
                    . htmlspecialchars((string) $value, ENT_XML1)
                    . '</text:p></table:table-cell>';
            }

            $rowXml[] = '<table:table-row>' . implode('', $cells) . '</table:table-row>';
        }

        $tables[] = '<table:table table:name="' . htmlspecialchars($sheetName, ENT_XML1) . '">' . implode('', $rowXml) . '</table:table>';
    }

    $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.spreadsheet');
    $zip->addFromString('content.xml', '<?xml version="1.0" encoding="UTF-8"?>'
        . '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">'
        . '<office:body><office:spreadsheet>' . implode('', $tables) . '</office:spreadsheet></office:body>'
        . '</office:document-content>');

    $zip->close();
}

function createRawOds(string $path, string $spreadsheetXml): void
{
    $zip = new \ZipArchive();
    $opened = $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    expect($opened)->toBeTrue();

    $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.spreadsheet');
    $zip->addFromString('content.xml', '<?xml version="1.0" encoding="UTF-8"?>'
        . '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">'
        . '<office:body><office:spreadsheet>' . $spreadsheetXml . '</office:spreadsheet></office:body>'
        . '</office:document-content>');

    $zip->close();
}

/**
 * @param list<array{title?: string, bullets?: list<string>}> $slides
 */
function createTestOdp(string $path, array $slides): void
{
    $zip = new \ZipArchive();
    $opened = $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    expect($opened)->toBeTrue();

    $pages = [];
    foreach ($slides as $index => $slide) {
        $paragraphs = [];
        $title = trim((string) ($slide['title'] ?? ''));
        if ($title !== '') {
            $paragraphs[] = '<text:p>' . htmlspecialchars($title, ENT_XML1) . '</text:p>';
        }

        foreach ($slide['bullets'] ?? [] as $bullet) {
            $bullet = trim($bullet);
            if ($bullet !== '') {
                $paragraphs[] = '<text:p>' . htmlspecialchars($bullet, ENT_XML1) . '</text:p>';
            }
        }

        $pages[] = '<draw:page draw:name="Slide ' . ($index + 1) . '"><draw:frame><draw:text-box>'
            . implode('', $paragraphs)
            . '</draw:text-box></draw:frame></draw:page>';
    }

    $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.presentation');
    $zip->addFromString('content.xml', '<?xml version="1.0" encoding="UTF-8"?>'
        . '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">'
        . '<office:body><office:presentation>' . implode('', $pages) . '</office:presentation></office:body>'
        . '</office:document-content>');

    $zip->close();
}

function createRawOdp(string $path, string $presentationXml): void
{
    $zip = new \ZipArchive();
    $opened = $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    expect($opened)->toBeTrue();

    $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.presentation');
    $zip->addFromString('content.xml', '<?xml version="1.0" encoding="UTF-8"?>'
        . '<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">'
        . '<office:body><office:presentation>' . $presentationXml . '</office:presentation></office:body>'
        . '</office:document-content>');

    $zip->close();
}

/**
 * @param array<string, list<list<string>>> $sheets
 */
function createTestXlsx(string $path, array $sheets): void
{
    $zip = new \ZipArchive();
    $opened = $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    expect($opened)->toBeTrue();

    $sharedStringMap = [];
    $sharedStrings = [];
    $sheetXml = [];
    $sheetIndex = 1;

    foreach ($sheets as $sheetName => $rows) {
        $sheetRows = [];
        foreach ($rows as $rowIndex => $row) {
            $cells = [];
            foreach ($row as $columnIndex => $value) {
                $key = (string) $value;
                if (!array_key_exists($key, $sharedStringMap)) {
                    $sharedStringMap[$key] = count($sharedStrings);
                    $sharedStrings[] = $key;
                }

                $cellRef = columnLetter($columnIndex + 1) . ($rowIndex + 1);
                $cells[] = '<c r="' . $cellRef . '" t="s"><v>' . $sharedStringMap[$key] . '</v></c>';
            }

            $sheetRows[] = '<row r="' . ($rowIndex + 1) . '">' . implode('', $cells) . '</row>';
        }

        $sheetXml[] = [
            'id' => $sheetIndex,
            'name' => $sheetName,
            'xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                . '<sheetData>' . implode('', $sheetRows) . '</sheetData>'
                . '</worksheet>',
        ];
        $sheetIndex++;
    }

    $workbookSheets = [];
    $workbookRelationships = [];

    foreach ($sheetXml as $sheet) {
        $workbookSheets[] = '<sheet name="' . htmlspecialchars($sheet['name'], ENT_XML1) . '" sheetId="' . $sheet['id'] . '" r:id="rId' . $sheet['id'] . '"/>';
        $workbookRelationships[] = '<Relationship Id="rId' . $sheet['id'] . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sheet['id'] . '.xml"/>';
        $zip->addFromString('xl/worksheets/sheet' . $sheet['id'] . '.xml', $sheet['xml']);
    }

    $sharedStringItems = array_map(
        static fn(string $value): string => '<si><t>' . htmlspecialchars($value, ENT_XML1) . '</t></si>',
        $sharedStrings,
    );

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
        . implode('', array_map(
            static fn(array $sheet): string => '<Override PartName="/xl/worksheets/sheet' . $sheet['id'] . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>',
            $sheetXml,
        ))
        . '</Types>');

    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>');

    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets>' . implode('', $workbookSheets) . '</sheets>'
        . '</workbook>');

    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . implode('', $workbookRelationships)
        . '<Relationship Id="rIdSharedStrings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
        . '</Relationships>');

    $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($sharedStrings) . '" uniqueCount="' . count($sharedStrings) . '">'
        . implode('', $sharedStringItems)
        . '</sst>');

    $zip->close();
}

/**
 * @param list<array{title?: string, bullets?: list<string>, notes?: list<string>}> $slides
 */
function createTestPptx(string $path, array $slides): void
{
    $zip = new \ZipArchive();
    $opened = $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    expect($opened)->toBeTrue();

    $slideXml = [];
    $notesXml = [];
    $slideIndex = 1;

    foreach ($slides as $slide) {
        $paragraphs = [];
        $title = trim((string) ($slide['title'] ?? ''));
        if ($title !== '') {
            $paragraphs[] = createPptxParagraph($title);
        }

        foreach ($slide['bullets'] ?? [] as $bullet) {
            $bullet = trim($bullet);
            if ($bullet !== '') {
                $paragraphs[] = createPptxParagraph($bullet);
            }
        }

        $slideXml[] = [
            'id' => $slideIndex,
            'has_notes' => ($slide['notes'] ?? []) !== [],
            'xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>'
                . '<p:sp><p:nvSpPr><p:cNvPr id="2" name="TextBox ' . $slideIndex . '"/><p:cNvSpPr txBox="1"/><p:nvPr/></p:nvSpPr><p:spPr/>'
                . '<p:txBody><a:bodyPr/><a:lstStyle/>' . implode('', $paragraphs) . '</p:txBody></p:sp>'
                . '</p:spTree></p:cSld></p:sld>',
        ];

        $notesParagraphs = [];
        foreach ($slide['notes'] ?? [] as $note) {
            $note = trim($note);
            if ($note !== '') {
                $notesParagraphs[] = createPptxParagraph($note);
            }
        }

        if ($notesParagraphs !== []) {
            $notesXml[$slideIndex] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<p:notes xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>'
                . '<p:sp><p:nvSpPr><p:cNvPr id="3" name="Notes TextBox ' . $slideIndex . '"/><p:cNvSpPr txBox="1"/><p:nvPr/></p:nvSpPr><p:spPr/>'
                . '<p:txBody><a:bodyPr/><a:lstStyle/>' . implode('', $notesParagraphs) . '</p:txBody></p:sp>'
                . '</p:spTree></p:cSld></p:notes>';
        }

        $slideIndex++;
    }

    $slideIds = [];
    $slideRelationships = [];
    foreach ($slideXml as $slide) {
        $slideIds[] = '<p:sldId id="' . (255 + $slide['id']) . '" r:id="rId' . $slide['id'] . '"/>';
        $slideRelationships[] = '<Relationship Id="rId' . $slide['id'] . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide' . $slide['id'] . '.xml"/>';
        $zip->addFromString('ppt/slides/slide' . $slide['id'] . '.xml', $slide['xml']);

        $slideLevelRelationships = [];
        if ($slide['has_notes']) {
            $slideLevelRelationships[] = '<Relationship Id="rIdNotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/notesSlide" Target="../notesSlides/notesSlide' . $slide['id'] . '.xml"/>';
            $zip->addFromString('ppt/notesSlides/notesSlide' . $slide['id'] . '.xml', $notesXml[$slide['id']]);
        }

        if ($slideLevelRelationships !== []) {
            $zip->addFromString('ppt/slides/_rels/slide' . $slide['id'] . '.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . implode('', $slideLevelRelationships)
                . '</Relationships>');
        }
    }

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/>'
        . implode('', array_map(
            static fn(array $slide): string => '<Override PartName="/ppt/slides/slide' . $slide['id'] . '.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>',
            $slideXml,
        ))
        . implode('', array_map(
            static fn(int $slideId): string => '<Override PartName="/ppt/notesSlides/notesSlide' . $slideId . '.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.notesSlide+xml"/>',
            array_keys($notesXml),
        ))
        . '</Types>');

    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>'
        . '</Relationships>');

    $zip->addFromString('ppt/presentation.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<p:presentation xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<p:sldIdLst>' . implode('', $slideIds) . '</p:sldIdLst>'
        . '</p:presentation>');

    $zip->addFromString('ppt/_rels/presentation.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . implode('', $slideRelationships)
        . '</Relationships>');

    $zip->close();
}

function createPptxParagraph(string $text): string
{
    return '<a:p><a:r><a:t>' . htmlspecialchars($text, ENT_XML1) . '</a:t></a:r></a:p>';
}

function columnLetter(int $index): string
{
    $letters = '';

    while ($index > 0) {
        $index--;
        $letters = chr(($index % 26) + 65) . $letters;
        $index = intdiv($index, 26);
    }

    return $letters;
}
