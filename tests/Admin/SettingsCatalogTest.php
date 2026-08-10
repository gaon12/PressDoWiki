<?php

declare(strict_types=1);

use PressDo\App\Admin\Settings\SettingsCatalog;
use PressDo\App\Admin\Settings\SettingsValidationException;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function failSettingsCatalogTest(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$catalog = new SettingsCatalog();
$normalized = $catalog->normalize([
    'wiki.site_name' => '  PressDo Test  ',
    'wiki.canonical_url' => 'https://example.test/',
    'wiki.timezone' => 'Asia/Seoul',
    'wiki.domain' => 'EXAMPLE.test',
    'unknown.php_expression' => '<?php exit; ?>',
]);

if ($normalized['wiki.site_name'] !== 'PressDo Test') {
    failSettingsCatalogTest('Text settings should be trimmed.');
}

if ($normalized['wiki.canonical_url'] !== 'https://example.test') {
    failSettingsCatalogTest('Canonical URLs should be normalized without a trailing slash.');
}

if ($normalized['wiki.domain'] !== 'example.test') {
    failSettingsCatalogTest('Hostnames should be normalized to lowercase.');
}

if (array_key_exists('unknown.php_expression', $normalized)) {
    failSettingsCatalogTest('Unknown administrator input must not become an application setting.');
}

try {
    $catalog->normalize(['wiki.canonical_url' => 'javascript:alert(1)']);
    failSettingsCatalogTest('Unsafe canonical URL schemes must be rejected.');
} catch (SettingsValidationException $exception) {
    if (!isset($exception->errors['wiki.canonical_url'])) {
        failSettingsCatalogTest('URL validation should identify the invalid setting.');
    }
}

try {
    $catalog->normalize(['wiki.timezone' => 'Not/A_Timezone']);
    failSettingsCatalogTest('Unknown timezone identifiers must be rejected.');
} catch (SettingsValidationException $exception) {
    if (!isset($exception->errors['wiki.timezone'])) {
        failSettingsCatalogTest('Timezone validation should identify the invalid setting.');
    }
}

echo 'Administrator settings catalog tests passed.' . PHP_EOL;
