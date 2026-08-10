<?php

declare(strict_types=1);

namespace PressDo\App\Services\File;

use PDO;
use PressDo\App\Services\Uploaders\ObjectKey;
use UnexpectedValueException;

/** Resolves a bounded object-key page against file metadata in one DB query. */
final readonly class PdoObjectReferenceRepository
{
    public function __construct(private PDO $database) {}

    /**
     * @param list<ObjectKey> $keys
     * @return array<string, true>
     */
    public function referencedKeys(array $keys): array
    {
        $requested = [];
        $digests = [];
        foreach ($keys as $key) {
            if (!FileObjectLocator::isManagedKey($key)) {
                continue;
            }
            $requested[$key->value] = true;
            $digestHex = substr($key->value, 3, 64);
            $digest = hex2bin($digestHex);
            if ($digest !== false) {
                $digests[$digestHex] = $digest;
            }
        }
        if ($digests === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($digests), '?'));
        $statement = $this->database->prepare(
            'SELECT f.hash, d.title FROM files f '
            . 'INNER JOIN document d ON d.uuid=f.uuid '
            . "WHERE f.hash IN ({$placeholders})",
        );
        $statement->execute(array_values($digests));

        $referenced = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (!is_array($row)) {
                throw new UnexpectedValueException('The object reference query returned a non-array row.');
            }
            $hash = $row['hash'] ?? null;
            $title = $row['title'] ?? null;
            if (!is_string($hash) || strlen($hash) !== 32 || !is_string($title)) {
                throw new UnexpectedValueException('The object reference query returned invalid metadata.');
            }
            foreach (FileObjectLocator::candidateKeys(bin2hex($hash), $title) as $candidate) {
                if (isset($requested[$candidate->value])) {
                    $referenced[$candidate->value] = true;
                }
            }
        }

        return $referenced;
    }
}
