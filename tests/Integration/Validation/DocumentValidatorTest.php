<?php

declare(strict_types=1);

/**
 * Tests for DocumentValidator.
 *
 * Verifies document integrity validation including rId resolution,
 * numId existence, style reference validity, and detection of
 * orphaned references that would cause Word to show a repair prompt.
 */

use DocxMerge\Cache\SourceDocumentCache;
use DocxMerge\Dto\ValidationResult;
use DocxMerge\Merge\MergeContext;
use DocxMerge\Tracking\IdTracker;
use DocxMerge\Validation\DocumentValidator;

describe('DocumentValidator', function (): void {
    // Holds the path of the temporary ZIP so it can be cleaned up.
    $tempZipPath = '';

    afterEach(function () use (&$tempZipPath): void {
        if ($tempZipPath !== '' && file_exists($tempZipPath)) {
            unlink($tempZipPath);
        }
        $tempZipPath = '';
    });

    describe('validate()', function (): void {
        it('returns valid result for a consistent document', function () use (&$tempZipPath): void {
            // Arrange
            $validator = new DocumentValidator();

            $documentDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:p><w:pPr><w:pStyle w:val="Normal"/></w:pPr>'
                . '<w:r><w:t>Text</w:t></w:r></w:p>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );
            $stylesDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:style w:type="paragraph" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
                . '</w:styles>'
            );
            $numberingDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'
            );
            $relsDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'
            );
            $contentTypesDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>'
            );

            $tempZipPath = tempnam(sys_get_temp_dir(), 'dv_test_') . '.zip';
            $zip = new ZipArchive();
            $zip->open($tempZipPath, ZipArchive::CREATE);
            $zip->addFromString('word/document.xml', '<doc/>');
            $zip->close();
            $zip->open($tempZipPath);

            $context = new MergeContext(
                $zip,
                $documentDom,
                $stylesDom,
                $numberingDom,
                $relsDom,
                $contentTypesDom,
                new IdTracker(),
                new SourceDocumentCache(),
            );

            // Act
            $result = $validator->validate($context);
            $zip->close();

            // Assert
            expect($result)->toBeInstanceOf(ValidationResult::class);
            expect($result->isValid())->toBeTrue();
        });

        it('reports an error for an orphaned rId in document.xml', function () use (&$tempZipPath): void {
            // Arrange
            $validator = new DocumentValidator();

            // Document references rId1 but rels DOM has no matching relationship
            $documentDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
                . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<w:body>'
                . '<w:p><w:r><w:drawing><a:blip xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
                . ' r:embed="rId1"/></w:drawing></w:r></w:p>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );
            $stylesDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'
            );
            $numberingDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'
            );
            // Empty rels -- rId1 is orphaned
            $relsDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'
            );
            $contentTypesDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>'
            );

            $tempZipPath = tempnam(sys_get_temp_dir(), 'dv_test_') . '.zip';
            $zip = new ZipArchive();
            $zip->open($tempZipPath, ZipArchive::CREATE);
            $zip->addFromString('word/document.xml', '<doc/>');
            $zip->close();
            $zip->open($tempZipPath);

            $context = new MergeContext(
                $zip,
                $documentDom,
                $stylesDom,
                $numberingDom,
                $relsDom,
                $contentTypesDom,
                new IdTracker(),
                new SourceDocumentCache(),
            );

            // Act
            $result = $validator->validate($context);
            $zip->close();

            // Assert -- should report at least one error for orphaned rId
            expect($result->isValid())->toBeFalse();
            expect($result->errors)->not->toBeEmpty();
        });

        it('reports an error for an orphaned numId without matching w:num', function () use (&$tempZipPath): void {
            // Arrange
            $validator = new DocumentValidator();

            // Document references numId=5 but numbering has no w:num with numId=5
            $documentDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:p><w:pPr><w:numPr><w:numId w:val="5"/></w:numPr></w:pPr></w:p>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );
            $stylesDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'
            );
            $numberingDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'
            );
            $relsDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'
            );
            $contentTypesDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>'
            );

            $tempZipPath = tempnam(sys_get_temp_dir(), 'dv_test_') . '.zip';
            $zip = new ZipArchive();
            $zip->open($tempZipPath, ZipArchive::CREATE);
            $zip->addFromString('word/document.xml', '<doc/>');
            $zip->close();
            $zip->open($tempZipPath);

            $context = new MergeContext(
                $zip,
                $documentDom,
                $stylesDom,
                $numberingDom,
                $relsDom,
                $contentTypesDom,
                new IdTracker(),
                new SourceDocumentCache(),
            );

            // Act
            $result = $validator->validate($context);
            $zip->close();

            // Assert
            expect($result->isValid())->toBeFalse();
            expect($result->errors)->not->toBeEmpty();
        });

        it('reports an error for a style reference that does not exist', function () use (&$tempZipPath): void {
            // Arrange
            $validator = new DocumentValidator();

            // Document references style "MissingStyle" but styles DOM has no such style
            $documentDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:p><w:pPr><w:pStyle w:val="MissingStyle"/></w:pPr></w:p>'
                . '<w:sectPr/>'
                . '</w:body></w:document>'
            );
            $stylesDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'
            );
            $numberingDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'
            );
            $relsDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'
            );
            $contentTypesDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>'
            );

            $tempZipPath = tempnam(sys_get_temp_dir(), 'dv_test_') . '.zip';
            $zip = new ZipArchive();
            $zip->open($tempZipPath, ZipArchive::CREATE);
            $zip->addFromString('word/document.xml', '<doc/>');
            $zip->close();
            $zip->open($tempZipPath);

            $context = new MergeContext(
                $zip,
                $documentDom,
                $stylesDom,
                $numberingDom,
                $relsDom,
                $contentTypesDom,
                new IdTracker(),
                new SourceDocumentCache(),
            );

            // Act
            $result = $validator->validate($context);
            $zip->close();

            // Assert
            expect($result->isValid())->toBeFalse();
            expect($result->errors)->not->toBeEmpty();
        });
    });
});
