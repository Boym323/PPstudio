#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Tento skript lze spouštět jen z CLI.\n";
    exit(1);
}

const SCRIPT_PREFIX = '[admin-voucher-usecase-tests]';

require_once __DIR__ . '/_test_helpers.php';
ppstudioCliTestBootstrapBase();

use PPStudio\Database\DatabaseFactory;
use PPStudio\Repository\VoucherRepository;
use PPStudio\Service\AdminVoucherBatchGenerateUseCase;
use PPStudio\Service\AdminVoucherEmailSendUseCase;
use PPStudio\Service\AdminVoucherRedeemUseCase;
use PPStudio\Service\MailerIntegrationService;

function connectVoucherDb(): mysqli
{
    return DatabaseFactory::connect();
}

/**
 * @return array{message:string,error:string,voucher_form:array<string,mixed>,voucher_batch_form:array<string,mixed>}
 */
function emptyVoucherForms(): array
{
    return [
        'message' => '',
        'error' => '',
        'voucher_form' => [
            'code' => '',
            'value' => '',
            'expires_at' => '',
            'recipient_name' => '',
            'recipient_email' => '',
            'note' => '',
        ],
        'voucher_batch_form' => [
            'prefix' => '',
            'count' => '20',
            'value' => '1000',
            'expires_at' => '',
            'recipient_name' => '',
            'note' => '',
        ],
    ];
}

function insertVoucher(
    mysqli $connection,
    string $code,
    float $value,
    float $remaining,
    string $status,
    ?string $expiresAt,
    string $recipientName,
    string $recipientEmail,
    string $note
): int {
    $statement = $connection->prepare(
        'INSERT INTO poukazy (kod, puvodni_hodnota, zustatek, status, issued_at, expires_at, recipient_name, recipient_email, note)
         VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?)'
    );
    $statement->bind_param('sddsssss', $code, $value, $remaining, $status, $expiresAt, $recipientName, $recipientEmail, $note);
    $statement->execute();
    $voucherId = (int) $connection->insert_id;
    $statement->close();

    return $voucherId;
}

function countVouchersByPrefix(mysqli $connection, string $prefix): int
{
    $like = $prefix . '-%';
    $statement = $connection->prepare('SELECT COUNT(*) AS total FROM poukazy WHERE kod LIKE ?');
    $statement->bind_param('s', $like);
    $statement->execute();
    $result = $statement->get_result();
    $row = $result instanceof mysqli_result ? $result->fetch_assoc() : [];
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $statement->close();

    return (int) ($row['total'] ?? 0);
}

function findVoucherRow(mysqli $connection, int $voucherId): array
{
    $statement = $connection->prepare('SELECT id, status, zustatek, emailed_at FROM poukazy WHERE id = ? LIMIT 1');
    $statement->bind_param('i', $voucherId);
    $statement->execute();
    $result = $statement->get_result();
    $row = $result instanceof mysqli_result ? $result->fetch_assoc() : [];
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $statement->close();

    return is_array($row) ? $row : [];
}

function countRedeemTransactions(mysqli $connection, int $voucherId): int
{
    $statement = $connection->prepare('SELECT COUNT(*) AS total FROM poukaz_cerpani WHERE poukaz_id = ?');
    $statement->bind_param('i', $voucherId);
    $statement->execute();
    $result = $statement->get_result();
    $row = $result instanceof mysqli_result ? $result->fetch_assoc() : [];
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $statement->close();

    return (int) ($row['total'] ?? 0);
}

$storageDir = ppstudioCliTestTempSecurityStorageDir(SCRIPT_PREFIX, 'ppstudio-admin-voucher-usecase-');
$previousEnv = ppstudioCliTestSetEnv([
    'PPSTUDIO_SECURITY_STORAGE' => $storageDir,
    'PPSTUDIO_EMAIL_ENABLED' => '0',
    'PPSTUDIO_VOUCHER_VERIFY_SECRET' => 'voucher-usecase-' . bin2hex(random_bytes(12)),
    'PPSTUDIO_ACTION_SECRET' => 'voucher-usecase-action-' . bin2hex(random_bytes(12)),
    'HTTP_HOST' => 'voucher-usecase-tests.local',
    'HTTPS' => 'off',
]);

$connection = connectVoucherDb();
$repository = new VoucherRepository($connection);
$baseForms = emptyVoucherForms();
$token = 'vu_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
$createdVoucherIds = [];

