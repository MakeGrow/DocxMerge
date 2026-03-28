<?php

declare(strict_types=1);

/**
 * Fixture Generator for DocxMerge Integration Tests.
 *
 * Creates minimal DOCX files programmatically using ZipArchive and XML strings.
 * Each fixture is the smallest valid DOCX that exercises a specific merge scenario.
 *
 * Usage: php tests/Integration/Fixtures/create-fixtures.php
 *
 * Generated fixtures:
 *   template-simple.docx       -- Single ${CONTENT} marker
 *   source-simple.docx         -- Two plain paragraphs
 *   template-multi.docx        -- ${FIRST} and ${SECOND} markers
 *   source-a.docx              -- Source A content for multi-merge
 *   source-b.docx              -- Source B content for multi-merge
 *   source-with-images.docx    -- Paragraph with inline image (1x1 PNG)
 *   source-with-lists.docx     -- Paragraphs with numbered list references
 *   source-with-headers.docx   -- Document with header1.xml
 *   source-multi-section.docx  -- Three sections with intermediate sectPr
 *   template-fragmented.docx   -- ${CONTENT} split across 3 w:t elements
 *   source-empty.docx          -- Only sectPr, no content paragraphs
 *   source-with-photo.docx     -- Paragraph with image using non-standard filename (photo.png)
 *   template-reprocessing.docx -- Two markers ${FIRST} and ${SECOND} for multi-pass reprocessing
 *   template-custom-pattern.docx -- {{CONTENT}} marker with double-brace delimiters
 *   source-reprocessing.docx   -- Identifiable content for reprocessing tests
 */

$fixtureDir = __DIR__;

// -- Shared XML fragments --------------------------------------------------

$nsW = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
$nsR = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
$nsMc = 'http://schemas.openxmlformats.org/markup-compatibility/2006';
$nsWp = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';
$nsA = 'http://schemas.openxmlformats.org/drawingml/2006/main';
$nsPic = 'http://schemas.openxmlformats.org/drawingml/2006/picture';
$nsRel = 'http://schemas.openxmlformats.org/package/2006/relationships';
$nsCt = 'http://schemas.openxmlformats.org/package/2006/content-types';

/**
 * Returns a minimal [Content_Types].xml string.
 *
 * @param list<array{partName: string, contentType: string}> $overrides Additional Override entries.
 * @param list<array{extension: string, contentType: string}> $defaults Additional Default entries.
 */
function buildContentTypes(array $overrides = [], array $defaults = []): string
{
    $ns = 'http://schemas.openxmlformats.org/package/2006/content-types';

    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<Types xmlns="' . $ns . '">';
    $xml .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
    $xml .= '<Default Extension="xml" ContentType="application/xml"/>';

    foreach ($defaults as $default) {
        $xml .= '<Default Extension="' . $default['extension'] . '" ContentType="' . $default['contentType'] . '"/>';
    }

    $xml .= '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>';
    $xml .= '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>';

    foreach ($overrides as $override) {
        $xml .= '<Override PartName="' . $override['partName'] . '" ContentType="' . $override['contentType'] . '"/>';
    }

    $xml .= '</Types>';

    return $xml;
}

/**
 * Returns a minimal _rels/.rels pointing to word/document.xml.
 */
function buildRootRels(): string
{
    $ns = 'http://schemas.openxmlformats.org/package/2006/relationships';
    $type = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument';

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="' . $ns . '">'
        . '<Relationship Id="rId1" Type="' . $type . '" Target="word/document.xml"/>'
        . '</Relationships>';
}

/**
 * Returns a minimal word/_rels/document.xml.rels.
 *
 * @param list<array{id: string, type: string, target: string, targetMode?: string}> $extraRels Additional relationships.
 */
