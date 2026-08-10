<?php

declare(strict_types=1);

/**
 * Keeps external AGPL code outside the distributable dependency graph and
 * verifies that every declared clean-room component has an auditable boundary.
 */

$root = dirname(__DIR__, 2);

/** @return array<string, mixed> */
function readJsonObject(string $path): array
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException("Unable to read JSON file: {$path}");
    }

    $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($decoded) || array_is_list($decoded)) {
        throw new RuntimeException("Expected a JSON object in {$path}");
    }

    return $decoded;
}

/** @param mixed $value */
function requireStringList(mixed $value, string $field): array
{
    if (!is_array($value) || !array_is_list($value)) {
        throw new RuntimeException("{$field} must be a JSON list.");
    }

    foreach ($value as $item) {
        if (!is_string($item) || $item === '') {
            throw new RuntimeException("{$field} must contain non-empty strings.");
        }
    }

    /** @var list<string> $value */
    return $value;
}

$policy = readJsonObject($root . '/config/clean-room-components.json');
$lock = readJsonObject($root . '/composer.lock');

if (($policy['schema_version'] ?? null) !== 1) {
    throw new RuntimeException('Unsupported clean-room policy schema version.');
}

$forbiddenPrefixes = requireStringList(
    $policy['forbidden_dependency_license_prefixes'] ?? null,
    'forbidden_dependency_license_prefixes',
);

foreach (['packages', 'packages-dev'] as $packageGroup) {
    $packages = $lock[$packageGroup] ?? null;
    if (!is_array($packages) || !array_is_list($packages)) {
        throw new RuntimeException("composer.lock field {$packageGroup} must be a list.");
    }

    foreach ($packages as $package) {
        if (!is_array($package)) {
            throw new RuntimeException("composer.lock field {$packageGroup} contains an invalid package.");
        }

        $packageName = $package['name'] ?? null;
        if (!is_string($packageName) || $packageName === '') {
            throw new RuntimeException('A locked Composer package has no valid name.');
        }

        $licenses = requireStringList($package['license'] ?? null, "license metadata for {$packageName}");
        foreach ($licenses as $license) {
            foreach ($forbiddenPrefixes as $prefix) {
                if (str_starts_with(strtoupper($license), strtoupper($prefix))) {
                    throw new RuntimeException(
                        "Forbidden dependency license {$license} declared by {$packageName}.",
                    );
                }
            }
        }
    }
}

$components = $policy['components'] ?? null;
if (!is_array($components) || !array_is_list($components) || $components === []) {
    throw new RuntimeException('The clean-room policy must declare at least one component.');
}

$allowedBases = ['original-design', 'public-behaviour'];
foreach ($components as $component) {
    if (!is_array($component)) {
        throw new RuntimeException('Every clean-room component must be a JSON object.');
    }

    $name = $component['name'] ?? null;
    if (!is_string($name) || $name === '') {
        throw new RuntimeException('Every clean-room component must have a name.');
    }

    $basis = $component['implementation_basis'] ?? null;
    if (!is_string($basis) || !in_array($basis, $allowedBases, true)) {
        throw new RuntimeException("{$name} has an unsupported implementation basis.");
    }

    $sourceReferences = requireStringList(
        $component['third_party_source_references'] ?? null,
        "third_party_source_references for {$name}",
    );
    if ($sourceReferences !== []) {
        throw new RuntimeException("{$name} must not reference third-party implementation source.");
    }

    foreach (requireStringList($component['paths'] ?? null, "paths for {$name}") as $relativePath) {
        if (str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            throw new RuntimeException("{$name} contains an unsafe repository path: {$relativePath}");
        }

        if (!file_exists($root . '/' . $relativePath)) {
            throw new RuntimeException("{$name} path does not exist: {$relativePath}");
        }
    }
}

echo 'License and clean-room boundary tests passed.' . PHP_EOL;
