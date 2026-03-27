<?php

declare(strict_types=1);

namespace DocxMerge\Style;

use DocxMerge\Dto\StyleMap;
use DocxMerge\Dto\StyleMapping;
use DocxMerge\Xml\XmlHelper;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Merges styles from a source DOCX into a target DOCX.
 *
 * Uses SHA-256 content hash comparison to detect identical styles in O(N+M)
 * time. Conflicting style IDs are renamed with sequential numeric suffixes
 * starting from 1000.
 *
 * @see StyleMergerInterface
 */
final class StyleMerger implements StyleMergerInterface
{
    /** Base ID for renamed styles that conflict with existing target styles. */
    private const RENAMED_ID_BASE = 1000;

    /**
     * Builds a mapping from source style IDs to target style IDs.
     *
     * For each target style, computes a normalized content hash. For each source
     * style, checks if an identical definition exists in the target by hash lookup.
     * When a style ID conflict exists with a different definition, generates a new
     * sequential numeric ID.
     *
     * @param DOMDocument $sourceStylesDom The source styles.xml DOM.
     * @param DOMDocument $targetStylesDom The target styles.xml DOM.
     *
     * @return StyleMap Mapping of old IDs to new IDs with metadata.
     */
    public function buildMap(
        DOMDocument $sourceStylesDom,
        DOMDocument $targetStylesDom,
    ): StyleMap {
        $localCounter = 0;

        $targetXpath = $this->createStyleXpath($targetStylesDom);
        $sourceXpath = $this->createStyleXpath($sourceStylesDom);

        // --- Phase 1: Index target styles by ID and content hash ---
        $targetHashById = $this->buildHashById($targetXpath);
        $targetIdSet = $this->buildIdSet($targetXpath);

        // --- Phase 2: Map each source style ---
        /** @var array<string, StyleMapping> $mappings */
        $mappings = [];

        $sourceStyles = $sourceXpath->query('//w:style');
        if ($sourceStyles === false) {
            return new StyleMap($mappings);
        }

        foreach ($sourceStyles as $styleNode) {
            if (!$styleNode instanceof DOMElement) {
                continue;
            }

            $oldId = $styleNode->getAttributeNS(XmlHelper::NS_W, 'styleId');
            if ($oldId === '') {
                continue;
            }

            $type = $styleNode->getAttributeNS(XmlHelper::NS_W, 'type');
            $sourceHash = $this->computeContentHash($styleNode);

            if (isset($targetIdSet[$oldId])) {
                // ID exists in target -- compare content hashes.
                if (isset($targetHashById[$oldId]) && $targetHashById[$oldId] === $sourceHash) {
                    // Identical definition -- reuse the existing target style.
                    $mappings[$oldId] = new StyleMapping(
                        oldId: $oldId,
                        newId: $oldId,
                        type: $type,
                        node: $styleNode,
                        reuseExisting: true,
                    );
                } else {
                    // Different definition with same ID -- rename to avoid conflict.
                    $newId = $this->generateUniqueId($targetIdSet, $localCounter);
                    $mappings[$oldId] = new StyleMapping(
                        oldId: $oldId,
                        newId: $newId,
                        type: $type,
                        node: $styleNode,
                        reuseExisting: false,
                    );
                    // Track the new ID to prevent future collisions within this pass.
                    $targetIdSet[$newId] = true;
                }
            } else {
                // New style -- keep original ID.
                $mappings[$oldId] = new StyleMapping(
                    oldId: $oldId,
                    newId: $oldId,
                    type: $type,
                    node: $styleNode,
                    reuseExisting: false,
                );
                $targetIdSet[$oldId] = true;
            }
        }

        return new StyleMap($mappings);
    }

    /**
     * Merges mapped styles into the target DOM.
     *
     * Only imports styles that are new or renamed (not reused). Each imported
     * style node is deep-cloned into the target document and its styleId
     * attribute is updated to the mapped new ID.
     *
     * @param DOMDocument $targetStylesDom The target styles.xml DOM (modified in place).
     * @param StyleMap    $styleMap        The computed style map.
     *
     * @return int Number of styles actually imported.
     */
    public function merge(
        DOMDocument $targetStylesDom,
        StyleMap $styleMap,
    ): int {
        $stylesToImport = $styleMap->getStylesToImport();

        if (count($stylesToImport) === 0) {
            return 0;
        }

        $targetRoot = $targetStylesDom->documentElement;
        if (!$targetRoot instanceof DOMElement) {
            return 0;
        }

        $imported = 0;

        foreach ($stylesToImport as $mapping) {
            $importedNode = $targetStylesDom->importNode($mapping->node, true);

            if ($importedNode instanceof DOMElement) {
                // Update the styleId to the mapped new ID.
                $importedNode->setAttributeNS(XmlHelper::NS_W, 'w:styleId', $mapping->newId);
            }

            $targetRoot->appendChild($importedNode);
            $imported++;
        }

        return $imported;
    }