function buildDocumentRels(array $extraRels = []): string
{
    $ns = 'http://schemas.openxmlformats.org/package/2006/relationships';
    $stylesType = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles';

    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<Relationships xmlns="' . $ns . '">';
    $xml .= '<Relationship Id="rId1" Type="' . $stylesType . '" Target="styles.xml"/>';

    foreach ($extraRels as $rel) {
        $xml .= '<Relationship Id="' . $rel['id'] . '" Type="' . $rel['type'] . '" Target="' . $rel['target'] . '"';
        if (isset($rel['targetMode'])) {
            $xml .= ' TargetMode="' . $rel['targetMode'] . '"';
        }
        $xml .= '/>';
    }

    $xml .= '</Relationships>';

    return $xml;
}

/**
 * Returns a minimal word/styles.xml with a Normal style.
 */
function buildStyles(): string
{
    $nsW = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:styles xmlns:w="' . $nsW . '">'
        . '<w:docDefaults><w:rPrDefault><w:rPr><w:sz w:val="22"/></w:rPr></w:rPrDefault><w:pPrDefault/></w:docDefaults>'
        . '<w:style w:type="paragraph" w:styleId="Normal" w:default="1">'
        . '<w:name w:val="Normal"/>'
        . '</w:style>'
        . '</w:styles>';
}

/**
 * Wraps body content in a minimal w:document/w:body envelope.
 *
 * @param string $bodyContent XML string for body children (paragraphs, tables, sectPr).
 */
function buildDocumentXml(string $bodyContent): string
{
    $nsW = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $nsR = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    $nsMc = 'http://schemas.openxmlformats.org/markup-compatibility/2006';
    $nsWp = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';
    $nsA = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    $nsPic = 'http://schemas.openxmlformats.org/drawingml/2006/picture';

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:w="' . $nsW . '"'
        . ' xmlns:r="' . $nsR . '"'
        . ' xmlns:mc="' . $nsMc . '"'
        . ' xmlns:wp="' . $nsWp . '"'
        . ' xmlns:a="' . $nsA . '"'
        . ' xmlns:pic="' . $nsPic . '"'
        . ' mc:Ignorable="">'
        . '<w:body>'
        . $bodyContent
        . '</w:body>'
        . '</w:document>';
}

/** Standard final sectPr for letter-sized pages. */
function finalSectPr(): string
{
    return '<w:sectPr>'
        . '<w:pgSz w:w="12240" w:h="15840"/>'
        . '<w:pgMar w:top="1440" w:right="1800" w:bottom="1440" w:left="1800" w:header="720" w:footer="720" w:gutter="0"/>'
        . '</w:sectPr>';
}

/**
 * Creates a DOCX ZIP archive with the given parts.
 *
 * @param string $path Output file path.
 * @param array<string, string> $parts Map of ZIP entry name to content.
 */
function createDocx(string $path, array $parts): void
{
    if (file_exists($path)) {
        unlink($path);
    }

    $zip = new ZipArchive();
    $result = $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($result !== true) {
        throw new RuntimeException("Failed to create ZIP at {$path}: error code {$result}");
    }

    foreach ($parts as $name => $content) {
        $zip->addFromString($name, $content);
    }

    $zip->close();
}

// -- 1. template-simple.docx ------------------------------------------------
// Template with a single ${CONTENT} marker and a final sectPr.

$body = '<w:p><w:r><w:t>${CONTENT}</w:t></w:r></w:p>' . finalSectPr();

createDocx($fixtureDir . '/template-simple.docx', [
    '[Content_Types].xml' => buildContentTypes(),
    '_rels/.rels' => buildRootRels(),
    'word/document.xml' => buildDocumentXml($body),
    'word/_rels/document.xml.rels' => buildDocumentRels(),
    'word/styles.xml' => buildStyles(),
]);

echo "Created: template-simple.docx\n";

// -- 2. source-simple.docx --------------------------------------------------
// Two paragraphs of plain text and a final sectPr.

