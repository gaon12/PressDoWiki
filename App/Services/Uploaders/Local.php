<?php

declare(strict_types=1);

namespace PressDo\App\Services\Uploaders;

use InvalidArgumentException;
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

    public function exists(ObjectKey $key): bool
    {
        return is_file($this->target($key, false));
    }

    public function listObjects(?ObjectKey $after, int $limit): ObjectPage
    {
        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Object inventory limits must be between 1 and 100.');
        }

        $items = [];
        $directories = array_filter(scandir($this->baseDirectory) ?: [], function (string $name): bool {
            return preg_match('/\A[a-f0-9]{2}\z/D', $name) === 1
                && is_dir($this->baseDirectory . DIRECTORY_SEPARATOR . $name);
        });
        sort($directories, SORT_STRING);

        foreach ($directories as $directory) {
            $path = $this->baseDirectory . DIRECTORY_SEPARATOR . $directory;
            $filenames = array_filter(scandir($path) ?: [], fn(string $name): bool => is_file($path . DIRECTORY_SEPARATOR . $name));
            sort($filenames, SORT_STRING);
            foreach ($filenames as $filename) {
                $key = new ObjectKey($directory . '/' . $filename);
                if ($after !== null && strcmp($key->value, $after->value) <= 0) {
                    continue;
                }
                $objectPath = $path . DIRECTORY_SEPARATOR . $filename;
                $modified = filemtime($objectPath);
                $size = filesize($objectPath);
                if ($modified === false || $size === false) {
                    throw new StorageException("Local object metadata could not be read: {$key->value}");
                }
                $items[] = new ObjectInfo($key, $modified, $size);
                if (count($items) > $limit) {
                    $nextCursor = $items[$limit - 1]->key;

                    return new ObjectPage(array_slice($items, 0, $limit), $nextCursor);
                }
            }
        }

        return new ObjectPage($items, null);
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
