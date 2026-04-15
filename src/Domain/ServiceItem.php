<?php
declare(strict_types=1);

namespace PPStudio\Domain;

final class ServiceItem
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $description,
        public readonly ?float $price,
        public readonly int $durationMinutes,
        public readonly ?string $createdAt = null,
        public readonly ?string $badge = null,
        public readonly ?string $category = null,
        public readonly ?int $categoryOrder = null
    ) {
    }

    public static function fromActiveRow(array $row): self
    {
        return new self(
            (int) ($row['id'] ?? 0),
            (string) ($row['nazev'] ?? ''),
            (string) ($row['popis'] ?? ''),
            isset($row['cena']) && $row['cena'] !== null ? (float) $row['cena'] : null,
            (int) ($row['doba_trvani'] ?? 0),
            isset($row['created_at']) ? (string) $row['created_at'] : null
        );
    }

    public static function fromCategoryRow(array $row): self
    {
        $category = trim((string) ($row['kategorie'] ?? ''));

        return new self(
            (int) ($row['id'] ?? 0),
            (string) ($row['nazev'] ?? ''),
            trim((string) ($row['popis'] ?? '')),
            isset($row['cena']) && $row['cena'] !== null ? (float) $row['cena'] : null,
            (int) ($row['doba_trvani'] ?? 0),
            null,
            trim((string) ($row['stitek'] ?? '')),
            $category !== '' ? $category : 'Ostatní služby',
            array_key_exists('kategorie_poradi', $row) && $row['kategorie_poradi'] !== null ? (int) $row['kategorie_poradi'] : null
        );
    }

    public function normalizedDurationMinutes(): int
    {
        return max(15, $this->durationMinutes);
    }

    public function toLegacyArray(): array
    {
        return [
            'id' => $this->id,
            'nazev' => $this->name,
            'popis' => $this->description,
            'cena' => $this->price,
            'doba_trvani' => $this->durationMinutes,
            'created_at' => $this->createdAt,
        ];
    }
}