$body = '<w:p><w:r><w:t>Hello from source</w:t></w:r></w:p>'
    . '<w:p><w:r><w:t>Second paragraph</w:t></w:r></w:p>'
    . finalSectPr();

createDocx($fixtureDir . '/source-simple.docx', [
    '[Content_Types].xml' => buildContentTypes(),
    '_rels/.rels' => buildRootRels(),
    'word/document.xml' => buildDocumentXml($body),
    'word/_rels/document.xml.rels' => buildDocumentRels(),
    'word/styles.xml' => buildStyles(),
]);

echo "Created: source-simple.docx\n";

// -- 3. template-multi.docx -------------------------------------------------
// Two markers: ${FIRST} and ${SECOND} in separate paragraphs.

$body = '<w:p><w:r><w:t>${FIRST}</w:t></w:r></w:p>'
    . '<w:p><w:r><w:t>${SECOND}</w:t></w:r></w:p>'
    . finalSectPr();

createDocx($fixtureDir . '/template-multi.docx', [
    '[Content_Types].xml' => buildContentTypes(),
    '_rels/.rels' => buildRootRels(),
    'word/document.xml' => buildDocumentXml($body),
    'word/_rels/document.xml.rels' => buildDocumentRels(),
    'word/styles.xml' => buildStyles(),
]);

echo "Created: template-multi.docx\n";

// -- 4. source-a.docx -------------------------------------------------------
// Source A with identifiable content for multi-merge testing.

$body = '<w:p><w:r><w:t>Content from source A</w:t></w:r></w:p>' . finalSectPr();

createDocx($fixtureDir . '/source-a.docx', [
    '[Content_Types].xml' => buildContentTypes(),
    '_rels/.rels' => buildRootRels(),
    'word/document.xml' => buildDocumentXml($body),
    'word/_rels/document.xml.rels' => buildDocumentRels(),
    'word/styles.xml' => buildStyles(),
]);

echo "Created: source-a.docx\n";

// -- 5. source-b.docx -------------------------------------------------------
// Source B with identifiable content for multi-merge testing.

$body = '<w:p><w:r><w:t>Content from source B</w:t></w:r></w:p>' . finalSectPr();

createDocx($fixtureDir . '/source-b.docx', [
    '[Content_Types].xml' => buildContentTypes(),
    '_rels/.rels' => buildRootRels(),
    'word/document.xml' => buildDocumentXml($body),
    'word/_rels/document.xml.rels' => buildDocumentRels(),
    'word/styles.xml' => buildStyles(),
]);

echo "Created: source-b.docx\n";

// -- 6. source-with-images.docx ----------------------------------------------
// Paragraph with an inline drawing referencing rId2 -> media/image1.png.
// Includes a 1x1 pixel transparent PNG in word/media/.

$imageDrawing = '<w:p><w:r><w:drawing>'
    . '<wp:inline distT="0" distB="0" distL="0" distR="0">'
    . '<wp:extent cx="914400" cy="914400"/>'
    . '<wp:effectExtent l="0" t="0" r="0" b="0"/>'
    . '<wp:docPr id="1" name="Image 1"/>'
    . '<wp:cNvGraphicFramePr>'
    . '<a:graphicFrameLocks noChangeAspect="1"/>'
    . '</wp:cNvGraphicFramePr>'
    . '<a:graphic>'
    . '<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
    . '<pic:pic>'
    . '<pic:nvPicPr><pic:cNvPr id="0" name="image1.png"/><pic:cNvPicPr/></pic:nvPicPr>'
    . '<pic:blipFill><a:blip r:embed="rId2"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
    . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="914400" cy="914400"/></a:xfrm>'
    . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
    . '</pic:pic>'
    . '</a:graphicData>'
    . '</a:graphic>'
    . '</wp:inline>'
    . '</w:drawing></w:r></w:p>';

$body = $imageDrawing . finalSectPr();

// 1x1 pixel transparent PNG (minimum valid PNG)
$pngData = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg=='
);

