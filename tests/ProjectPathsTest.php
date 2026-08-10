<?php

declare(strict_types=1);

use PressDo\App\Core\ProjectPaths;
use PressDo\App\Helpers\DefaultConfig;
use PressDo\App\Helpers\Languages;
use PressDo\App\Helpers\Namespaces;

require __DIR__ . '/../vendor/autoload.php';

function failProjectPathsTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$expectedRoot = dirname(__DIR__);
if (ProjectPaths::root() !== $expectedRoot) {
    failProjectPathsTest('Project paths should resolve the installed repository root.');
}
if (!is_file(ProjectPaths::config('license.json'))) {
    failProjectPathsTest('Project paths should resolve configuration files from the repository root.');
}
if (!is_file(ProjectPaths::public('skins/pressdo/config.json'))) {
    failProjectPathsTest('Project paths should resolve public skin files from the repository root.');
}

foreach (['../config', 'skins/../config', '/absolute/path', 'C:/absolute/path', "bad\0path"] as $invalidPath) {
    try {
        ProjectPaths::public($invalidPath);
        failProjectPathsTest('Project paths should reject absolute and traversal input.');
    } catch (InvalidArgumentException) {
    }
}

$defaultConfig = new ReflectionProperty(DefaultConfig::class, 'DefConfig');
$defaultConfig->setValue(null, ['wiki.language' => 'ko-kr']);
(new ReflectionProperty(Languages::class, 'Languages'))->setValue(null, []);
(new ReflectionProperty(Namespaces::class, 'Namespaces'))->setValue(null, []);

$previousDirectory = getcwd();
chdir(sys_get_temp_dir());
try {
    if (!is_string(Languages::get('page', 'error'))) {
        failProjectPathsTest('Language files should load independently of the process working directory.');
    }
    if (Namespaces::all() !== ['문서', '파일', '사용자', '분류', '틀', '휴지통', 'PressDoWiki', '테스트']) {
        failProjectPathsTest('Namespace files should load independently of the process working directory.');
    }
} finally {
    if ($previousDirectory !== false) {
        chdir($previousDirectory);
    }
}

echo 'Project path tests passed.' . PHP_EOL;
