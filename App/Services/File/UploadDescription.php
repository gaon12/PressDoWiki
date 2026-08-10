<?php

declare(strict_types=1);

namespace PressDo\App\Services\File;

/** Validated wiki text attached to an uploaded file document. */
final readonly class UploadDescription
{
    public function __construct(
        public string $license,
        public string $category,
        public string $text,
    ) {}

    /**
     * @param list<string> $allowedLicenses
     * @param list<string> $allowedCategories
     */
    public static function fromInput(
        mixed $license,
        mixed $category,
        mixed $text,
        array $allowedLicenses,
        array $allowedCategories,
    ): self {
        if (!is_string($license) || !in_array($license, $allowedLicenses, true)) {
            throw new UploadValidationException('file_select_license');
        }
        if (!is_string($category) || !in_array($category, $allowedCategories, true)) {
            throw new UploadValidationException('file_select_category');
        }
        if (!is_string($text)) {
            throw new UploadValidationException('err_invalid_file');
        }

        return new self($license, $category, $text);
    }

    public function wikiText(string $templateNamespace, string $imageLicense, string $categoryNamespace, string $fileNamespace): string
    {
        return '[include(' . $templateNamespace . ':' . $imageLicense . '/' . $this->license . ")]\n"
            . '[[' . $categoryNamespace . ':' . $fileNamespace . '/' . $this->category . "]]\n" . $this->text;
    }
}