try {
    $batchUseCase = new AdminVoucherBatchGenerateUseCase($repository);
    $emailUseCase = new AdminVoucherEmailSendUseCase(
        $repository,
        new MailerIntegrationService(['enabled' => false]),
        []
    );
    $redeemUseCase = new AdminVoucherRedeemUseCase($connection, $repository);

    $batchPrefix = 'TST' . strtoupper(bin2hex(random_bytes(2)));
    $batchSuccess = $batchUseCase->handle([
        'voucher_batch_prefix' => $batchPrefix,
        'voucher_batch_count' => '2',
        'voucher_batch_value' => '500',
        'voucher_batch_expires_at' => (new DateTimeImmutable('+30 days'))->format('Y-m-d'),
        'voucher_batch_recipient_name' => 'Batch Recipient',
        'voucher_batch_note' => 'batch success ' . $token,
    ], $baseForms['voucher_form'], $baseForms['voucher_batch_form']);
    ppstudioCliTestAssertSame(SCRIPT_PREFIX, '', (string) ($batchSuccess['error'] ?? ''), 'Batch generate success nema vratit chybu.');
    ppstudioCliTestAssertContains(SCRIPT_PREFIX, 'Vygenerováno 2 poukazů.', (string) ($batchSuccess['message'] ?? ''), 'Batch generate success ma potvrdit pocet.');
    ppstudioCliTestAssertSame(SCRIPT_PREFIX, 2, countVouchersByPrefix($connection, $batchPrefix), 'Batch generate ma vytvorit dva poukazy.');

    $batchInvalidValue = $batchUseCase->handle([
        'voucher_batch_prefix' => 'BAD' . strtoupper(bin2hex(random_bytes(2))),
        'voucher_batch_count' => '2',
        'voucher_batch_value' => '0',
        'voucher_batch_expires_at' => '',
        'voucher_batch_recipient_name' => '',
        'voucher_batch_note' => '',
    ], $baseForms['voucher_form'], $baseForms['voucher_batch_form']);
    ppstudioCliTestAssertSame(SCRIPT_PREFIX, 'Hodnota poukazu musí být vyšší než 0 Kč.', (string) ($batchInvalidValue['error'] ?? ''), 'Batch generate ma odmitnout nulovou hodnotu.');

    $batchInvalidDate = $batchUseCase->handle([
        'voucher_batch_prefix' => 'DAT' . strtoupper(bin2hex(random_bytes(2))),
        'voucher_batch_count' => '2',
        'voucher_batch_value' => '500',
        'voucher_batch_expires_at' => '2026/12/31',
        'voucher_batch_recipient_name' => '',
        'voucher_batch_note' => '',
    ], $baseForms['voucher_form'], $baseForms['voucher_batch_form']);
    ppstudioCliTestAssertSame(SCRIPT_PREFIX, 'Platnost poukazu má neplatný formát data.', (string) ($batchInvalidDate['error'] ?? ''), 'Batch generate ma odmitnout neplatny format data.');

    $expiredVoucherId = insertVoucher(
        $connection,
        'EXP-' . strtoupper(bin2hex(random_bytes(3))),
        1000.0,
        1000.0,
        'aktivni',
        (new DateTimeImmutable('-1 day'))->format('Y-m-d'),
        'Expired Recipient',
        '',
        'expired email send ' . $token
    );
    $createdVoucherIds[] = $expiredVoucherId;
    $expiredEmailResult = $emailUseCase->handle([
        'voucher_id' => $expiredVoucherId,
        'voucher_recipient_email' => 'expired-' . $token . '@example.test',
    ], $baseForms['voucher_form'], $baseForms['voucher_batch_form']);
    ppstudioCliTestAssertSame(SCRIPT_PREFIX, 'E-mailem lze odeslat jen aktivní poukaz.', (string) ($expiredEmailResult['error'] ?? ''), 'Expired voucher email send ma byt odmitnut.');

    $activeVoucherId = insertVoucher(
        $connection,
        'ACT-' . strtoupper(bin2hex(random_bytes(3))),
        1200.0,
        1200.0,
        'aktivni',
        (new DateTimeImmutable('+30 days'))->format('Y-m-d'),
        'Active Recipient',
        '',
        'active email send ' . $token
    );
    $createdVoucherIds[] = $activeVoucherId;
    $disabledEmailResult = $emailUseCase->handle([
        'voucher_id' => $activeVoucherId,
        'voucher_recipient_email' => 'active-' . $token . '@example.test',
    ], $baseForms['voucher_form'], $baseForms['voucher_batch_form']);
    ppstudioCliTestAssertSame(SCRIPT_PREFIX, 'E-mailové odesílání není v nastavení aktivní.', (string) ($disabledEmailResult['error'] ?? ''), 'Active voucher ma pri vypnutem maileru vratit spravnou chybu.');
    $activeVoucherRow = findVoucherRow($connection, $activeVoucherId);
    ppstudioCliTestAssertSame(SCRIPT_PREFIX, null, $activeVoucherRow['emailed_at'] ?? null, 'Pri disabled maileru se nema zapsat emailed_at.');

    $stornoVoucherId = insertVoucher(
        $connection,
        'STO-' . strtoupper(bin2hex(random_bytes(3))),
        900.0,
        900.0,
        'storno',
        (new DateTimeImmutable('+20 days'))->format('Y-m-d'),
        'Storno Recipient',
        '',
        'storno redeem ' . $token
    );
    $createdVoucherIds[] = $stornoVoucherId;
    $stornoRedeemResult = $redeemUseCase->handle([
        'voucher_id' => $stornoVoucherId,
        'redeem_amount' => '100',
    ], $baseForms['voucher_form'], $baseForms['voucher_batch_form']);
    ppstudioCliTestAssertSame(SCRIPT_PREFIX, 'Poukaz je stornovaný.', (string) ($stornoRedeemResult['error'] ?? ''), 'Storno voucher ma odmitnout cerpani.');
    ppstudioCliTestAssertSame(SCRIPT_PREFIX, 0, countRedeemTransactions($connection, $stornoVoucherId), 'Storno voucher nema ulozit cerpani.');

    $expiredRedeemVoucherId = insertVoucher(
        $connection,
        'REX-' . strtoupper(bin2hex(random_bytes(3))),
        900.0,
        900.0,
        'aktivni',
        (new DateTimeImmutable('-2 days'))->format('Y-m-d'),
        'Expired Redeem Recipient',
        '',
        'expired redeem ' . $token
    );
    $createdVoucherIds[] = $expiredRedeemVoucherId;
    $expiredRedeemResult = $redeemUseCase->handle([
        'voucher_id' => $expiredRedeemVoucherId,
        'redeem_amount' => '100',
    ], $baseForms['voucher_form'], $baseForms['voucher_batch_form']);
    ppstudioCliTestAssertSame(SCRIPT_PREFIX, 'Poukaz je expirovaný.', (string) ($expiredRedeemResult['error'] ?? ''), 'Expirovany voucher ma odmitnout cerpani.');
    ppstudioCliTestAssertSame(SCRIPT_PREFIX, 0, countRedeemTransactions($connection, $expiredRedeemVoucherId), 'Expirovany voucher nema ulozit cerpani.');

    $overdrawVoucherId = insertVoucher(
        $connection,
        'OVR-' . strtoupper(bin2hex(random_bytes(3))),
        500.0,
        200.0,
        'aktivni',
        (new DateTimeImmutable('+20 days'))->format('Y-m-d'),
        'Overdraw Recipient',
        '',
        'overdraw redeem ' . $token
    );
    $createdVoucherIds[] = $overdrawVoucherId;
    $overdrawRedeemResult = $redeemUseCase->handle([
        'voucher_id' => $overdrawVoucherId,
        'redeem_amount' => '250',
    ], $baseForms['voucher_form'], $baseForms['voucher_batch_form']);
    ppstudioCliTestAssertSame(SCRIPT_PREFIX, 'Čerpání je vyšší než aktuální zůstatek poukazu.', (string) ($overdrawRedeemResult['error'] ?? ''), 'Cerpani nad zustatek ma byt odmitnuto.');
    ppstudioCliTestAssertSame(SCRIPT_PREFIX, 0, countRedeemTransactions($connection, $overdrawVoucherId), 'Neplatne cerpani nema ulozit transakci.');
    $overdrawVoucherRow = findVoucherRow($connection, $overdrawVoucherId);
    ppstudioCliTestAssertSame(SCRIPT_PREFIX, '200.00', number_format((float) ($overdrawVoucherRow['zustatek'] ?? 0), 2, '.', ''), 'Neplatne cerpani nema menit zustatek.');

    echo SCRIPT_PREFIX . ' [OK] Admin voucher use-case scenarios passed.' . PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    ppstudioCliTestFail(SCRIPT_PREFIX, 'Exception: ' . $exception->getMessage());
} finally {
    if ($createdVoucherIds !== []) {
        $deleteTransactions = $connection->prepare('DELETE FROM poukaz_cerpani WHERE poukaz_id = ?');
        $deleteVoucher = $connection->prepare('DELETE FROM poukazy WHERE id = ?');

        foreach ($createdVoucherIds as $voucherId) {
            try {
                $deleteTransactions->bind_param('i', $voucherId);
                $deleteTransactions->execute();
            } catch (Throwable) {
            }

            try {
                $deleteVoucher->bind_param('i', $voucherId);
                $deleteVoucher->execute();
            } catch (Throwable) {
            }
        }

        $deleteTransactions->close();
        $deleteVoucher->close();
    }

    try {
        $batchLike = $batchPrefix . '-%';
        $statement = $connection->prepare('DELETE FROM poukazy WHERE kod LIKE ?');
        $statement->bind_param('s', $batchLike);
        $statement->execute();
        $statement->close();
    } catch (Throwable) {
    }

    $connection->close();
    ppstudioCliTestRestoreEnv($previousEnv);

    if (is_dir($storageDir)) {
        $files = glob($storageDir . '/*') ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($storageDir);
    }
}
