<?php

declare(strict_types=1);

namespace PressDo\App\Services\Uploaders;

use PressDo\App\Core\ProjectPaths;

/** Filesystem-backed object storage rooted below the public files directory. */
final readonly class Local implements ObjectStorageInterface
{
    private string $baseDirectory;

    public function __construct(?string $baseDirectory = null)
    {
        $directory = $baseDirectory ?? ProjectPaths::public('files');
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new StorageException("The local storage directory could not be created: {$directory}");
        }

        $resolved = realpath($directory);
        if ($resolved === false || !is_dir($resolved) || !is_writable($resolved)) {
            throw new StorageException("The local storage directory is not writable: {$directory}");
        }

        $this->baseDirectory = rtrim($resolved, DIRECTORY_SEPARATOR);
    }

    public function store(ObjectKey $key, string $sourcePath): StoredObject
    {
        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            throw new StorageException('The upload source file is missing or unreadable.');
        }

        $target = $this->target($key, true);
        $destination = @fopen($target, 'x+b');
        if ($destination === false) {
            if (is_file($target)) {
                return new StoredObject($key, false);
            }

            throw new StorageException("The local object could not be created: {$key->value}");
        }

        $source = @fopen($sourcePath, 'rb');
        if ($source === false) {
            fclose($destination);
            @unlink($target);
            throw new StorageException('The upload source file could not be opened.');
        }

        try {
            if (stream_copy_to_stream($source, $destination) === false || !fflush($destination)) {
                throw new StorageException("The local object could not be written completely: {$key->value}");
            }
        } catch (\Throwable $error) {
            fclose($source);
            fclose($destination);
            @unlink($target);
            throw $error;
        }

        fclose($source);
        fclose($destination);

        return new StoredObject($key, true);
    }

    public function delete(ObjectKey $key): void
    {
        $target = $this->target($key, false);
        if (!file_exists($target)) {
            return;
        }
        if (!is_file($target) || !@unlink($target)) {
            throw new StorageException("The local object could not be deleted: {$key->value}");
        }
    }

    private function target(ObjectKey $key, bool $createParent): string
    {
        $segments = explode('/', $key->value);
        $filename = array_pop($segments);
        $parent = $this->baseDirectory;
        if ($segments !== []) {
            $parent .= DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);
        }

        if ($createParent && !is_dir($parent) && !@mkdir($parent, 0755, true) && !is_dir($parent)) {
            throw new StorageException("The local object directory could not be created: {$key->value}");
        }

        $resolvedParent = realpath($parent);
        $expectedPrefix = $this->baseDirectory . DIRECTORY_SEPARATOR;
        if (
            $resolvedParent === false
            || ($resolvedParent !== $this->baseDirectory && !str_starts_with($resolvedParent, $expectedPrefix))
        ) {
            throw new StorageException('The local object path escapes its configured storage root.');
        }

        return $resolvedParent . DIRECTORY_SEPARATOR . $filename;
    }
}
