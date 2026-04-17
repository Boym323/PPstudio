<?php
declare(strict_types=1);

namespace PPStudio\Service;

use mysqli;
use mysqli_result;
use RuntimeException;
use Throwable;

final class AdminServiceMutationService
{
    public function __construct(
        private mysqli $connection
    ) {
    }

    public function saveCategory(array $post): array
    {
        $categoryId = (int) ($post['category_id'] ?? 0);
        $categoryName = trim((string) ($post['category_name'] ?? ''));
        $categoryOrder = trim((string) ($post['category_order'] ?? ''));

        $categoryForm = [
            'id' => $categoryId,
            'nazev' => $categoryName,
            'poradi' => $categoryOrder,
        ];

        if ($categoryName === '') {
            return $this->error('Název kategorie je povinný.', ['category_form' => $categoryForm]);
        }

        if ($categoryOrder !== '' && ! ctype_digit($categoryOrder)) {
            return $this->error('Pořadí kategorie musí být celé kladné číslo nebo prázdné pole.', ['category_form' => $categoryForm]);
        }

        $normalizedOrder = $categoryOrder !== '' ? (int) $categoryOrder : 9999;

        if ($categoryId > 0) {
            $statement = $this->connection->prepare('UPDATE kategorie SET nazev = ?, poradi = ? WHERE id = ?');
            if ($statement) {
                $statement->bind_param('sii', $categoryName, $normalizedOrder, $categoryId);
            }
        } else {
            $statement = $this->connection->prepare('INSERT INTO kategorie (nazev, poradi) VALUES (?, ?)');
            if ($statement) {
                $statement->bind_param('si', $categoryName, $normalizedOrder);
            }
        }

        if (! isset($statement) || ! $statement) {
            return $this->noop(['category_form' => $categoryForm]);
        }

        $success = $statement->execute();
        $statement->close();

        if (! $success) {
            return $this->error('Kategorii se nepodařilo uložit. Název kategorie musí být unikátní.', ['category_form' => $categoryForm]);
        }

        return $this->success(
            $categoryId > 0 ? 'Kategorie byla upravena.' : 'Kategorie byla přidána.',
            ['category_form' => $this->emptyCategoryForm()]
        );
    }

    public function toggleCategoryActive(array $post): array
    {
        $categoryId = (int) ($post['category_id'] ?? 0);
        $targetActive = (int) ($post['target_active'] ?? 1);
        $targetActive = $targetActive === 0 ? 0 : 1;

        if ($categoryId <= 0) {
            return $this->noop();
        }

        $selectCategory = $this->connection->prepare('SELECT nazev, aktivni FROM kategorie WHERE id = ? LIMIT 1');
        if (! $selectCategory) {
            return $this->noop();
        }

        $selectCategory->bind_param('i', $categoryId);
        $selectCategory->execute();
        $selectCategory->bind_result($categoryName, $currentActive);
        $exists = $selectCategory->fetch();
        $selectCategory->close();

        if (! $exists) {
            return $this->error('Kategorie nebyla nalezena.');
        }

        if ((string) $categoryName === 'Ostatní služby') {
            return $this->error('Kategorii „Ostatní služby“ nelze deaktivovat.');
        }

        if ((int) $currentActive === $targetActive) {
            return $this->success($targetActive === 1 ? 'Kategorie už je aktivní.' : 'Kategorie už je neaktivní.');
        }

        $this->connection->begin_transaction();

        $updateCategory = $this->connection->prepare('UPDATE kategorie SET aktivni = ? WHERE id = ?');
        if (! $updateCategory) {
            $this->connection->rollback();

            return $this->error('Stav kategorie se nepodařilo změnit.');
        }

        $updateCategory->bind_param('ii', $targetActive, $categoryId);
        $okCategory = $updateCategory->execute();
        $updateCategory->close();

        $okServices = true;
        if ($okCategory && $targetActive === 0) {
            $deactivateServices = $this->connection->prepare('UPDATE sluzby SET aktivni = 0 WHERE kategorie_id = ?');
            if ($deactivateServices) {
                $deactivateServices->bind_param('i', $categoryId);
                $okServices = $deactivateServices->execute();
                $deactivateServices->close();
            } else {
                $okServices = false;
            }
        }

        if (! $okCategory || ! $okServices) {
            $this->connection->rollback();

            return $this->error('Stav kategorie se nepodařilo změnit.');
        }

        $this->connection->commit();

        return $this->success(
            $targetActive === 1
                ? 'Kategorie byla aktivována.'
                : 'Kategorie byla deaktivována. Navázané procedury byly také deaktivovány.'
        );
    }

