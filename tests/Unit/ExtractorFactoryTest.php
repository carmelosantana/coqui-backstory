<?php

declare(strict_types=1);

use CoquiBot\Toolkits\Backstory\Extractor\CodeBlockExtractor;
use CoquiBot\Toolkits\Backstory\Extractor\CsvExtractor;
use CoquiBot\Toolkits\Backstory\Extractor\DocxExtractor;
use CoquiBot\Toolkits\Backstory\Extractor\ExtractorFactory;
use CoquiBot\Toolkits\Backstory\Extractor\HtmlExtractor;
use CoquiBot\Toolkits\Backstory\Extractor\JsonExtractor;
use CoquiBot\Toolkits\Backstory\Extractor\MarkdownExtractor;
use CoquiBot\Toolkits\Backstory\Extractor\OdpExtractor;
use CoquiBot\Toolkits\Backstory\Extractor\OdsExtractor;
use CoquiBot\Toolkits\Backstory\Extractor\OdtExtractor;
use CoquiBot\Toolkits\Backstory\Extractor\PdfExtractor;
use CoquiBot\Toolkits\Backstory\Extractor\PptxExtractor;
use CoquiBot\Toolkits\Backstory\Extractor\RtfExtractor;
use CoquiBot\Toolkits\Backstory\Extractor\SqlExtractor;
use CoquiBot\Toolkits\Backstory\Extractor\TextExtractor;
use CoquiBot\Toolkits\Backstory\Extractor\XlsxExtractor;
use CoquiBot\Toolkits\Backstory\Extractor\XmlExtractor;
use CoquiBot\Toolkits\Backstory\Extractor\YamlExtractor;

test('ExtractorFactory maps dependency-free extensions to extractors', function () {
    $factory = new ExtractorFactory();

    expect($factory->get('txt'))->toBeInstanceOf(TextExtractor::class);
    expect($factory->get('md'))->toBeInstanceOf(MarkdownExtractor::class);
    expect($factory->get('mdx'))->toBeInstanceOf(MarkdownExtractor::class);
    expect($factory->get('json'))->toBeInstanceOf(JsonExtractor::class);
    expect($factory->get('yaml'))->toBeInstanceOf(YamlExtractor::class);
    expect($factory->get('yml'))->toBeInstanceOf(YamlExtractor::class);
    expect($factory->get('csv'))->toBeInstanceOf(CsvExtractor::class);
    expect($factory->get('tsv'))->toBeInstanceOf(CsvExtractor::class);
    expect($factory->get('xml'))->toBeInstanceOf(XmlExtractor::class);
    expect($factory->get('rtf'))->toBeInstanceOf(RtfExtractor::class);
    expect($factory->get('sql'))->toBeInstanceOf(SqlExtractor::class);
    expect($factory->get('py'))->toBeInstanceOf(CodeBlockExtractor::class);
});

test('ExtractorFactory maps the absorbed dependency-carrying formats', function () {
    $factory = new ExtractorFactory();

    // These live in this package now (Word, PDF, HTML are hard deps) — no discovery seam.
    expect($factory->get('docx'))->toBeInstanceOf(DocxExtractor::class);
    expect($factory->get('docm'))->toBeInstanceOf(DocxExtractor::class);
    expect($factory->get('pdf'))->toBeInstanceOf(PdfExtractor::class);
    expect($factory->get('html'))->toBeInstanceOf(HtmlExtractor::class);
    expect($factory->get('htm'))->toBeInstanceOf(HtmlExtractor::class);
});

test('ExtractorFactory maps the runtime-guarded office formats when supported', function () {
    $factory = new ExtractorFactory();

    if (OdtExtractor::isRuntimeSupported()) {
        expect($factory->get('odt'))->toBeInstanceOf(OdtExtractor::class);
    }
    if (OdsExtractor::isRuntimeSupported()) {
        expect($factory->get('ods'))->toBeInstanceOf(OdsExtractor::class);
    }
    if (OdpExtractor::isRuntimeSupported()) {
        expect($factory->get('odp'))->toBeInstanceOf(OdpExtractor::class);
    }
    if (XlsxExtractor::isRuntimeSupported()) {
        expect($factory->get('xlsx'))->toBeInstanceOf(XlsxExtractor::class);
        expect($factory->get('xlsm'))->toBeInstanceOf(XlsxExtractor::class);
    }
    if (PptxExtractor::isRuntimeSupported()) {
        expect($factory->get('pptx'))->toBeInstanceOf(PptxExtractor::class);
        expect($factory->get('pptm'))->toBeInstanceOf(PptxExtractor::class);
    }
});

test('ExtractorFactory returns null for unsupported extension', function () {
    $factory = new ExtractorFactory();
    expect($factory->get('exe'))->toBeNull();
});

test('ExtractorFactory isSupported', function () {
    $factory = new ExtractorFactory();

    expect($factory->isSupported('txt'))->toBeTrue();
    expect($factory->isSupported('TXT'))->toBeTrue();
    expect($factory->isSupported('php'))->toBeTrue();
    expect($factory->isSupported('docx'))->toBeTrue();
    expect($factory->isSupported('pdf'))->toBeTrue();
    expect($factory->isSupported('html'))->toBeTrue();
    expect($factory->isSupported('odt'))->toBe(OdtExtractor::isRuntimeSupported());
    expect($factory->isSupported('ods'))->toBe(OdsExtractor::isRuntimeSupported());
    expect($factory->isSupported('odp'))->toBe(OdpExtractor::isRuntimeSupported());
    expect($factory->isSupported('xlsx'))->toBe(XlsxExtractor::isRuntimeSupported());
    expect($factory->isSupported('xlsm'))->toBe(XlsxExtractor::isRuntimeSupported());
    expect($factory->isSupported('pptx'))->toBe(PptxExtractor::isRuntimeSupported());
    expect($factory->isSupported('pptm'))->toBe(PptxExtractor::isRuntimeSupported());
    expect($factory->isSupported('exe'))->toBeFalse();
});