    /**
     * Creates a DOMXPath with the WordprocessingML namespace registered.
     *
     * @param DOMDocument $dom The document to query.
     *
     * @return DOMXPath The configured XPath instance.
     */
    private function createStyleXpath(DOMDocument $dom): DOMXPath
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', XmlHelper::NS_W);

        return $xpath;
    }

    /**
     * Builds a styleId-to-hash index from all styles in the target.
     *
     * @param DOMXPath $xpath The target document XPath.
     *
     * @return array<string, string> StyleId => content hash.
     */
    private function buildHashById(DOMXPath $xpath): array
    {
        $index = [];
        $styles = $xpath->query('//w:style');

        if ($styles === false) {
            return $index;
        }

        foreach ($styles as $styleNode) {
            if (!$styleNode instanceof DOMElement) {
                continue;
            }

            $styleId = $styleNode->getAttributeNS(XmlHelper::NS_W, 'styleId');
            if ($styleId === '') {
                continue;
            }

            $index[$styleId] = $this->computeContentHash($styleNode);
        }

        return $index;
    }

    /**
     * Builds a set of existing style IDs for collision detection.
     *
     * @param DOMXPath $xpath The target document XPath.
     *
     * @return array<string, true> StyleId => true.
     */
    private function buildIdSet(DOMXPath $xpath): array
    {
        $set = [];
        $styles = $xpath->query('//w:style');

        if ($styles === false) {
            return $set;
        }

        foreach ($styles as $styleNode) {
            if (!$styleNode instanceof DOMElement) {
                continue;
            }

            $styleId = $styleNode->getAttributeNS(XmlHelper::NS_W, 'styleId');
            if ($styleId !== '') {
                $set[$styleId] = true;
            }
        }

        return $set;
    }

    /**
     * Computes a SHA-256 content hash of a style element after normalization.
     *
     * Removes attributes and child elements that do not affect the visual
     * definition (styleId, customStyle, default, name, aliases) before hashing.
     * This allows detecting equivalent styles that differ only in metadata.
     *
     * @param DOMElement $styleNode The w:style element to hash.
     *
     * @return string The hex-encoded SHA-256 hash.
     */
    private function computeContentHash(DOMElement $styleNode): string
    {
        // Clone to avoid mutating the original DOM.
        $clone = $styleNode->cloneNode(true);

        if (!$clone instanceof DOMElement) {
            return hash('sha256', '');
        }

        // Remove metadata attributes that do not affect visual definition.
        $clone->removeAttributeNS(XmlHelper::NS_W, 'styleId');
        $clone->removeAttributeNS(XmlHelper::NS_W, 'customStyle');
        $clone->removeAttributeNS(XmlHelper::NS_W, 'default');

        // Remove metadata child elements (w:name, w:aliases).
        $this->removeChildByLocalName($clone, 'name');
        $this->removeChildByLocalName($clone, 'aliases');

        $ownerDoc = $clone->ownerDocument;
        $xml = $ownerDoc instanceof DOMDocument
            ? $ownerDoc->saveXML($clone)
            : '';

        if ($xml === false) {
            $xml = '';
        }

        // Normalize whitespace for consistent hashing.
        $normalized = preg_replace('/\s+/', ' ', trim($xml));

        return hash('sha256', $normalized ?? '');
    }

    /**
     * Removes child elements with the given local name from a parent element.
     *
     * Only removes direct children in the WordprocessingML namespace.
     *
     * @param DOMElement $parent    The parent element.
     * @param string     $localName The local name to match (e.g., 'name').
     */
    private function removeChildByLocalName(DOMElement $parent, string $localName): void
    {
        $toRemove = [];

        foreach ($parent->childNodes as $child) {
            if (
                $child instanceof DOMElement
                && $child->localName === $localName
                && $child->namespaceURI === XmlHelper::NS_W
            ) {
                $toRemove[] = $child;
            }
        }

        foreach ($toRemove as $node) {
            $parent->removeChild($node);
        }
    }

    /**
     * Generates a unique style ID that does not collide with existing IDs.
     *
     * Uses a sequential numeric suffix starting from RENAMED_ID_BASE (1000).
     *
     * @param array<string, true> $existingIds Set of IDs already in use.
     * @param int                 $counter     Counter passed by reference, incremented on each call.
     *
     * @return string A new unique style ID (e.g., "Style1000", "Style1001").
     */
    private function generateUniqueId(array $existingIds, int &$counter): string
    {
        do {
            $candidate = 'Style' . (self::RENAMED_ID_BASE + $counter);
            $counter++;
        } while (isset($existingIds[$candidate]));

        return $candidate;
    }
}