$imageType = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';

createDocx($fixtureDir . '/source-with-images.docx', [
    '[Content_Types].xml' => buildContentTypes(
        defaults: [['extension' => 'png', 'contentType' => 'image/png']],
    ),
    '_rels/.rels' => buildRootRels(),
    'word/document.xml' => buildDocumentXml($body),
    'word/_rels/document.xml.rels' => buildDocumentRels([
        ['id' => 'rId2', 'type' => $imageType, 'target' => 'media/image1.png'],
    ]),
    'word/styles.xml' => buildStyles(),
    'word/media/image1.png' => $pngData,
]);

echo "Created: source-with-images.docx\n";

// -- 7. source-with-lists.docx -----------------------------------------------
// Two paragraphs with w:numPr referencing numId="1". Includes numbering.xml
// with one abstractNum and one num definition.

$listParagraphs = '<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr>'
    . '<w:r><w:t>First item</w:t></w:r></w:p>'
    . '<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr>'
    . '<w:r><w:t>Second item</w:t></w:r></w:p>';

$body = $listParagraphs . finalSectPr();

$numberingXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<w:numbering xmlns:w="' . $nsW . '">'
    . '<w:abstractNum w:abstractNumId="0">'
    . '<w:multiLevelType w:val="hybridMultilevel"/>'
    . '<w:lvl w:ilvl="0">'
    . '<w:start w:val="1"/>'
    . '<w:numFmt w:val="decimal"/>'
    . '<w:lvlText w:val="%1."/>'
    . '<w:lvlJc w:val="left"/>'
    . '<w:pPr><w:ind w:left="720" w:hanging="360"/></w:pPr>'
    . '</w:lvl>'
    . '</w:abstractNum>'
    . '<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num>'
    . '</w:numbering>';

$numberingType = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering';

createDocx($fixtureDir . '/source-with-lists.docx', [
    '[Content_Types].xml' => buildContentTypes([
        ['partName' => '/word/numbering.xml', 'contentType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml'],
    ]),
    '_rels/.rels' => buildRootRels(),
    'word/document.xml' => buildDocumentXml($body),
    'word/_rels/document.xml.rels' => buildDocumentRels([
        ['id' => 'rId2', 'type' => $numberingType, 'target' => 'numbering.xml'],
    ]),
    'word/styles.xml' => buildStyles(),
    'word/numbering.xml' => $numberingXml,
]);

echo "Created: source-with-lists.docx\n";

// -- 8. source-with-headers.docx ---------------------------------------------
// Document with a header1.xml referenced from sectPr via rId3.

$headerXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<w:hdr xmlns:w="' . $nsW . '" xmlns:r="' . $nsR . '">'
    . '<w:p><w:pPr><w:pStyle w:val="Header"/></w:pPr>'
    . '<w:r><w:t>Document Header</w:t></w:r></w:p>'
    . '</w:hdr>';

$body = '<w:p><w:r><w:t>Content with header</w:t></w:r></w:p>'
    . '<w:sectPr>'
    . '<w:headerReference w:type="default" r:id="rId2"/>'
    . '<w:pgSz w:w="12240" w:h="15840"/>'
    . '<w:pgMar w:top="1440" w:right="1800" w:bottom="1440" w:left="1800" w:header="720" w:footer="720" w:gutter="0"/>'
    . '</w:sectPr>';

$headerType = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/header';

createDocx($fixtureDir . '/source-with-headers.docx', [
    '[Content_Types].xml' => buildContentTypes([
        ['partName' => '/word/header1.xml', 'contentType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml'],
    ]),
    '_rels/.rels' => buildRootRels(),
    'word/document.xml' => buildDocumentXml($body),
    'word/_rels/document.xml.rels' => buildDocumentRels([
        ['id' => 'rId2', 'type' => $headerType, 'target' => 'header1.xml'],
    ]),
    'word/styles.xml' => buildStyles(),
    'word/header1.xml' => $headerXml,
]);

