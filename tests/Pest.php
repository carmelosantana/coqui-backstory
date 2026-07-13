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
