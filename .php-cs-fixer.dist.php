<?php

declare(strict_types=1);

/*
 * Formatting is introduced as a ratchet, just like PHPStan. Core and Helpers
 * were normalized in a dedicated formatting-only commit. Add each migrated
 * directory to this finder when its first vertical slice is introduced.
 *
 * Avoid formatting the entire legacy tree in a functional commit. A dedicated
 * formatting-only commit can move a legacy area under this gate before it is
 * refactored.
 */
$finder = PhpCsFixer\Finder::create()
    ->files()
    ->in([
        __DIR__.'/App/Admin/Settings',
        __DIR__.'/App/Core',
        __DIR__.'/App/Helpers',
        __DIR__.'/App/Http',
        __DIR__.'/App/Infrastructure/Rendering',
        __DIR__.'/App/Shared/Rendering',
        __DIR__.'/tests/Rendering',
        __DIR__.'/tests/Http',
        __DIR__.'/tests/Admin',
    ])
    ->append([
        __DIR__.'/App/Controllers/Pages/admin/Config.php',
        __DIR__.'/tests/GeoIpTest.php',
    ]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS2.0' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_quote' => true,
        'trailing_comma_in_multiline' => true,
    ])
    ->setFinder($finder);