echo "Created: source-with-headers.docx\n";

// -- 9. source-multi-section.docx --------------------------------------------
// Three sections: two intermediate sectPr in pPr, one final sectPr.

$body = '<w:p><w:r><w:t>Section 1 content</w:t></w:r></w:p>'
    // Intermediate sectPr ending section 1
    . '<w:p><w:pPr><w:sectPr>'
    . '<w:pgSz w:w="12240" w:h="15840"/>'
    . '<w:pgMar w:top="1440" w:right="1800" w:bottom="1440" w:left="1800" w:header="720" w:footer="720" w:gutter="0"/>'
    . '</w:sectPr></w:pPr></w:p>'
    // Section 2
    . '<w:p><w:r><w:t>Section 2 content</w:t></w:r></w:p>'
    // Intermediate sectPr ending section 2
    . '<w:p><w:pPr><w:sectPr>'
    . '<w:pgSz w:w="15840" w:h="12240" w:orient="landscape"/>'
    . '<w:pgMar w:top="1440" w:right="1800" w:bottom="1440" w:left="1800" w:header="720" w:footer="720" w:gutter="0"/>'
    . '</w:sectPr></w:pPr></w:p>'
    // Section 3
    . '<w:p><w:r><w:t>Section 3 content</w:t></w:r></w:p>'
    // Final sectPr
    . finalSectPr();

createDocx($fixtureDir . '/source-multi-section.docx', [
    '[Content_Types].xml' => buildContentTypes(),
    '_rels/.rels' => buildRootRels(),
    'word/document.xml' => buildDocumentXml($body),
    'word/_rels/document.xml.rels' => buildDocumentRels(),
    'word/styles.xml' => buildStyles(),
]);

echo "Created: source-multi-section.docx\n";

// -- 10. template-fragmented.docx --------------------------------------------
// Marker ${CONTENT} split across 3 w:t elements: "$", "{CONTENT", "}".
// This simulates Word's run-splitting behavior for styled text.

$body = '<w:p>'
    . '<w:r><w:t>$</w:t></w:r>'
    . '<w:r><w:t>{CONTENT</w:t></w:r>'
    . '<w:r><w:t>}</w:t></w:r>'
    . '</w:p>'
    . finalSectPr();

createDocx($fixtureDir . '/template-fragmented.docx', [
    '[Content_Types].xml' => buildContentTypes(),
    '_rels/.rels' => buildRootRels(),
    'word/document.xml' => buildDocumentXml($body),
    'word/_rels/document.xml.rels' => buildDocumentRels(),
    'word/styles.xml' => buildStyles(),
]);

echo "Created: template-fragmented.docx\n";

// -- 11. source-empty.docx ---------------------------------------------------
// Only a sectPr, no content paragraphs with text.

$body = finalSectPr();

createDocx($fixtureDir . '/source-empty.docx', [
    '[Content_Types].xml' => buildContentTypes(),
    '_rels/.rels' => buildRootRels(),
    'word/document.xml' => buildDocumentXml($body),
    'word/_rels/document.xml.rels' => buildDocumentRels(),
    'word/styles.xml' => buildStyles(),
]);

echo "Created: source-empty.docx\n";

// -- 12. source-with-photo.docx -----------------------------------------------
// Paragraph with an inline drawing referencing rId2 -> media/photo.png.
// Uses a non-standard filename to test media target remapping.

