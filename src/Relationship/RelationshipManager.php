<?php

declare(strict_types=1);

namespace DocxMerge\Relationship;

use DocxMerge\Dto\RelationshipMap;
use DocxMerge\Dto\RelationshipMapping;
use DocxMerge\Tracking\IdTracker;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Manages relationship mappings between source and target documents.
 *
 * Filters source relationships to include only those referenced in the
 * extracted content, excludes structural and header/footer relationships,
 * and assigns new IDs via IdTracker. Detects duplicates by Type+Target
 * combination to reuse existing target IDs.
 *
 * @see RelationshipManagerInterface
 */
final class RelationshipManager implements RelationshipManagerInterface
{
    /** Package relationships namespace URI. */
    private const NS_REL = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /**
     * Relationship types that are structural and should never be duplicated.
     *
     * @var list<string>
     */
    private const STRUCTURAL_REL_TYPES = [
        'http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles',
        'http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings',
        'http://schemas.openxmlformats.org/officeDocument/2006/relationships/fontTable',
        'http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme',
        'http://schemas.openxmlformats.org/officeDocument/2006/relationships/webSettings',
        'http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering',
    ];

    /**
     * Header/footer relationship types handled by HeaderFooterCopier.
     *
     * @var list<string>
     */
    private const HEADER_FOOTER_REL_TYPES = [
        'http://schemas.openxmlformats.org/officeDocument/2006/relationships/header',
        'http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer',
    ];

    /**
     * {@inheritdoc}
     */
    public function buildMap(
        DOMDocument $sourceRelsDom,
        DOMDocument $targetRelsDom,
        string $contentXml,
        IdTracker $idTracker,
    ): RelationshipMap {
        $sourceXpath = new DOMXPath($sourceRelsDom);
        $sourceXpath->registerNamespace('rel', self::NS_REL);

        $targetXpath = new DOMXPath($targetRelsDom);
        $targetXpath->registerNamespace('rel', self::NS_REL);

        // Build index of existing target relationships by Type+Target for duplicate detection
        $existingTargetRels = $this->buildTargetIndex($targetXpath);

        // Extract rIds referenced in the content
        $referencedIds = $this->extractReferencedIds($contentXml);

        /** @var array<string, RelationshipMapping> $mappings */
        $mappings = [];

        $relNodes = $sourceXpath->query('//rel:Relationship');
        if ($relNodes === false) {
            return new RelationshipMap($mappings);
        }

        foreach ($relNodes as $relNode) {
            if (!$relNode instanceof DOMElement) {
                continue;
            }

            $oldId = $relNode->getAttribute('Id');
            $type = $relNode->getAttribute('Type');
            $target = $relNode->getAttribute('Target');
            $targetMode = $relNode->getAttribute('TargetMode');
            $isExternal = $targetMode === 'External';

            // Skip structural relationships
            if (in_array($type, self::STRUCTURAL_REL_TYPES, true)) {
                continue;
            }

            // Skip header/footer relationships (handled by HeaderFooterCopier)
            if (in_array($type, self::HEADER_FOOTER_REL_TYPES, true)) {
                continue;
            }

            // Skip relationships not referenced in the content
            if (!in_array($oldId, $referencedIds, true)) {
                continue;
            }

            // Check for duplicate by Type+Target in the target
            $duplicateKey = $type . '|' . $target;
            if (isset($existingTargetRels[$duplicateKey])) {
                // Reuse the existing target rId
                $mappings[$oldId] = new RelationshipMapping(
                    oldId: $oldId,
                    newId: $existingTargetRels[$duplicateKey],
                    type: $type,
                    target: $target,
                    newTarget: $target,
                    needsFileCopy: false,
                    isExternal: $isExternal,
                );
                continue;
            }

            $newId = $idTracker->nextRelationshipId();

            $mappings[$oldId] = new RelationshipMapping(
                oldId: $oldId,
                newId: $newId,
                type: $type,
                target: $target,
                newTarget: $target,
                needsFileCopy: !$isExternal,
                isExternal: $isExternal,
            );
        }

        return new RelationshipMap($mappings);
    }

    /**
     * {@inheritdoc}
     */
    public function addRelationships(
        DOMDocument $targetRelsDom,
        RelationshipMap $relationshipMap,
    ): void {
        $root = $targetRelsDom->documentElement;
        if ($root === null) {
            return;
        }

        // Build index of existing target rIds to avoid adding duplicates
        $targetXpath = new DOMXPath($targetRelsDom);
        $targetXpath->registerNamespace('rel', self::NS_REL);
        $existingIds = $this->extractExistingTargetIds($targetXpath);

        foreach ($relationshipMap->mappings as $mapping) {
            // Skip if this new rId already exists in the target
            if (in_array($mapping->newId, $existingIds, true)) {
                continue;
            }

            $relElement = $targetRelsDom->createElementNS(self::NS_REL, 'Relationship');
            $relElement->setAttribute('Id', $mapping->newId);
            $relElement->setAttribute('Type', $mapping->type);
            $relElement->setAttribute('Target', $mapping->newTarget);

            if ($mapping->isExternal) {
                $relElement->setAttribute('TargetMode', 'External');
            }

            $root->appendChild($relElement);
        }
    }

    /**
     * Builds an index of target relationships by Type+Target for duplicate detection.
     *
     * @param DOMXPath $targetXpath The target rels XPath instance.
     *
     * @return array<string, string> Map of "Type|Target" to existing rId.
     */
    private function buildTargetIndex(DOMXPath $targetXpath): array
    {
        $index = [];
        $nodes = $targetXpath->query('//rel:Relationship');

        if ($nodes === false) {
            return $index;
        }

        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $type = $node->getAttribute('Type');
            $target = $node->getAttribute('Target');
            $id = $node->getAttribute('Id');
            $key = $type . '|' . $target;
            $index[$key] = $id;
        }

        return $index;
    }

    /**
     * Extracts rId values referenced in the content XML.
     *
     * Searches for rId patterns in attribute values throughout the content
     * to determine which relationships are actually used.
     *
     * @param string $contentXml The extracted content XML string.
     *
     * @return list<string> The list of unique rId values found.
     */
    private function extractReferencedIds(string $contentXml): array
    {
        $matches = [];
        // Match rId references in any attribute value
        preg_match_all('/\brId\d+\b/', $contentXml, $matches);

        if (empty($matches[0])) {
            return [];
        }

        return array_values(array_unique($matches[0]));
    }

    /**
     * Extracts all existing rId values from the target rels DOM.
     *
     * @param DOMXPath $targetXpath The target rels XPath instance.
     *
     * @return list<string> The list of existing rId values.
     */
    private function extractExistingTargetIds(DOMXPath $targetXpath): array
    {
        $ids = [];
        $nodes = $targetXpath->query('//rel:Relationship/@Id');

        if ($nodes === false) {
            return $ids;
        }

        foreach ($nodes as $node) {
            $value = $node->nodeValue ?? '';
            if ($value !== '') {
                $ids[] = $value;
            }
        }

        return $ids;
    }
}
