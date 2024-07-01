<?php

declare(strict_types=1);

/*
 * This file is part of the "map_magic" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace WapplerSystems\MapMagic\Mvc\Validation;

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\MimeTypeDetector;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;
use WapplerSystems\MapMagic\Mvc\Property\TypeConverter\PseudoFile;
use WapplerSystems\MapMagic\Mvc\Validation\Exception\InvalidValidationOptionsException;

/**
 * Validator for mime types
 *
 * Scope: frontend
 */
final class MimeTypeValidator extends AbstractValidator
{
    /**
     * @var array
     */
    protected $supportedOptions = [
        'allowedMimeTypes' => [null, 'Allowed mime types (using */* IANA media types)', 'array', true],
    ];

    /**
     * The given $value is valid if it is a FileReference of the
     * configured type (one of the IANA media types)
     *
     * Note: a value of NULL or empty string ('') is considered valid
     *
     * @param FileReference|File|PseudoFile $resource The resource that should be validated
     */
    public function isValid(mixed $resource): void
    {
        $this->validateOptions();

        if ($resource instanceof FileReference) {
            $mimeType = $resource->getOriginalResource()->getMimeType();
            $fileExtension = $resource->getOriginalResource()->getExtension();
        } elseif ($resource instanceof File) {
            $mimeType = $resource->getMimeType();
            $fileExtension = $resource->getExtension();
        } elseif ($resource instanceof PseudoFile) {
            $mimeType = $resource->getMimeType();
            $fileExtension = $resource->getExtension();
        } else {
            $this->addError(
                $this->translateErrorMessage(
                    'validation.error.1471708997',
                    'map_magic'
                ),
                1471708997
            );
            return;
        }

        $allowedMimeTypes = $this->options['allowedMimeTypes'];
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            $this->addError(
                $this->translateErrorMessage(
                    'validation.error.1471708998',
                    'map_magic',
                    [$mimeType]
                ),
                1471708998,
                [$mimeType]
            );
        } else {
            // The mime-type which was detected by FAL matches, but the file name does not match.
            // Example: myfile.txt is actually a PDF file (defined by mime-type), but .txt is not associated
            // for application/pdf, so this is not valid. The file extension of the uploaded file must match
            // the mime-type for this file.
            $assumedMimesTypeOfFileExtension = (new MimeTypeDetector())->getMimeTypesForFileExtension($fileExtension);
            if (empty(array_intersect($allowedMimeTypes, $assumedMimesTypeOfFileExtension))) {
                $this->addError(
                    $this->translateErrorMessage(
                        'validation.error.1613126216',
                        'map_magic',
                        [$fileExtension]
                    ),
                    1613126216,
                    [$fileExtension]
                );
            }
        }
    }

    /**
     * Checks if this validator is correctly configured
     *
     * @throws InvalidValidationOptionsException if the configured validation options are incorrect
     */
    protected function validateOptions(): void
    {
        if (!is_array($this->options['allowedMimeTypes'] ?? false) || $this->options['allowedMimeTypes'] === []) {
            throw new InvalidValidationOptionsException('The option "allowedMimeTypes" must be an array with at least one item.', 1471713296);
        }
    }
}
