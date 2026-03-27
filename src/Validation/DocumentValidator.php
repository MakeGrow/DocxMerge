<?php

declare(strict_types=1);

namespace DocxMerge\Validation;

use DocxMerge\Dto\ValidationResult;
use DocxMerge\Merge\MergeContext;
use DocxMerge\Xml\XmlHelper;
use DOMXPath;

/**
 * Validates the consistency of a merged DOCX document.
 *
 * Performs read-only checks on the merge context DOMs and ZIP to detect
 * orphaned rIds, missing numbering definitions, and unresolved style
 * references that would cause Word to show a repair prompt.
 *
 * @see DocumentValidatorInterface
 */
final class DocumentValidator implements DocumentValidatorInterface
{
    /**
     * Validates the consistency of the merged document.
     *
     * Runs all integrity checks against the merge context and returns
     * a ValidationResult with any errors and warnings found. Checks orphaned
     * relationship IDs, numbering IDs, and style references.
     *
     * @param MergeContext $context The merge context with all DOMs and the ZIP.
     *
     * @return ValidationResult List of errors and warnings found.
     */
    public function validate(MergeContext $context): ValidationResult
    {
        /** @var list<string> $errors */
        $errors = [];

        /** @var list<string> $warnings */
        $warnings = [];

        $this->checkOrphanedRelationshipIds($context, $errors);
        $this->checkOrphanedNumIds($context, $errors);
        $this->checkOrphanedStyleReferences($context, $errors);

        return new ValidationResult($errors, $warnings);
    }

    /**
     * Checks that every r:id and r:embed in document.xml exists in rels.
     *
     * @param MergeContext $context The merge context.
     * @param list<string> &$errors Errors array to append to.
     */
    private function checkOrphanedRelationshipIds(MergeContext $context, array &$errors): void
    {
        // Build set of known relationship IDs from rels DOM
        $relsXpath = new DOMXPath($context->relsDom);
        $relsXpath->registerNamespace('rel', XmlHelper::NS_REL);

        /** @var array<string, true> $knownRIds */
        $knownRIds = [];

        $relsNodes = $relsXpath->query('//rel:Relationship/@Id');
        if ($relsNodes !== false) {
            foreach ($relsNodes as $attr) {
                $knownRIds[$attr->nodeValue ?? ''] = true;
            }
        }

        // Find all r:embed and r:id attributes in document.xml
        $docXpath = new DOMXPath($context->documentDom);
        $docXpath->registerNamespace('r', XmlHelper::NS_R);

        $embedNodes = $docXpath->query('//@r:embed');
        if ($embedNodes !== false) {
            foreach ($embedNodes as $attr) {
                $rId = $attr->nodeValue ?? '';
                if ($rId !== '' && !isset($knownRIds[$rId])) {
                    $errors[] = "Orphaned relationship reference: {$rId} not found in document.xml.rels";
                }
            }
        }

        $idNodes = $docXpath->query('//@r:id');
        if ($idNodes !== false) {
            foreach ($idNodes as $attr) {
                $rId = $attr->nodeValue ?? '';
                if ($rId !== '' && !isset($knownRIds[$rId])) {
                    $errors[] = "Orphaned relationship reference: {$rId} not found in document.xml.rels";
                }
            }
        }
    }

    /**
     * Checks that every w:numId in document.xml has a matching w:num.
     *
     * @param MergeContext $context The merge context.
     * @param list<string> &$errors Errors array to append to.
     */
    private function checkOrphanedNumIds(MergeContext $context, array &$errors): void
    {
        // Build set of known numIds from numbering DOM
        $numXpath = new DOMXPath($context->numberingDom);
        $numXpath->registerNamespace('w', XmlHelper::NS_W);

        /** @var array<int, true> $knownNumIds */
        $knownNumIds = [];

        $numNodes = $numXpath->query('//w:num/@w:numId');
        if ($numNodes !== false) {
            foreach ($numNodes as $attr) {
                $numId = (int) ($attr->nodeValue ?? '0');
                $knownNumIds[$numId] = true;
            }
        }

        // Find all w:numId references in document.xml
        $docXpath = new DOMXPath($context->documentDom);
        $docXpath->registerNamespace('w', XmlHelper::NS_W);

        $numIdNodes = $docXpath->query('//w:numId/@w:val');
        if ($numIdNodes !== false) {
            foreach ($numIdNodes as $attr) {
                $numId = (int) ($attr->nodeValue ?? '0');
                // numId 0 means "no numbering" in OOXML
                if ($numId !== 0 && !isset($knownNumIds[$numId])) {
                    $errors[] = "Orphaned numbering reference: w:numId={$numId} has no matching w:num in numbering.xml";
                }
            }
        }
    }

    /**
     * Checks that every w:pStyle and w:rStyle in document.xml exists in styles.xml.
     *
     * @param MergeContext $context The merge context.
     * @param list<string> &$errors Errors array to append to.
     */
    private function checkOrphanedStyleReferences(MergeContext $context, array &$errors): void
    {
        // Build set of known styleIds from styles DOM
        $stylesXpath = new DOMXPath($context->stylesDom);
        $stylesXpath->registerNamespace('w', XmlHelper::NS_W);

        /** @var array<string, true> $knownStyleIds */
        $knownStyleIds = [];

        $styleNodes = $stylesXpath->query('//w:style/@w:styleId');
        if ($styleNodes !== false) {
            foreach ($styleNodes as $attr) {
                $knownStyleIds[$attr->nodeValue ?? ''] = true;
            }
        }

        // Find all w:pStyle and w:rStyle references in document.xml
        $docXpath = new DOMXPath($context->documentDom);
        $docXpath->registerNamespace('w', XmlHelper::NS_W);

        $pStyleNodes = $docXpath->query('//w:pStyle/@w:val');
        if ($pStyleNodes !== false) {
            foreach ($pStyleNodes as $attr) {
                $styleId = $attr->nodeValue ?? '';
                if ($styleId !== '' && !isset($knownStyleIds[$styleId])) {
                    $errors[] = "Orphaned style reference: w:pStyle=\"{$styleId}\" not found in styles.xml";
                }
            }
        }

        $rStyleNodes = $docXpath->query('//w:rStyle/@w:val');
        if ($rStyleNodes !== false) {
            foreach ($rStyleNodes as $attr) {
                $styleId = $attr->nodeValue ?? '';
                if ($styleId !== '' && !isset($knownStyleIds[$styleId])) {
                    $errors[] = "Orphaned style reference: w:rStyle=\"{$styleId}\" not found in styles.xml";
                }
            }
        }
    }
}
