<?php
declare(strict_types=1);

namespace PPStudio\Repository;

use mysqli;

final class MediaRepository
{
    public function __construct(
        private mysqli $connection
    ) {
    }

    public function findByCategory(string $category, int $limit = 50): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, category, image_path, title, subtitle, external_url, sort_order
             FROM media
             WHERE category = ?
             ORDER BY sort_order ASC, id DESC
             LIMIT ?'
        );

        if (! $statement) {
            return [];
        }

        $statement->bind_param('si', $category, $limit);
        $statement->execute();
        $statement->bind_result($id, $rowCategory, $imagePath, $title, $subtitle, $externalUrl, $sortOrder);

        $items = [];

        while ($statement->fetch()) {
            $items[] = [
                'id' => $id,
                'category' => $rowCategory,
                'image_path' => $imagePath,
                'title' => $title,
                'subtitle' => $subtitle,
                'external_url' => $externalUrl,
                'sort_order' => $sortOrder,
            ];
        }

        $statement->close();

        return $items;
    }

    public function deleteByCategory(string $category): bool
    {
        $statement = $this->connection->prepare('DELETE FROM media WHERE category = ?');
        if (! $statement) {
            return false;
        }

        $statement->bind_param('s', $category);
        $executed = $statement->execute();
        $statement->close();

        return $executed;
    }

    public function create(
        string $category,
        string $imagePath,
        string $title,
        string $subtitle,
        string $externalUrl,
        int $sortOrder
    ): ?bool {
        $statement = $this->connection->prepare(
            'INSERT INTO media (category, image_path, title, subtitle, external_url, sort_order)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        if (! $statement) {
            return null;
        }

        $statement->bind_param('sssssi', $category, $imagePath, $title, $subtitle, $externalUrl, $sortOrder);
        $executed = $statement->execute();
        $statement->close();

        return $executed;
    }

    public function findImagePathById(int $mediaId): ?string
    {
        $statement = $this->connection->prepare('SELECT image_path FROM media WHERE id = ? LIMIT 1');
        if (! $statement) {
            return null;
        }

        $statement->bind_param('i', $mediaId);
        $statement->execute();
        $statement->bind_result($imagePath);

        $path = null;
        if ($statement->fetch()) {
            $path = (string) $imagePath;
        }

        $statement->close();

        return $path;
    }

    public function deleteById(int $mediaId): ?bool
    {
        $statement = $this->connection->prepare('DELETE FROM media WHERE id = ? LIMIT 1');
        if (! $statement) {
            return null;
        }

        $statement->bind_param('i', $mediaId);
        $executed = $statement->execute();
        $statement->close();

        return $executed;
    }
}
