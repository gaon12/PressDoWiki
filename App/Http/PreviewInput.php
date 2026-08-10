<?php

declare(strict_types=1);

namespace PressDo\App\Http;

use InvalidArgumentException;
use LengthException;
use PressDo\App\Core\Request;
use PressDo\App\Services\Mark\MarkHandler;

/** Validated user input for the document preview endpoint. */
final readonly class PreviewInput
{
    public const MAX_TITLE_BYTES = 1_024;

    public function __construct(
        public string $text,
        public string $title,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $text = $request->postScalarString('text');
        $title = $request->postScalarString('title');
        if ($text === null || $title === null) {
            throw new InvalidArgumentException('Preview text and title must be scalar POST fields.');
        }

        if (strlen($text) > MarkHandler::MAX_INPUT_BYTES) {
            throw new LengthException('Preview text exceeds the 2 MiB markup engine limit.');
        }

        if (strlen($title) > self::MAX_TITLE_BYTES) {
            throw new LengthException('Preview title exceeds the 1 KiB request limit.');
        }

        return new self($text, $title);
    }
}
