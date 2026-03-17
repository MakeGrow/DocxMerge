<?php

declare(strict_types=1);

namespace DocxMerge\Xml;

use DocxMerge\Exception\XmlParseException;
use DOMDocument;
use DOMXPath;

/**
 * Provides safe DOM creation, XPath factory, and whitespace preservation.
 *
 * Centralizes XML parsing configuration to ensure consistent behavior
 * across all services that manipulate OOXML document parts.
 */
final class XmlHelper
{
    /** WordprocessingML main namespace URI. */
    private const NS_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** Office relationships namespace URI. */
    private const NS_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /** DrawingML main namespace URI. */
    private const NS_A = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    /** DrawingML picture namespace URI. */
    private const NS_PIC = 'http://schemas.openxmlformats.org/drawingml/2006/picture';

    /** WordprocessingDrawing namespace URI. */
    private const NS_WP = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';

    /** Markup Compatibility namespace URI. */
    private const NS_MC = 'http://schemas.openxmlformats.org/markup-compatibility/2006';

    /** Word 2010 extensions namespace URI. */
    private const NS_W14 = 'http://schemas.microsoft.com/office/word/2010/wordml';

    /** Package relationships namespace URI. */
    private const NS_REL = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /** Content types namespace URI. */
    private const NS_CT = 'http://schemas.openxmlformats.org/package/2006/content-types';

    /** VML namespace URI. */
    private const NS_V = 'urn:schemas-microsoft-com:vml';

    /**
     * Creates a DOMDocument from an XML string with safe defaults.
     *
     * Uses LIBXML_NONET to prevent external entity loading and LIBXML_PARSEHUGE
     * to support large documents. Collects libxml errors and throws on failure.
     *
     * @param string $xml The raw XML string to parse.
     *
     * @return DOMDocument The parsed DOM with preserveWhiteSpace=true and formatOutput=false.
     *
     * @throws XmlParseException If the XML cannot be parsed.
     */
    public function createDom(string $xml): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;

        // Save and override the internal error state to collect parsing errors
        // without interfering with the caller's error handling.
        $previousUseErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $result = $dom->loadXML($xml, LIBXML_NONET | LIBXML_PARSEHUGE);

        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);

        if ($result === false || count($errors) > 0) {
            $message = count($errors) > 0
                ? trim($errors[0]->message)
                : 'Unknown XML parsing error';

            throw new XmlParseException($message);
        }

        return $dom;
    }

    /**
     * Creates a DOMXPath instance with all OOXML namespaces registered.
     *
     * Registers prefixes w, r, a, pic, wp, mc, w14, rel, ct, and v so that
     * XPath queries across any document part work without additional setup.
     *
     * @param DOMDocument $dom The DOM to query.
     *
     * @return DOMXPath The XPath instance with OOXML namespaces registered.
     */
    public function createXpath(DOMDocument $dom): DOMXPath
    {
        $xpath = new DOMXPath($dom);

        $xpath->registerNamespace('w', self::NS_W);
        $xpath->registerNamespace('r', self::NS_R);
        $xpath->registerNamespace('a', self::NS_A);
        $xpath->registerNamespace('pic', self::NS_PIC);
        $xpath->registerNamespace('wp', self::NS_WP);
        $xpath->registerNamespace('mc', self::NS_MC);
        $xpath->registerNamespace('w14', self::NS_W14);
        $xpath->registerNamespace('rel', self::NS_REL);
        $xpath->registerNamespace('ct', self::NS_CT);
        $xpath->registerNamespace('v', self::NS_V);

        return $xpath;
    }

    /**
     * Adds xml:space="preserve" to w:t elements with leading or trailing whitespace.
     *
     * Without this attribute, Word silently strips surrounding spaces from text
     * runs, causing formatting loss in merged documents.
     *
     * @param DOMDocument $dom The document to process.
     */
    public function preserveTextSpaces(DOMDocument $dom): void
    {
        $xpath = $this->createXpath($dom);
        $textNodes = $xpath->query('//w:t');

        if ($textNodes === false) {
            return;
        }

        foreach ($textNodes as $textNode) {
            if (!$textNode instanceof \DOMElement) {
                continue;
            }

            $value = $textNode->nodeValue ?? '';

            // Check if text has leading or trailing whitespace that Word would strip.
            if ($value !== ltrim($value) || $value !== rtrim($value)) {
                $textNode->setAttribute('xml:space', 'preserve');
            }
        }
    }
}