$photoDrawing = '<w:p><w:r><w:drawing>'
    . '<wp:inline distT="0" distB="0" distL="0" distR="0">'
    . '<wp:extent cx="914400" cy="914400"/>'
    . '<wp:effectExtent l="0" t="0" r="0" b="0"/>'
    . '<wp:docPr id="1" name="Photo 1"/>'
    . '<wp:cNvGraphicFramePr>'
    . '<a:graphicFrameLocks noChangeAspect="1"/>'
    . '</wp:cNvGraphicFramePr>'
    . '<a:graphic>'
    . '<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
    . '<pic:pic>'
    . '<pic:nvPicPr><pic:cNvPr id="0" name="photo.png"/><pic:cNvPicPr/></pic:nvPicPr>'
    . '<pic:blipFill><a:blip r:embed="rId2"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
    . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="914400" cy="914400"/></a:xfrm>'
    . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
    . '</pic:pic>'
    . '</a:graphicData>'
    . '</a:graphic>'
    . '</wp:inline>'
    . '</w:drawing></w:r></w:p>';

$photoBody = $photoDrawing . finalSectPr();

createDocx($fixtureDir . '/source-with-photo.docx', [
    '[Content_Types].xml' => buildContentTypes(
        defaults: [['extension' => 'png', 'contentType' => 'image/png']],
    ),
    '_rels/.rels' => buildRootRels(),
    'word/document.xml' => buildDocumentXml($photoBody),
    'word/_rels/document.xml.rels' => buildDocumentRels([
        ['id' => 'rId2', 'type' => $imageType, 'target' => 'media/photo.png'],
    ]),
    'word/styles.xml' => buildStyles(),
    'word/media/photo.png' => $pngData,
]);

echo "Created: source-with-photo.docx\n";

// -- 13. template-reprocessing.docx ------------------------------------------
// Template with two markers for multi-pass reprocessing testing.
// Pass 1 replaces ${FIRST}, pass 2 replaces ${SECOND}.

$body = '<w:p><w:r><w:t>Before first marker</w:t></w:r></w:p>'
    . '<w:p><w:r><w:t>${FIRST}</w:t></w:r></w:p>'
    . '<w:p><w:r><w:t>Between markers</w:t></w:r></w:p>'
    . '<w:p><w:r><w:t>${SECOND}</w:t></w:r></w:p>'
    . '<w:p><w:r><w:t>After second marker</w:t></w:r></w:p>'
    . finalSectPr();

createDocx($fixtureDir . '/template-reprocessing.docx', [
    '[Content_Types].xml' => buildContentTypes(),
    '_rels/.rels' => buildRootRels(),
    'word/document.xml' => buildDocumentXml($body),
    'word/_rels/document.xml.rels' => buildDocumentRels(),
    'word/styles.xml' => buildStyles(),
]);

echo "Created: template-reprocessing.docx\n";

// -- 14. template-custom-pattern.docx ----------------------------------------
// Template with {{CONTENT}} marker using double-brace delimiters.
// Used for end-to-end custom marker pattern integration testing.

$body = '<w:p><w:r><w:t>{{CONTENT}}</w:t></w:r></w:p>' . finalSectPr();

createDocx($fixtureDir . '/template-custom-pattern.docx', [
    '[Content_Types].xml' => buildContentTypes(),
    '_rels/.rels' => buildRootRels(),
    'word/document.xml' => buildDocumentXml($body),
    'word/_rels/document.xml.rels' => buildDocumentRels(),
    'word/styles.xml' => buildStyles(),
]);

echo "Created: template-custom-pattern.docx\n";

// -- 15. source-reprocessing.docx --------------------------------------------
// Source with identifiable content for reprocessing tests.

$body = '<w:p><w:r><w:t>Reprocessing source content</w:t></w:r></w:p>' . finalSectPr();

createDocx($fixtureDir . '/source-reprocessing.docx', [
    '[Content_Types].xml' => buildContentTypes(),
    '_rels/.rels' => buildRootRels(),
    'word/document.xml' => buildDocumentXml($body),
    'word/_rels/document.xml.rels' => buildDocumentRels(),
    'word/styles.xml' => buildStyles(),
]);

echo "Created: source-reprocessing.docx\n";

echo "\nAll fixtures created successfully.\n";
