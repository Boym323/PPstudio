<?php
declare(strict_types=1);

namespace PPStudio\Support;

use DateTimeImmutable;
use mysqli;
use mysqli_result;
use PPStudio\Database\DatabaseFactory;
use Throwable;

final class AdminVoucherPostTestRunner
{
    public function __construct(
        private readonly string $scriptPrefix = '[admin-voucher-post-tests]'
    ) {
    }

    public function run(): int
    {
        ppstudioCliTestBootstrapBase();

        $storageDir = ppstudioCliTestTempSecurityStorageDir($this->scriptPrefix, 'ppstudio-admin-voucher-post-');
        $voucherVerifySecret = 'admin-voucher-post-' . bin2hex(random_bytes(16));
        $previousEnv = ppstudioCliTestSetEnv([
            'PPSTUDIO_SECURITY_STORAGE' => $storageDir,
            'PPSTUDIO_VOUCHER_VERIFY_SECRET' => $voucherVerifySecret,
            'PPSTUDIO_ACTION_SECRET' => $voucherVerifySecret,
            'HTTP_HOST' => 'voucher-tests.local',
            'HTTPS' => 'off',
        ]);

        $connection = DatabaseFactory::connect();
        $token = bin2hex(random_bytes(4));
        $code = 'IT-VOUCHER-' . strtoupper($token);
        $expiresAt = (new DateTimeImmutable('+60 days'))->format('Y-m-d');
        $recipientName = 'Test Recipient ' . $token;
        $recipientEmail = 'voucher-post-' . $token . '@example.test';
        $note = 'admin voucher post smoke test ' . $token;
        $voucherId = 0;

        try {
            (new \PPStudio\Security\SecurityFacade())->startSecureSession();
            session_unset();

            $createResponse = $this->captureChildResponse(
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

            ppstudioCliTestAssertSame($this->scriptPrefix, 200, (int) ($createResponse['code'] ?? 0), 'create_voucher ma vratit HTTP 200.');
            ppstudioCliTestAssertSame($this->scriptPrefix, 'Poukaz byl uložen.', (string) ($createResponse['message'] ?? ''), 'create_voucher ma potvrdit ulozeni poukazu.');
            ppstudioCliTestAssertSame($this->scriptPrefix, '', (string) ($createResponse['error'] ?? ''), 'create_voucher nema vratit chybu.');

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

            ppstudioCliTestAssertTrue($this->scriptPrefix, is_array($voucherRow) && ($voucherRow['id'] ?? 0) !== null, 'Po create musi existovat ulozeny poukaz.');
            ppstudioCliTestAssertSame($this->scriptPrefix, 1200, (int) ($voucherRow['puvodni_hodnota'] ?? 0), 'Ulozeny poukaz ma mit spravnou hodnotu.');
            ppstudioCliTestAssertSame($this->scriptPrefix, 1200, (int) ($voucherRow['zustatek'] ?? 0), 'Ulozeny poukaz ma mit plny zustatek.');
            ppstudioCliTestAssertSame($this->scriptPrefix, 'aktivni', (string) ($voucherRow['status'] ?? ''), 'Ulozeny poukaz ma mit stav aktivni.');
            ppstudioCliTestAssertSame($this->scriptPrefix, mb_strtolower($recipientEmail), (string) ($voucherRow['recipient_email'] ?? ''), 'Ulozeny poukaz ma mit normalizovany e-mail.');

            $voucherId = (int) ($voucherRow['id'] ?? 0);

            $redeemResponse = $this->captureChildResponse(
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

            ppstudioCliTestAssertSame($this->scriptPrefix, 200, (int) ($redeemResponse['code'] ?? 0), 'redeem_voucher ma vratit HTTP 200.');
            ppstudioCliTestAssertSame($this->scriptPrefix, 'Čerpání poukazu bylo uloženo. Zůstatek: 850 Kč.', (string) ($redeemResponse['message'] ?? ''), 'redeem_voucher ma potvrdit ulozeni čerpání.');
            ppstudioCliTestAssertSame($this->scriptPrefix, '', (string) ($redeemResponse['error'] ?? ''), 'redeem_voucher nema vratit chybu.');

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

            ppstudioCliTestAssertSame($this->scriptPrefix, 850, (int) ($redeemRow['zustatek'] ?? 0), 'Po redeem ma zustatku odpovidat odebrane castce.');
            ppstudioCliTestAssertSame($this->scriptPrefix, 'aktivni', (string) ($redeemRow['status'] ?? ''), 'Po partial redeem ma zustat aktivni stav.');

            $checkTransaction = $connection->prepare('SELECT COUNT(*) AS total FROM poukaz_cerpani WHERE poukaz_id = ?');
            $checkTransaction->bind_param('i', $voucherId);
            $checkTransaction->execute();
            $transactionResult = $checkTransaction->get_result();
            $transactionRow = $transactionResult instanceof mysqli_result ? $transactionResult->fetch_assoc() : [];
            if ($transactionResult instanceof mysqli_result) {
                $transactionResult->free();
            }
            $checkTransaction->close();

            ppstudioCliTestAssertSame($this->scriptPrefix, 1, (int) ($transactionRow['total'] ?? 0), 'Redeem ma ulozit jednu transakci.');

            echo $this->scriptPrefix . ' [OK] Admin voucher POST smoke test passed.' . PHP_EOL;
            return 0;
        } catch (Throwable $exception) {
            ppstudioCliTestFail($this->scriptPrefix, 'Exception: ' . $exception->getMessage());
        } finally {
            if ($voucherId > 0) {
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
    }

    private function captureChildResponse(array $server, array $post, array $voucherForm, array $voucherBatchForm, array $env): array
    {
        $command = [
            PHP_BINARY,
            dirname(__DIR__, 2) . '/scripts/run-admin-voucher-post-tests.php',
            '--child',
        ];

        $childEnv = array_merge($_ENV, $env, [
            'PPSTUDIO_ADMIN_VOUCHER_POST_CHILD' => '1',
            'PPSTUDIO_ADMIN_VOUCHER_POST_SERVER' => json_encode($server, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'PPSTUDIO_ADMIN_VOUCHER_POST_POST' => json_encode($post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'PPSTUDIO_ADMIN_VOUCHER_POST_FORM' => json_encode($voucherForm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'PPSTUDIO_ADMIN_VOUCHER_POST_BATCH_FORM' => json_encode($voucherBatchForm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return ppstudioCliTestCaptureJsonChildResponse($this->scriptPrefix, $command, $childEnv, dirname(__DIR__, 2));
    }
}
