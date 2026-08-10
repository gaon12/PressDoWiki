<?php

declare(strict_types=1);

namespace PressDo\App\Services\File;

use PDO;
use RuntimeException;
use UnexpectedValueException;

/** Reads bounded pages of file metadata for storage integrity checks. */
final readonly class PdoFileIntegrityRepository
{
    public function __construct(private PDO $database) {}

    public function count(): int
    {
        $statement = $this->database->query('SELECT COUNT(*) FROM files');
        if ($statement === false) {
            throw new RuntimeException('The file integrity count query could not be prepared.');
        }
        $count = $statement->fetchColumn();

        return is_int($count) || is_string($count) ? (int) $count : 0;
    }

    /** @return list<StoredFileRecord> */
    public function page(int $offset, int $limit): array
    {
        $statement = $this->database->prepare(
            'SELECT f.uuid, f.hash, d.namespace, d.title '
            . 'FROM files f INNER JOIN document d ON d.uuid=f.uuid '
            . 'ORDER BY f.uuid LIMIT ? OFFSET ?',
        );
        $statement->bindValue(1, $limit, PDO::PARAM_INT);
        $statement->bindValue(2, $offset, PDO::PARAM_INT);
        $statement->execute();

        $records = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (!is_array($row)) {
                throw new UnexpectedValueException('The file integrity query returned a non-array row.');
            }
            $documentId = $row['uuid'] ?? null;
            $sha256 = $row['hash'] ?? null;
            $namespace = $row['namespace'] ?? null;
            $title = $row['title'] ?? null;
            if (!is_string($documentId) || !is_string($sha256) || !is_string($namespace) || !is_string($title)) {
                throw new UnexpectedValueException('The file integrity query returned an invalid row.');
            }

            $records[] = new StoredFileRecord($documentId, $sha256, $namespace, $title);
        }

        return $records;
    }
}
