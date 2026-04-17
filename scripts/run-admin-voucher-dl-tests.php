#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Tento skript lze spouštět jen z CLI.\n";
    exit(1);
}

const SCRIPT_PREFIX = '[admin-voucher-dl-tests]';

require_once __DIR__ . '/_test_helpers.php';

function captureChildResponse(array $server, array $get, array $env): array
{
    $command = [
        PHP_BINARY,
        __FILE__,
        '--child',
    ];

    $childEnv = array_merge($_ENV, $env, [
        'PPSTUDIO_ADMIN_VOUCHER_DL_CHILD' => '1',
        'PPSTUDIO_ADMIN_VOUCHER_DL_SERVER' => json_encode($server, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'PPSTUDIO_ADMIN_VOUCHER_DL_GET' => json_encode($get, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    return ppstudioCliTestCaptureJsonChildResponse(SCRIPT_PREFIX, $command, $childEnv, dirname(__DIR__));
}

function childMain(): never
{
    $server = json_decode((string) getenv('PPSTUDIO_ADMIN_VOUCHER_DL_SERVER'), true);
    $get = json_decode((string) getenv('PPSTUDIO_ADMIN_VOUCHER_DL_GET'), true);
    $server = is_array($server) ? $server : [];
    $get = is_array($get) ? $get : [];
    $_SERVER = array_merge($_SERVER, $server);
    $_GET = $get;
    $_REQUEST = array_merge($_GET, $_POST);

    session_start();
    $_SESSION['ppstudio_admin_authenticated'] = true;

    ob_start();
    register_shutdown_function(static function (): void {
        $body = ob_get_clean();
        $code = (int) http_response_code();
        if ($code <= 0) {
            $code = 200;
        }
        echo json_encode([
            'code' => $code,
            'body' => is_string($body) ? $body : '',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    });

    require dirname(__DIR__) . '/admin-voucher-dl.php';
}

$argvCopy = $argv;
array_shift($argvCopy);
$isChild = in_array('--child', $argvCopy, true);

if ($isChild) {
    childMain();
}

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/config/app.php';
require dirname(__DIR__) . '/includes/functions.php';
require dirname(__DIR__) . '/includes/security.php';
require dirname(__DIR__) . '/includes/settings.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$storageDir = ppstudioCliTestTempSecurityStorageDir(SCRIPT_PREFIX, 'ppstudio-admin-voucher-dl-');
$voucherVerifySecret = 'admin-voucher-dl-' . bin2hex(random_bytes(16));
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
$recipientName = 'Test Recipient ' . $token;
$expiresAt = (new DateTimeImmutable('+30 days'))->format('Y-m-d');
$statement = $connection->prepare(
    'INSERT INTO poukazy (kod, puvodni_hodnota, zustatek, status, issued_at, expires_at, recipient_name, note)
     VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)'
);
$originalValue = 1500.0;
$remainingValue = 1500.0;
$status = 'aktivni';
$note = 'admin voucher dl smoke test ' . $token;
$statement->bind_param('sddssss', $code, $originalValue, $remainingValue, $status, $expiresAt, $recipientName, $note);
$statement->execute();
$voucherId = (int) $connection->insert_id;
$statement->close();

try {
    startSecureSession();
    session_unset();

    $response = captureChildResponse(
        [
            'REQUEST_METHOD' => 'GET',
            'HTTP_HOST' => 'voucher-tests.local',
        ],
        [
            'id' => $voucherId,
        ],
        [
            'PPSTUDIO_SECURITY_STORAGE' => $storageDir,
            'PPSTUDIO_VOUCHER_VERIFY_SECRET' => $voucherVerifySecret,
            'PPSTUDIO_ACTION_SECRET' => $voucherVerifySecret,
            'HTTP_HOST' => 'voucher-tests.local',
            'HTTPS' => 'off',
        ]
    );

    ppstudioCliTestAssertSame(SCRIPT_PREFIX, 200, (int) ($response['code'] ?? 0), 'admin-voucher-dl ma vratit HTTP 200.');
    ppstudioCliTestAssertContains(SCRIPT_PREFIX, 'Dárkový poukaz', (string) ($response['body'] ?? ''), 'admin-voucher-dl ma obsahovat titul poukazu.');
    ppstudioCliTestAssertContains(SCRIPT_PREFIX, 'Tisk / Uložit jako PDF', (string) ($response['body'] ?? ''), 'admin-voucher-dl ma obsahovat tiskovou akci.');
    ppstudioCliTestAssertContains(SCRIPT_PREFIX, $code, (string) ($response['body'] ?? ''), 'admin-voucher-dl ma zobrazit kod poukazu.');

    echo SCRIPT_PREFIX . ' [OK] Admin voucher DL smoke test passed.' . PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    ppstudioCliTestFail(SCRIPT_PREFIX, 'Exception: ' . $exception->getMessage());
} finally {
    $delete = $connection->prepare('DELETE FROM poukazy WHERE id = ?');
    $delete->bind_param('i', $voucherId);
    $delete->execute();
    $delete->close();
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
