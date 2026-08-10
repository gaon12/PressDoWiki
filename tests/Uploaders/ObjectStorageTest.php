<?php

declare(strict_types=1);

use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use PressDo\App\Services\Uploaders\Local;
use PressDo\App\Services\Uploaders\ObjectKey;
use PressDo\App\Services\Uploaders\ObjectStorageFactory;
use PressDo\App\Services\Uploaders\S3;
use PressDo\App\Services\Uploaders\StorageException;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failObjectStorageTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

/** @phpstan-impure */
function lastMockCommand(MockHandler $handler): Aws\CommandInterface
{
    return $handler->getLastCommand();
}

try {
    new ObjectKey('../escape.webp');
    failObjectStorageTest('Object keys must reject parent traversal.');
} catch (InvalidArgumentException) {
}

$testRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pressdo-storage-' . bin2hex(random_bytes(8));
$source = $testRoot . '-source';
if (file_put_contents($source, 'first object') === false) {
    failObjectStorageTest('The local storage source fixture could not be created.');
}

$local = new Local($testRoot);
$key = new ObjectKey('ab/' . str_repeat('c', 64) . '.webp');
$first = $local->store($key, $source);
if (!$first->created) {
    failObjectStorageTest('The first local object write should create the object.');
}
if (!$local->exists($key)) {
    failObjectStorageTest('A stored local object should be discoverable.');
}
$target = $testRoot . DIRECTORY_SEPARATOR . 'ab' . DIRECTORY_SEPARATOR . str_repeat('c', 64) . '.webp';
if (file_get_contents($target) !== 'first object') {
    failObjectStorageTest('The local object should contain the source bytes.');
}
$secondKey = new ObjectKey('ac/' . str_repeat('d', 64) . '.png');
$local->store($secondKey, $source);
$firstPage = $local->listObjects(null, 1);
if (count($firstPage->items) !== 1 || $firstPage->items[0]->key->value !== $key->value || $firstPage->nextCursor?->value !== $key->value) {
    failObjectStorageTest('Local inventories should return stable lexicographic cursor pages.');
}
$secondPage = $local->listObjects($firstPage->nextCursor, 1);
if (count($secondPage->items) !== 1 || $secondPage->items[0]->key->value !== $secondKey->value || $secondPage->nextCursor !== null) {
    failObjectStorageTest('Local inventory cursors should continue after the previous key.');
}
file_put_contents($source, 'replacement');
$second = $local->store($key, $source);
if ($second->created || file_get_contents($target) !== 'first object') {
    failObjectStorageTest('A repeated local write must not overwrite the existing object.');
}
$local->delete($key);
$local->delete($secondKey);
$local->delete($key);
if (file_exists($target)) {
    failObjectStorageTest('Local object deletion should be idempotent.');
}
if ($local->exists($key)) {
    failObjectStorageTest('A deleted local object should no longer be discoverable.');
}
unlink($source);
rmdir(dirname($target));
rmdir($testRoot . DIRECTORY_SEPARATOR . 'ac');
rmdir($testRoot);

try {
    ObjectStorageFactory::create('unsupported');
    failObjectStorageTest('The storage factory must reject unconfigured implementations.');
} catch (StorageException) {
}

$s3Source = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pressdo-s3-' . bin2hex(random_bytes(8));
file_put_contents($s3Source, 's3 object');
$handler = new MockHandler([new Result()]);
$client = new S3Client([
    'version' => 'latest',
    'region' => 'ap-northeast-2',
    'credentials' => false,
    'handler' => $handler,
]);
$s3 = new S3($client, 'test-bucket');
$s3Result = $s3->store($key, $s3Source);
if (!$s3Result->created) {
    failObjectStorageTest('A successful S3 conditional write should report object creation.');
}
$putCommand = lastMockCommand($handler);
if ($putCommand->getName() !== 'PutObject' || $putCommand['IfNoneMatch'] !== '*') {
    failObjectStorageTest('S3 writes must use IfNoneMatch to prevent overwrites.');
}
$headHandler = new MockHandler([new Result()]);
$headClient = new S3Client([
    'version' => 'latest',
    'region' => 'ap-northeast-2',
    'credentials' => false,
    'handler' => $headHandler,
]);
$headStorage = new S3($headClient, 'test-bucket');
if (!$headStorage->exists($key) || lastMockCommand($headHandler)->getName() !== 'HeadObject') {
    failObjectStorageTest('S3 existence checks should issue HeadObject.');
}
$deleteHandler = new MockHandler([new Result()]);
$deleteClient = new S3Client([
    'version' => 'latest',
    'region' => 'ap-northeast-2',
    'credentials' => false,
    'handler' => $deleteHandler,
]);
$deleteStorage = new S3($deleteClient, 'test-bucket');
$deleteStorage->delete($key);
if (lastMockCommand($deleteHandler)->getName() !== 'DeleteObject') {
    failObjectStorageTest('S3 compensation should issue DeleteObject.');
}
$listHandler = new MockHandler([new Result([
    'Contents' => [[
        'Key' => $key->value,
        'LastModified' => new DateTimeImmutable('@1700000000'),
        'Size' => 9,
    ]],
    'IsTruncated' => true,
])]);
$listClient = new S3Client([
    'version' => 'latest',
    'region' => 'ap-northeast-2',
    'credentials' => false,
    'handler' => $listHandler,
]);
$listStorage = new S3($listClient, 'test-bucket');
$objectPage = $listStorage->listObjects(new ObjectKey('aa/' . str_repeat('0', 64) . '.png'), 1);
$listCommand = lastMockCommand($listHandler);
if (
    $listCommand->getName() !== 'ListObjectsV2'
    || $listCommand['MaxKeys'] !== 1
    || $listCommand['StartAfter'] !== 'aa/' . str_repeat('0', 64) . '.png'
    || $objectPage->nextCursor?->value !== $key->value
) {
    failObjectStorageTest('S3 inventories should use bounded StartAfter ListObjectsV2 requests.');
}
unlink($s3Source);

echo 'Object storage contract tests passed.' . PHP_EOL;
