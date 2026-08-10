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
$target = $testRoot . DIRECTORY_SEPARATOR . 'ab' . DIRECTORY_SEPARATOR . str_repeat('c', 64) . '.webp';
if (file_get_contents($target) !== 'first object') {
    failObjectStorageTest('The local object should contain the source bytes.');
}
file_put_contents($source, 'replacement');
$second = $local->store($key, $source);
if ($second->created || file_get_contents($target) !== 'first object') {
    failObjectStorageTest('A repeated local write must not overwrite the existing object.');
}
$local->delete($key);
$local->delete($key);
if (file_exists($target)) {
    failObjectStorageTest('Local object deletion should be idempotent.');
}
unlink($source);
rmdir(dirname($target));
rmdir($testRoot);

try {
    ObjectStorageFactory::create('unsupported');
    failObjectStorageTest('The storage factory must reject unconfigured implementations.');
} catch (StorageException) {
}

$s3Source = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pressdo-s3-' . bin2hex(random_bytes(8));
file_put_contents($s3Source, 's3 object');
$handler = new MockHandler([new Result(), new Result()]);
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
$putCommand = $handler->getLastCommand();
if ($putCommand->getName() !== 'PutObject' || $putCommand['IfNoneMatch'] !== '*') {
    failObjectStorageTest('S3 writes must use IfNoneMatch to prevent overwrites.');
}
$s3->delete($key);
if ($handler->getLastCommand()->getName() !== 'DeleteObject') {
    failObjectStorageTest('S3 compensation should issue DeleteObject.');
}
unlink($s3Source);

echo 'Object storage contract tests passed.' . PHP_EOL;
