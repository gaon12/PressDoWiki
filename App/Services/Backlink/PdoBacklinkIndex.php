<?php

declare(strict_types=1);

namespace PressDo\App\Services\Backlink;

use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * Atomically replaces every outgoing relationship for one document.
 *
 * A replacement is deliberately used instead of individual mutations. It
 * keeps the stored index identical to the latest parser result, including the
 * important case where a document no longer contains any links.
 */
final readonly class PdoBacklinkIndex
{
    private const INSERT_CHUNK_SIZE = 200;

    public function __construct(private PDO $database) {}

    /** @param list<BacklinkTarget> $targets */
    public function replace(string $documentId, array $targets): void
    {
        if (strlen($documentId) !== 16) {
            throw new InvalidArgumentException('A backlink document ID must contain exactly 16 bytes.');
        }

        $startedTransaction = !$this->database->inTransaction();
        if ($startedTransaction) {
            $this->database->beginTransaction();
        }

        try {
            $delete = $this->database->prepare('DELETE FROM `links` WHERE `from_uuid`=?');
            $delete->execute([$documentId]);

            foreach (array_chunk($targets, self::INSERT_CHUNK_SIZE) as $chunk) {
                $placeholders = implode(', ', array_fill(0, count($chunk), '(?,?,?,?)'));
                $parameters = [];

                foreach ($chunk as $target) {
                    $parameters[] = $target->namespace;
                    $parameters[] = $target->title;
                    $parameters[] = $documentId;
                    $parameters[] = $target->type->value;
                }

                $insert = $this->database->prepare(
                    'INSERT INTO `links` (`namespace`, `title`, `from_uuid`, `type`) VALUES ' . $placeholders,
                );
                $insert->execute($parameters);
            }

            $complete = $this->database->prepare(
                "UPDATE `document` SET `backlink_updated`='1' WHERE `uuid`=?",
            );
            $complete->execute([$documentId]);

            if ($startedTransaction) {
                $this->database->commit();
            }
        } catch (Throwable $error) {
            if ($startedTransaction) {
                $this->database->rollBack();
            }

            throw $error;
        }
    }
}