    public function saveCategoryOrder(array $post): array
    {
        $rawOrder = trim((string) ($post['category_order_ids'] ?? ''));

        if ($rawOrder === '') {
            return $this->error('Pořadí kategorií je prázdné.');
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $rawOrder)), static fn (string $value): bool => $value !== ''));
        $categoryIds = [];

        foreach ($parts as $part) {
            if (! ctype_digit($part)) {
                return $this->error('Neplatný formát pořadí kategorií.');
            }

            $categoryIds[] = (int) $part;
        }

        $uniqueCategoryIds = array_values(array_unique($categoryIds));
        if (count($uniqueCategoryIds) !== count($categoryIds)) {
            return $this->error('Pořadí kategorií obsahuje duplicity.');
        }

        $existingIds = [];
        $query = $this->connection->query('SELECT id FROM kategorie');
        if ($query instanceof mysqli_result) {
            while ($row = $query->fetch_assoc()) {
                $existingIds[] = (int) ($row['id'] ?? 0);
            }
            $query->free();
        }

        sort($existingIds);
        $submittedIds = $uniqueCategoryIds;
        sort($submittedIds);

        if ($existingIds !== $submittedIds) {
            return $this->error('Pořadí kategorií neodpovídá aktuálním kategoriím.');
        }

        $statement = $this->connection->prepare('UPDATE kategorie SET poradi = ? WHERE id = ?');
        if (! $statement) {
            return $this->error('Pořadí kategorií se nepodařilo uložit.');
        }

        $rank = 1;
        foreach ($categoryIds as $categoryId) {
            $statement->bind_param('ii', $rank, $categoryId);
            if (! $statement->execute()) {
                $statement->close();

                return $this->error('Pořadí kategorií se nepodařilo uložit.');
            }

            $rank++;
        }

        $statement->close();

        return $this->success('Pořadí kategorií bylo uloženo.');
    }

    public function saveService(array $post): array
    {
        $serviceId = (int) ($post['service_id'] ?? 0);
        $name = trim((string) ($post['nazev'] ?? ''));
        $categoryId = (int) ($post['kategorie_id'] ?? 0);
        $badge = trim((string) ($post['stitek'] ?? ''));
        $description = trim((string) ($post['popis'] ?? ''));
        $price = trim((string) ($post['cena'] ?? ''));
        $duration = trim((string) ($post['doba_trvani'] ?? ''));

        $serviceForm = [
            'id' => $serviceId,
            'nazev' => $name,
            'kategorie_id' => $categoryId > 0 ? (string) $categoryId : '',
            'stitek' => $badge,
            'kategorie' => '',
            'kategorie_poradi' => '',
            'popis' => $description,
            'cena' => $price,
            'doba_trvani' => $duration,
        ];

        if ($name === '' || $duration === '') {
            return $this->error('Název a délka trvání procedury jsou povinné.', ['service_form' => $serviceForm]);
        }

        if ($badge !== '' && mb_strlen($badge) > 80) {
            return $this->error('Štítek může mít maximálně 80 znaků.', ['service_form' => $serviceForm]);
        }

        if (! ctype_digit($duration) || (int) $duration <= 0) {
            return $this->error('Délka trvání musí být kladné číslo v minutách.', ['service_form' => $serviceForm]);
        }

        if ($categoryId <= 0) {
            return $this->error('Vyberte prosím kategorii procedury.', ['service_form' => $serviceForm]);
        }

        if ($price !== '' && ! is_numeric(str_replace(',', '.', $price))) {
            return $this->error('Cena musí být číslo nebo prázdné pole.', ['service_form' => $serviceForm]);
        }

        $normalizedPrice = \PPStudio\Support\ValueHelper::normalizeNullableFloat($price);
        $durationValue = (int) $duration;
        $resolvedCategoryId = 0;
        $priceChanged = false;
        $originalPrice = null;

        $categoryCheck = $this->connection->prepare('SELECT id FROM kategorie WHERE id = ? LIMIT 1');
        if ($categoryCheck) {
            $categoryCheck->bind_param('i', $categoryId);
            $categoryCheck->execute();
            $categoryCheck->bind_result($checkedCategoryId);
            if ($categoryCheck->fetch()) {
                $resolvedCategoryId = (int) $checkedCategoryId;
            }
            $categoryCheck->close();
        }

        if ($resolvedCategoryId <= 0) {
            return $this->error('Vybraná kategorie neexistuje.', ['service_form' => $serviceForm]);
        }

        if ($serviceId > 0) {
            $servicePriceCheck = $this->connection->prepare('SELECT cena FROM sluzby WHERE id = ? LIMIT 1');
            if ($servicePriceCheck) {
                $servicePriceCheck->bind_param('i', $serviceId);
                $servicePriceCheck->execute();
                $servicePriceCheck->bind_result($currentPrice);
                if ($servicePriceCheck->fetch()) {
                    $originalPrice = $currentPrice !== null ? (float) $currentPrice : null;
                }
                $servicePriceCheck->close();
            }

            $priceChanged = $originalPrice !== $normalizedPrice;
            $statement = $this->connection->prepare(
                'UPDATE sluzby SET nazev = ?, kategorie_id = ?, stitek = ?, popis = ?, cena = ?, doba_trvani = ? WHERE id = ?'
            );
            if ($statement) {
                $statement->bind_param('sissdii', $name, $resolvedCategoryId, $badge, $description, $normalizedPrice, $durationValue, $serviceId);
            }
        } else {
            $statement = $this->connection->prepare(
                'INSERT INTO sluzby (nazev, kategorie_id, stitek, popis, cena, doba_trvani) VALUES (?, ?, ?, ?, ?, ?)'
            );
            if ($statement) {
                $statement->bind_param('sissdi', $name, $resolvedCategoryId, $badge, $description, $normalizedPrice, $durationValue);
            }
        }

        if (! isset($statement) || ! $statement) {
            return $this->noop(['service_form' => $serviceForm]);
        }

        $this->connection->begin_transaction();

        try {
            if (! $statement->execute()) {
                throw new RuntimeException('save_service_failed');
            }

            $savedServiceId = $serviceId > 0 ? $serviceId : (int) $this->connection->insert_id;
            if ($serviceId <= 0 || $priceChanged) {
                \syncServicePriceHistory($this->connection, $savedServiceId, $normalizedPrice);
            }

            $this->connection->commit();
            $statement->close();

            return $this->success(
                $serviceId > 0 ? 'Procedura byla upravena.' : 'Nová procedura byla přidána.',
                ['service_form' => $this->emptyServiceForm()]
            );
        } catch (Throwable) {
            $this->connection->rollback();
            $statement->close();

            return $this->error('Proceduru se nepodařilo uložit.', ['service_form' => $serviceForm]);
        }
    }

    public function toggleServiceActive(array $post): array
    {
        $serviceId = (int) ($post['service_id'] ?? 0);
        $targetActive = (int) ($post['target_active'] ?? 1);
        $targetActive = $targetActive === 0 ? 0 : 1;

        if ($serviceId <= 0) {
            return $this->noop();
        }

        $statement = $this->connection->prepare('UPDATE sluzby SET aktivni = ? WHERE id = ? LIMIT 1');
        if (! $statement) {
            return $this->noop();
        }

        $statement->bind_param('ii', $targetActive, $serviceId);
        $success = $statement->execute();
        $statement->close();

        if (! $success) {
            return $this->error('Stav procedury se nepodařilo změnit.');
        }

        return $this->success($targetActive === 1 ? 'Procedura byla aktivována.' : 'Procedura byla deaktivována.');
    }

    private function emptyServiceForm(): array
    {
        return [
            'id' => 0,
            'nazev' => '',
            'kategorie_id' => '',
            'stitek' => '',
            'kategorie' => '',
            'kategorie_poradi' => '',
            'popis' => '',
            'cena' => '',
            'doba_trvani' => '',
        ];
    }

    private function emptyCategoryForm(): array
    {
        return [
            'id' => 0,
            'nazev' => '',
            'poradi' => '',
        ];
    }

    private function success(string $message, array $data = []): array
    {
        return [
            'success' => true,
            'message' => $message,
            'error' => null,
            'data' => $data,
        ];
    }

    private function error(string $message, array $data = []): array
    {
        return [
            'success' => false,
            'message' => null,
            'error' => $message,
            'data' => $data,
        ];
    }

    private function noop(array $data = []): array
    {
        return [
            'success' => null,
            'message' => null,
            'error' => null,
            'data' => $data,
        ];
    }
}
