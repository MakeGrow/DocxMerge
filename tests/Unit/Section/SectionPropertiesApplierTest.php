<?php

declare(strict_types=1);

/**
 * Tests for SectionPropertiesApplier.
 *
 * Verifies that intermediate section properties have their headerReference
 * and footerReference rIds remapped, and that the final section properties
 * are correctly applied to the target document.
 */

use DocxMerge\Dto\ExtractedContent;
use DocxMerge\Dto\HeaderFooterMap;
use DocxMerge\Dto\HeaderFooterMapping;
use DocxMerge\Section\SectionPropertiesApplier;

describe('SectionPropertiesApplier', function (): void {
    describe('apply()', function (): void {
        it('updates headerReference rIds in intermediate sectPr using the map', function (): void {
            // Arrange
            $nsW = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
            $nsR = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

            $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<w:document xmlns:w="' . $nsW . '" xmlns:r="' . $nsR . '">'
                . '<w:body>'
                . '<w:p><w:pPr><w:sectPr>'
                . '<w:headerReference w:type="default" r:id="rId3"/>'
                . '</w:sectPr></w:pPr></w:p>'
                . '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/></w:sectPr>'
                . '</w:body>'
                . '</w:document>';

            $documentDom = createDomFromXml($documentXml);
            $xpath = createXpathWithNamespaces($documentDom);

            // Get the marker parent (w:body)
            $bodyNodes = $xpath->query('//w:body');
            assert($bodyNodes !== false && $bodyNodes->length > 0);
            $body = $bodyNodes->item(0);
            assert($body instanceof DOMElement);

            // Get the intermediate sectPr
            $intermediateSectPrNodes = $xpath->query('//w:p/w:pPr/w:sectPr');
            assert($intermediateSectPrNodes !== false && $intermediateSectPrNodes->length > 0);
            $intermediateSectPr = $intermediateSectPrNodes->item(0);
            assert($intermediateSectPr instanceof DOMElement);

            $extractedContent = new ExtractedContent(
                nodes: [],
                finalSectPr: null,
                intermediateSectPrElements: [$intermediateSectPr],
                sectionCount: 2,
            );

            $headerFooterMap = new HeaderFooterMap([
                'rId3' => new HeaderFooterMapping(
                    oldId: 'rId3',
                    newRelId: 'rId20',
                    oldTarget: 'header1.xml',
                    newFilename: 'header5.xml',
                    type: 'default',
                    isHeader: true,
                ),
            ]);

            $applier = new SectionPropertiesApplier();

            // Act
            $applier->apply($documentDom, $body, $extractedContent, $headerFooterMap);

            // Assert -- headerReference rId should be updated to rId20
            $updatedNodes = $xpath->query('//w:p/w:pPr/w:sectPr/w:headerReference/@r:id');
            assert($updatedNodes !== false && $updatedNodes->length > 0);
            $newRId = $updatedNodes->item(0);
            assert($newRId !== null);
            expect($newRId->nodeValue)->toBe('rId20');
        });

        it('updates footerReference rIds in intermediate sectPr using the map', function (): void {
            // Arrange
            $nsW = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
            $nsR = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

            $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<w:document xmlns:w="' . $nsW . '" xmlns:r="' . $nsR . '">'
                . '<w:body>'
                . '<w:p><w:pPr><w:sectPr>'
                . '<w:footerReference w:type="default" r:id="rId4"/>'
                . '</w:sectPr></w:pPr></w:p>'
                . '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/></w:sectPr>'
                . '</w:body>'
                . '</w:document>';

            $documentDom = createDomFromXml($documentXml);
            $xpath = createXpathWithNamespaces($documentDom);

            $bodyNodes = $xpath->query('//w:body');
            assert($bodyNodes !== false && $bodyNodes->length > 0);
            $body = $bodyNodes->item(0);
            assert($body instanceof DOMElement);

            $intermediateSectPrNodes = $xpath->query('//w:p/w:pPr/w:sectPr');
            assert($intermediateSectPrNodes !== false && $intermediateSectPrNodes->length > 0);
            $intermediateSectPr = $intermediateSectPrNodes->item(0);
            assert($intermediateSectPr instanceof DOMElement);

            $extractedContent = new ExtractedContent(
                nodes: [],
                finalSectPr: null,
                intermediateSectPrElements: [$intermediateSectPr],
                sectionCount: 2,
            );

            $headerFooterMap = new HeaderFooterMap([
                'rId4' => new HeaderFooterMapping(
                    oldId: 'rId4',
                    newRelId: 'rId25',
                    oldTarget: 'footer1.xml',
                    newFilename: 'footer3.xml',
                    type: 'default',
                    isHeader: false,
                ),
            ]);

            $applier = new SectionPropertiesApplier();

            // Act
            $applier->apply($documentDom, $body, $extractedContent, $headerFooterMap);

            // Assert
            $updatedNodes = $xpath->query('//w:p/w:pPr/w:sectPr/w:footerReference/@r:id');
            assert($updatedNodes !== false && $updatedNodes->length > 0);
            $newRId = $updatedNodes->item(0);
            assert($newRId !== null);
            expect($newRId->nodeValue)->toBe('rId25');
        });

        it('skips remapping when the rId is not in the header footer map', function (): void {
            // Arrange
            $nsW = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
            $nsR = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

            $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<w:document xmlns:w="' . $nsW . '" xmlns:r="' . $nsR . '">'
                . '<w:body>'
                . '<w:p><w:pPr><w:sectPr>'
                . '<w:headerReference w:type="default" r:id="rId99"/>'
                . '</w:sectPr></w:pPr></w:p>'
                . '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/></w:sectPr>'
                . '</w:body>'
                . '</w:document>';

            $documentDom = createDomFromXml($documentXml);
            $xpath = createXpathWithNamespaces($documentDom);

            $bodyNodes = $xpath->query('//w:body');
            assert($bodyNodes !== false && $bodyNodes->length > 0);
            $body = $bodyNodes->item(0);
            assert($body instanceof DOMElement);

            $intermediateSectPrNodes = $xpath->query('//w:p/w:pPr/w:sectPr');
            assert($intermediateSectPrNodes !== false && $intermediateSectPrNodes->length > 0);
            $intermediateSectPr = $intermediateSectPrNodes->item(0);
            assert($intermediateSectPr instanceof DOMElement);

            $extractedContent = new ExtractedContent(
                nodes: [],
                finalSectPr: null,
                intermediateSectPrElements: [$intermediateSectPr],
                sectionCount: 2,
            );

            // Map has a different rId than the one in the document
            $headerFooterMap = new HeaderFooterMap([
                'rId3' => new HeaderFooterMapping(
                    oldId: 'rId3',
                    newRelId: 'rId20',
                    oldTarget: 'header1.xml',
                    newFilename: 'header5.xml',
                    type: 'default',
                    isHeader: true,
                ),
            ]);

            $applier = new SectionPropertiesApplier();

            // Act
            $applier->apply($documentDom, $body, $extractedContent, $headerFooterMap);

            // Assert -- rId should remain unchanged since rId99 is not in the map
            $updatedNodes = $xpath->query('//w:p/w:pPr/w:sectPr/w:headerReference/@r:id');
            assert($updatedNodes !== false && $updatedNodes->length > 0);
            $rId = $updatedNodes->item(0);
            assert($rId !== null);
            expect($rId->nodeValue)->toBe('rId99');
        });

        it('skips non-element and non-reference children of sectPr', function (): void {
            // Arrange
            $nsW = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
            $nsR = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

            // sectPr has a pgSz child (not a headerReference/footerReference)
            // and a headerReference that should be remapped
            $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<w:document xmlns:w="' . $nsW . '" xmlns:r="' . $nsR . '">'
                . '<w:body>'
                . '<w:p><w:pPr><w:sectPr>'
                . '<w:pgSz w:w="12240" w:h="15840"/>'
                . '<w:headerReference w:type="default" r:id="rId3"/>'
                . '</w:sectPr></w:pPr></w:p>'
                . '<w:sectPr/>'
                . '</w:body>'
                . '</w:document>';

            $documentDom = createDomFromXml($documentXml);
            $xpath = createXpathWithNamespaces($documentDom);

            $bodyNodes = $xpath->query('//w:body');
            assert($bodyNodes !== false && $bodyNodes->length > 0);
            $body = $bodyNodes->item(0);
            assert($body instanceof DOMElement);

            $intermediateSectPrNodes = $xpath->query('//w:p/w:pPr/w:sectPr');
            assert($intermediateSectPrNodes !== false && $intermediateSectPrNodes->length > 0);
            $intermediateSectPr = $intermediateSectPrNodes->item(0);
            assert($intermediateSectPr instanceof DOMElement);

            $extractedContent = new ExtractedContent(
                nodes: [],
                finalSectPr: null,
                intermediateSectPrElements: [$intermediateSectPr],
                sectionCount: 2,
            );

            $headerFooterMap = new HeaderFooterMap([
                'rId3' => new HeaderFooterMapping(
                    oldId: 'rId3',
                    newRelId: 'rId20',
                    oldTarget: 'header1.xml',
                    newFilename: 'header5.xml',
                    type: 'default',
                    isHeader: true,
                ),
            ]);

            $applier = new SectionPropertiesApplier();

            // Act
            $applier->apply($documentDom, $body, $extractedContent, $headerFooterMap);

            // Assert -- pgSz is untouched, headerReference is remapped
            $headerRefs = $xpath->query('//w:p/w:pPr/w:sectPr/w:headerReference/@r:id');
            assert($headerRefs !== false && $headerRefs->length > 0);
            expect($headerRefs->item(0)->nodeValue)->toBe('rId20');

            // pgSz should still exist untouched
            $pgSz = $xpath->query('//w:p/w:pPr/w:sectPr/w:pgSz');
            expect($pgSz->length)->toBe(1);
        });

        it('does nothing when the header footer map is empty', function (): void {
            // Arrange
            $nsW = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
            $nsR = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

            $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<w:document xmlns:w="' . $nsW . '" xmlns:r="' . $nsR . '">'
                . '<w:body>'
                . '<w:p><w:r><w:t>Content</w:t></w:r></w:p>'
                . '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/></w:sectPr>'
                . '</w:body>'
                . '</w:document>';

            $documentDom = createDomFromXml($documentXml);
            $originalXml = $documentDom->saveXML();

            $xpath = createXpathWithNamespaces($documentDom);
            $bodyNodes = $xpath->query('//w:body');
            assert($bodyNodes !== false && $bodyNodes->length > 0);
            $body = $bodyNodes->item(0);
            assert($body instanceof DOMElement);

            $extractedContent = new ExtractedContent(
                nodes: [],
                finalSectPr: null,
                intermediateSectPrElements: [],
                sectionCount: 1,
            );

            $headerFooterMap = new HeaderFooterMap([]);
            $applier = new SectionPropertiesApplier();

            // Act
            $applier->apply($documentDom, $body, $extractedContent, $headerFooterMap);

            // Assert -- document should remain unchanged
            expect($documentDom->saveXML())->toBe($originalXml);
        });
    });
});
