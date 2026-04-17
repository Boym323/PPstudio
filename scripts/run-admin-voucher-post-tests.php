#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Tento skript lze spouštět jen z CLI.\n";
    exit(1);
}

const SCRIPT_PREFIX = '[admin-voucher-post-tests]';

require_once __DIR__ . '/_test_helpers.php';

function captureChildResponse(array $server, array $post, array $voucherForm, array $voucherBatchForm, array $env): array
{
    $command = [
        PHP_BINARY,
        __FILE__,
        '--child',
    ];

    $childEnv = array_merge($_ENV, $env, [
        'PPSTUDIO_ADMIN_VOUCHER_POST_CHILD' => '1',
        'PPSTUDIO_ADMIN_VOUCHER_POST_SERVER' => json_encode($server, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'PPSTUDIO_ADMIN_VOUCHER_POST_POST' => json_encode($post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'PPSTUDIO_ADMIN_VOUCHER_POST_FORM' => json_encode($voucherForm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'PPSTUDIO_ADMIN_VOUCHER_POST_BATCH_FORM' => json_encode($voucherBatchForm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    return ppstudioCliTestCaptureJsonChildResponse(SCRIPT_PREFIX, $command, $childEnv, dirname(__DIR__));
}

$argvCopy = $argv;
array_shift($argvCopy);
$isChild = in_array('--child', $argvCopy, true);

if ($isChild) {
    ppstudioCliTestBootstrapBase();
    require dirname(__DIR__) . '/includes/admin/actions/post/helpers.php';

    $server = json_decode((string) getenv('PPSTUDIO_ADMIN_VOUCHER_POST_SERVER'), true);
    $post = json_decode((string) getenv('PPSTUDIO_ADMIN_VOUCHER_POST_POST'), true);
    $voucherForm = json_decode((string) getenv('PPSTUDIO_ADMIN_VOUCHER_POST_FORM'), true);
    $voucherBatchForm = json_decode((string) getenv('PPSTUDIO_ADMIN_VOUCHER_POST_BATCH_FORM'), true);

    $server = is_array($server) ? $server : [];
    $post = is_array($post) ? $post : [];
    $voucherForm = is_array($voucherForm) ? $voucherForm : [];
    $voucherBatchForm = is_array($voucherBatchForm) ? $voucherBatchForm : [];

    $_SERVER = array_merge($_SERVER, $server);
    $_POST = $post;
    $_REQUEST = array_merge($_GET, $_POST);

    $connection = \PPStudio\Database\DatabaseFactory::connect();
    $emailConfig = is_array($emailConfig ?? null) ? $emailConfig : [];
    $siteSettings = is_array($siteSettings ?? null) ? $siteSettings : [];
    $message = '';
    $error = '';

    ob_start();
    require dirname(__DIR__) . '/includes/admin/actions/post/vouchers.php';
    ob_end_clean();

    echo json_encode([
        'code' => 200,
        'message' => $message,
        'error' => $error,
        'voucher_form' => $voucherForm,
        'voucher_batch_form' => $voucherBatchForm,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit(0);
}

ppstudioCliTestBootstrapBase();

$storageDir = ppstudioCliTestTempSecurityStorageDir(SCRIPT_PREFIX, 'ppstudio-admin-voucher-post-');
$voucherVerifySecret = 'admin-voucher-post-' . bin2hex(random_bytes(16));
$previousEnv = ppstudioCliTestSetEnv([
    'PPSTUDIO_SECURITY_STORAGE' => $storageDir,
    'PPSTUDIO_VOUCHER_VERIFY_SECRET' => $voucherVerifySecret,
    'PPSTUDIO_ACTION_SECRET' => $voucherVerifySecret,
    'HTTP_HOST' => 'voucher-tests.local',
    'HTTPS' => 'off',
]);

$connection = \PPStudio\Database\DatabaseFactory::connect();
$token = bin2hex(random_bytes(4));
$code = 'IT-VOUCHER-' . strtoupper($token);
$expiresAt = (new DateTimeImmutable('+60 days'))->format('Y-m-d');
$recipientName = 'Test Recipient ' . $token;
$recipientEmail = 'voucher-post-' . $token . '@example.test';
$note = 'admin voucher post smoke test ' . $token;

try {
    (new \PPStudio\Security\SecurityFacade())->startSecureSession();
    session_unset();

    $createResponse = captureChildResponse(
        [
            'REQUEST_METHOD' => 'POST',
            'HTTP_HOST' => 'voucher-tests.local',
        ],
        [
            'create_voucher' => '1',
            'voucher_code' => $code,
            'voucher_value' => '1200',
            'voucher_expires_at' => $expiresAt,
            'voucher_recipient_name' => $recipientName,
            'voucher_recipient_email' => $recipientEmail,
            'voucher_note' => $note,
        ],
        [
            'code' => '',
            'value' => '',
            'expires_at' => '',
            'recipient_name' => '',
            'recipient_email' => '',
            'note' => '',
        ],
        [
            'prefix' => '',
            'count' => '20',
            'value' => '1000',
            'expires_at' => '',
            'recipient_name' => '',
            'note' => '',
        ],
        [
            'PPSTUDIO_SECURITY_STORAGE' => $storageDir,
            'PPSTUDIO_VOUCHER_VERIFY_SECRET' => $voucherVerifySecret,
            'PPSTUDIO_ACTION_SECRET' => $voucherVerifySecret,
            'HTTP_HOST' => 'voucher-tests.local',
            'HTTPS' => 'off',
        ]
    );

    ppstudioCliTestAssertSame(SCRIPT_PREFIX, 200, (int) ($createResponse['code'] ?? 0), 'create_voucher ma vratit HTTP 200.');
    ppstudioCliTestAssertSame(SCRIPT_PREFIX, 'Poukaz byl uložen.', (string) ($createResponse['message'] ?? ''), 'create_voucher ma potvrdit ulozeni poukazu.');
    ppstudioCliTestAssertSame(SCRIPT_PREFIX, '', (string) ($createResponse['error'] ?? ''), 'create_voucher nema vratit chybu.');

    $checkVoucher = $connection->prepare(
        'SELECT id, kod, ROUND(puvodni_hodnota, 0) AS puvodni_hodnota, ROUND(zustatek, 0) AS zustatek, status, recipient_email
         FROM poukazy
         WHERE kod = ?
         LIMIT 1'
    );
    $checkVoucher->bind_param('s', $code);
    $checkVoucher->execute();
    $voucherResult = $checkVoucher->get_result();
    $voucherRow = $voucherResult instanceof mysqli_result ? $voucherResult->fetch_assoc() : [];
    if ($voucherResult instanceof mysqli_result) {
        $voucherResult->free();
    }
    $checkVoucher->close();

    ppstudioCliTestAssertTrue(SCRIPT_PREFIX, is_array($voucherRow) && ($voucherRow['id'] ?? 0) !== null, 'Po create musi existovat ulozeny poukaz.');
    ppstudioCliTestAssertSame(SCRIPT_PREFIX, 1200, (int) ($voucherRow['puvodni_hodnota'] ?? 0), 'Ulozeny poukaz ma mit spravnou hodnotu.');
    ppstudioCliTestAssertSame(SCRIPT_PREFIX, 1200, (int) ($voucherRow['zustatek'] ?? 0), 'Ulozeny poukaz ma mit plny zustatek.');
    ppstudioCliTestAssertSame(SCRIPT_PREFIX, 'aktivni', (string) ($voucherRow['status'] ?? ''), 'Ulozeny poukaz ma mit stav aktivni.');
    ppstudioCliTestAssertSame(SCRIPT_PREFIX, mb_strtolower($recipientEmail), (string) ($voucherRow['recipient_email'] ?? ''), 'Ulozeny poukaz ma mit normalizovany e-mail.');

    $voucherId = (int) ($voucherRow['id'] ?? 0);

    $redeemResponse = captureChildResponse(
        [
            'REQUEST_METHOD' => 'POST',
            'HTTP_HOST' => 'voucher-tests.local',
        ],
        [
            'redeem_voucher' => '1',
            'voucher_id' => $voucherId,
            'redeem_amount' => '350',
            'redeem_note' => 'smoke redeem ' . $token,
        ],
        [
            'code' => '',
            'value' => '',
            'expires_at' => '',
            'recipient_name' => '',
            'recipient_email' => '',
            'note' => '',
        ],
        [
            'prefix' => '',
            'count' => '20',
            'value' => '1000',
            'expires_at' => '',
            'recipient_name' => '',
            'note' => '',
        ],
        [
            'PPSTUDIO_SECURITY_STORAGE' => $storageDir,
            'PPSTUDIO_VOUCHER_VERIFY_SECRET' => $voucherVerifySecret,
            'PPSTUDIO_ACTION_SECRET' => $voucherVerifySecret,
            'HTTP_HOST' => 'voucher-tests.local',
            'HTTPS' => 'off',
        ]
    );

    ppstudioCliTestAssertSame(SCRIPT_PREFIX, 200, (int) ($redeemResponse['code'] ?? 0), 'redeem_voucher ma vratit HTTP 200.');
    ppstudioCliTestAssertSame(SCRIPT_PREFIX, 'Čerpání poukazu bylo uloženo. Zůstatek: 850 Kč.', (string) ($redeemResponse['message'] ?? ''), 'redeem_voucher ma potvrdit ulozeni čerpání.');
    ppstudioCliTestAssertSame(SCRIPT_PREFIX, '', (string) ($redeemResponse['error'] ?? ''), 'redeem_voucher nema vratit chybu.');

    $checkRedeem = $connection->prepare(
        'SELECT ROUND(zustatek, 0) AS zustatek, status
         FROM poukazy
         WHERE id = ?
         LIMIT 1'
    );
    $checkRedeem->bind_param('i', $voucherId);
    $checkRedeem->execute();
    $redeemResult = $checkRedeem->get_result();
    $redeemRow = $redeemResult instanceof mysqli_result ? $redeemResult->fetch_assoc() : [];
    if ($redeemResult instanceof mysqli_result) {
        $redeemResult->free();
    }
    $checkRedeem->close();

    ppstudioCliTestAssertSame(SCRIPT_PREFIX, 850, (int) ($redeemRow['zustatek'] ?? 0), 'Po redeem ma zustatku odpovidat odebrane castce.');
    ppstudioCliTestAssertSame(SCRIPT_PREFIX, 'aktivni', (string) ($redeemRow['status'] ?? ''), 'Po partial redeem ma zustat aktivni stav.');

    $checkTransaction = $connection->prepare('SELECT COUNT(*) AS total FROM poukaz_cerpani WHERE poukaz_id = ?');
    $checkTransaction->bind_param('i', $voucherId);
    $checkTransaction->execute();
    $transactionResult = $checkTransaction->get_result();
    $transactionRow = $transactionResult instanceof mysqli_result ? $transactionResult->fetch_assoc() : [];
    if ($transactionResult instanceof mysqli_result) {
        $transactionResult->free();
    }
    $checkTransaction->close();

    ppstudioCliTestAssertSame(SCRIPT_PREFIX, 1, (int) ($transactionRow['total'] ?? 0), 'Redeem ma ulozit jednu transakci.');

    echo SCRIPT_PREFIX . ' [OK] Admin voucher POST smoke test passed.' . PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    ppstudioCliTestFail(SCRIPT_PREFIX, 'Exception: ' . $exception->getMessage());
} finally {
    if (isset($voucherId) && $voucherId > 0) {
        $deleteTransactions = $connection->prepare('DELETE FROM poukaz_cerpani WHERE poukaz_id = ?');
        $deleteTransactions->bind_param('i', $voucherId);
        $deleteTransactions->execute();
        $deleteTransactions->close();

        $deleteVoucher = $connection->prepare('DELETE FROM poukazy WHERE id = ?');
        $deleteVoucher->bind_param('i', $voucherId);
        $deleteVoucher->execute();
        $deleteVoucher->close();
    } else {
        $deleteVoucher = $connection->prepare('DELETE FROM poukazy WHERE kod = ?');
        $deleteVoucher->bind_param('s', $code);
        $deleteVoucher->execute();
        $deleteVoucher->close();
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
