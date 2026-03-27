<?php

declare(strict_types=1);

/**
 * Tests for IdTracker.
 *
 * Verifies that ID counters are initialized from target document state
 * and incremented sequentially without collisions.
 */

use DocxMerge\Tracking\IdTracker;

describe('IdTracker', function (): void {
    describe('initializeFromTarget()', function (): void {
        it('detects max relationship ID from rels DOM', function (): void {
            // Arrange
            $relsDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="test" Target="a.xml"/>'
                . '<Relationship Id="rId5" Type="test" Target="b.xml"/>'
                . '<Relationship Id="rId3" Type="test" Target="c.xml"/>'
                . '</Relationships>'
            );
            $documentDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body><w:sectPr/></w:body></w:document>'
            );
            $zip = new ZipArchive();

            // Act
            $tracker = IdTracker::initializeFromTarget($zip, $relsDom, $documentDom, null);

            // Assert -- next ID should be rId6 (max was rId5)
            expect($tracker->nextRelationshipId())->toBe('rId6');
        });

        it('detects max numId from numbering DOM', function (): void {
            // Arrange
            $relsDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'
            );
            $documentDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body><w:sectPr/></w:body></w:document>'
            );
            $numberingDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:abstractNum w:abstractNumId="0"/>'
                . '<w:abstractNum w:abstractNumId="3"/>'
                . '<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num>'
                . '<w:num w:numId="7"><w:abstractNumId w:val="3"/></w:num>'
                . '</w:numbering>'
            );
            $zip = new ZipArchive();

            // Act
            $tracker = IdTracker::initializeFromTarget($zip, $relsDom, $documentDom, $numberingDom);

            // Assert -- next numId should be 8, next abstractNumId should be 4
            expect($tracker->nextNumId())->toBe(8);
            expect($tracker->nextAbstractNumId())->toBe(4);
        });

        it('detects max docPr ID from document DOM', function (): void {
            // Arrange
            $relsDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'
            );
            $documentDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
                . ' xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing">'
                . '<w:body>'
                . '<w:p><w:r><w:drawing><wp:inline><wp:docPr id="5" name="Image 5"/></wp:inline></w:drawing></w:r></w:p>'
                . '<w:sectPr/></w:body></w:document>'
            );
            $zip = new ZipArchive();

            // Act
            $tracker = IdTracker::initializeFromTarget($zip, $relsDom, $documentDom, null);

            // Assert -- next docPr id should be 6
            expect($tracker->nextDocPrId())->toBe(6);
        });

        it('detects max bookmark ID from document DOM', function (): void {
            // Arrange
            $relsDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'
            );
            $documentDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body>'
                . '<w:bookmarkStart w:id="0" w:name="bm1"/>'
                . '<w:bookmarkEnd w:id="0"/>'
                . '<w:bookmarkStart w:id="10" w:name="bm2"/>'
                . '<w:bookmarkEnd w:id="10"/>'
                . '<w:sectPr/></w:body></w:document>'
            );
            $zip = new ZipArchive();

            // Act
            $tracker = IdTracker::initializeFromTarget($zip, $relsDom, $documentDom, null);

            // Assert -- next bookmark id should be 11
            expect($tracker->nextBookmarkId())->toBe(11);
        });
        it('handles null numberingDom by skipping numbering scan', function (): void {
            // Arrange
            $relsDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'
            );
            $documentDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body><w:sectPr/></w:body></w:document>'
            );
            $zip = new ZipArchive();

            // Act -- null numberingDom should not throw
            $tracker = IdTracker::initializeFromTarget($zip, $relsDom, $documentDom, null);

            // Assert -- numId starts from 1 (counter was 0)
            expect($tracker->nextNumId())->toBe(1);
            expect($tracker->nextAbstractNumId())->toBe(1);
        });
    });

    describe('sequential increment', function (): void {
        it('generates sequential relationship IDs', function (): void {
            // Arrange
            $relsDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'
            );
            $documentDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body><w:sectPr/></w:body></w:document>'
            );
            $zip = new ZipArchive();
            $tracker = IdTracker::initializeFromTarget($zip, $relsDom, $documentDom, null);

            // Act
            $id1 = $tracker->nextRelationshipId();
            $id2 = $tracker->nextRelationshipId();
            $id3 = $tracker->nextRelationshipId();

            // Assert
            expect($id1)->toBe('rId1');
            expect($id2)->toBe('rId2');
            expect($id3)->toBe('rId3');
        });

        it('generates sequential header/footer numbers', function (): void {
            // Arrange
            $relsDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'
            );
            $documentDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body><w:sectPr/></w:body></w:document>'
            );
            $zip = new ZipArchive();
            $tracker = IdTracker::initializeFromTarget($zip, $relsDom, $documentDom, null);

            // Act + Assert
            expect($tracker->nextHeaderFooterNumber())->toBe(1);
            expect($tracker->nextHeaderFooterNumber())->toBe(2);
        });

        it('generates sequential style IDs starting from 1001', function (): void {
            // Arrange
            $relsDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'
            );
            $documentDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body><w:sectPr/></w:body></w:document>'
            );
            $zip = new ZipArchive();
            $tracker = IdTracker::initializeFromTarget($zip, $relsDom, $documentDom, null);

            // Act + Assert -- style ID counter starts at 1000, so first next is 1001
            expect($tracker->nextStyleId())->toBe(1001);
            expect($tracker->nextStyleId())->toBe(1002);
        });

        it('generates sequential bookmark IDs', function (): void {
            // Arrange
            $relsDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'
            );
            $documentDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body><w:sectPr/></w:body></w:document>'
            );
            $zip = new ZipArchive();
            $tracker = IdTracker::initializeFromTarget($zip, $relsDom, $documentDom, null);

            // Act + Assert
            expect($tracker->nextBookmarkId())->toBe(1);
            expect($tracker->nextBookmarkId())->toBe(2);
        });

        it('generates sequential image numbers', function (): void {
            // Arrange
            $relsDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'
            );
            $documentDom = createDomFromXml(
                '<?xml version="1.0"?>'
                . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
                . '<w:body><w:sectPr/></w:body></w:document>'
            );
            $zip = new ZipArchive();
            $tracker = IdTracker::initializeFromTarget($zip, $relsDom, $documentDom, null);

            // Act + Assert
            expect($tracker->nextImageNumber())->toBe(1);
            expect($tracker->nextImageNumber())->toBe(2);
        });
    });
});
