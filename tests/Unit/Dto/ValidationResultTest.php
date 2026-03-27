<?php

declare(strict_types=1);

/**
 * Tests for ValidationResult.
 *
 * Verifies the isValid() method behavior based on error presence,
 * ensuring correct distinction between valid and invalid documents.
 */

use DocxMerge\Dto\ValidationResult;

describe('ValidationResult', function (): void {
    describe('isValid()', function (): void {
        it('returns true when no errors exist', function (): void {
            // Arrange
            $result = new ValidationResult(errors: [], warnings: ['some warning']);

            // Act + Assert
            expect($result->isValid())->toBeTrue();
        });

        it('returns false when errors exist', function (): void {
            // Arrange
            $result = new ValidationResult(errors: ['critical error'], warnings: []);

            // Act + Assert
            expect($result->isValid())->toBeFalse();
        });

        it('returns true when both errors and warnings are empty', function (): void {
            // Arrange
            $result = new ValidationResult(errors: [], warnings: []);

            // Act + Assert
            expect($result->isValid())->toBeTrue();
        });
    });
});
