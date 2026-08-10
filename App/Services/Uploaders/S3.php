<?php

declare(strict_types=1);

namespace PressDo\App\Services\Uploaders;

use Aws\Credentials\Credentials;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;
use PressDo\App\Helpers\DefaultConfig;
use Throwable;

/** S3-compatible object storage using conditional writes to prevent overwrites. */
final readonly class S3 implements ObjectStorageInterface
{
    private S3ClientInterface $client;

    private string $bucket;

    public function __construct(?S3ClientInterface $client = null, ?string $bucket = null)
    {
        $this->bucket = $bucket ?? self::requiredConfigString('storage.bucket');
        if ($client !== null) {
            $this->client = $client;
            return;
        }

        $option = [
            'version' => self::requiredConfigString('storage.version'),
            'region' => self::requiredConfigString('storage.region'),
            'credentials' => new Credentials(
                self::requiredConfigString('storage.key'),
                self::requiredConfigString('storage.secret'),
            ),
        ];

        $endpoint = DefaultConfig::get('storage.endpoint');
        if (is_string($endpoint) && $endpoint !== '') {
            $option['endpoint'] = $endpoint;
        }

        $this->client = new S3Client($option);
    }

    public function store(ObjectKey $key, string $sourcePath): StoredObject
    {
        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            throw new StorageException('The upload source file is missing or unreadable.');
        }

        for ($attempt = 0; $attempt < 2; ++$attempt) {
            try {
                $this->client->putObject([
                    'Bucket' => $this->bucket,
                    'Key' => $key->value,
                    'SourceFile' => $sourcePath,
                    'ACL' => 'public-read',
                    'IfNoneMatch' => '*',
                ]);

                return new StoredObject($key, true);
            } catch (S3Exception $error) {
                if ($error->getStatusCode() === 412) {
                    return new StoredObject($key, false);
                }
                if ($error->getStatusCode() === 409 && $attempt === 0) {
                    continue;
                }

                throw new StorageException('The S3 object could not be stored.', previous: $error);
            } catch (Throwable $error) {
                throw new StorageException('The S3 object could not be stored.', previous: $error);
            }
        }

        throw new StorageException('The S3 conditional write could not be completed.');
    }

    public function delete(ObjectKey $key): void
    {
        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $key->value,
            ]);
        } catch (Throwable $error) {
            throw new StorageException('The S3 object could not be deleted.', previous: $error);
        }
    }

    private static function requiredConfigString(string $key): string
    {
        $value = DefaultConfig::get($key);
        if (!is_string($value) || $value === '') {
            throw new StorageException("Required object storage configuration is missing: {$key}");
        }

        return $value;
    }
}
