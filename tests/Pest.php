<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Helpers
|--------------------------------------------------------------------------
|
| Global helper functions available to all test files.
|
*/

/**
 * Returns the absolute path to a test fixture file.
 *
 * @param string $name Filename relative to tests/Integration/Fixtures/.
 *
 * @return string Absolute path to the fixture.
 *
 * @throws RuntimeException If the fixture file does not exist.
 */
function fixture(string $name): string
{
    $path = __DIR__ . '/Integration/Fixtures/' . $name;

    if (!file_exists($path)) {
        throw new RuntimeException("Fixture not found: {$path}");
    }

    return $path;
}

/**
 * Creates a DOMDocument from an XML string for unit testing.
 *
 * Configures preserveWhiteSpace=true and formatOutput=false to match
 * the production XmlHelper behavior.
 *
 * @param string $xml The XML string to parse.
 *
 * @return DOMDocument The parsed DOM document.
 *
 * @throws RuntimeException If the XML cannot be parsed.
 */
function createDomFromXml(string $xml): DOMDocument
{
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = true;
    $dom->formatOutput = false;

    $previous = libxml_use_internal_errors(true);
    $result = $dom->loadXML($xml, LIBXML_NONET);
    $errors = libxml_get_errors();
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if ($result === false || count($errors) > 0) {
        $message = count($errors) > 0
            ? $errors[0]->message
            : 'Unknown XML parse error';
        throw new RuntimeException("Failed to parse XML: {$message}");
    }

    return $dom;
}

/**
 * Creates a DOMXPath with standard OOXML namespaces registered.
 *
 * @param DOMDocument $dom The document to create an XPath for.
 *
 * @return DOMXPath The configured XPath instance.
 */
function createXpathWithNamespaces(DOMDocument $dom): DOMXPath
{
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
    $xpath->registerNamespace('wp', 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing');
    $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
    $xpath->registerNamespace('pic', 'http://schemas.openxmlformats.org/drawingml/2006/picture');
    $xpath->registerNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');
    $xpath->registerNamespace('ct', 'http://schemas.openxmlformats.org/package/2006/content-types');
    $xpath->registerNamespace('v', 'urn:schemas-microsoft-com:vml');

    return $xpath;
}
