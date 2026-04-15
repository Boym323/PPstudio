<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use PPStudio\Security\CsrfService;
use PPStudio\Security\RequestSecurityService;
use PPStudio\Security\SessionService;

function ppstudioSessionService(): SessionService
{
    static $service = null;

    if (! $service instanceof SessionService) {
        $service = new SessionService();
    }

    return $service;
}

function ppstudioCsrfService(): CsrfService
{
    static $service = null;

    if (! $service instanceof CsrfService) {
        $service = new CsrfService(ppstudioSessionService());
    }

    return $service;
}

function ppstudioRequestSecurityService(): RequestSecurityService
{
    static $service = null;

    if (! $service instanceof RequestSecurityService) {
        $service = new RequestSecurityService(ppstudioSessionService());
    }

    return $service;
}

function startSecureSession(): void
{
    ppstudioSessionService()->start();
}

function getClientIpAddress(): string
{
    return ppstudioRequestSecurityService()->clientIpAddress();
}

function getCsrfToken(): string
{
    return ppstudioCsrfService()->token();
}

function csrfInputField(string $fieldName = '_csrf'): string
{
    return ppstudioCsrfService()->inputField($fieldName);
}

function isValidCsrfToken(?string $token): bool
{
    return ppstudioCsrfService()->isValid($token);
}

function ppstudioSecurityStorageDir(): string
{
    return ppstudioRequestSecurityService()->storageDir();
}

function ppstudioLoginThrottleState(
    string $scope,
    string $ipAddress,
    string $username,
    int $limit = 5,
    int $windowSeconds = 900
): array {
    return ppstudioRequestSecurityService()->loginThrottleState($scope, $ipAddress, $username, $limit, $windowSeconds);
}

function ppstudioLoginThrottleRegisterFailure(
    string $scope,
    string $ipAddress,
    string $username,
    int $limit = 5,
    int $windowSeconds = 900
): array {
    return ppstudioRequestSecurityService()->loginThrottleRegisterFailure($scope, $ipAddress, $username, $limit, $windowSeconds);
}

function ppstudioLoginThrottleReset(string $scope, string $ipAddress, string $username): void
{
    ppstudioRequestSecurityService()->loginThrottleReset($scope, $ipAddress, $username);
}

function ppstudioVoucherVerifySecret(): string
{
    return ppstudioRequestSecurityService()->voucherVerifySecret();
}

function buildVoucherVerifySignature(string $secret, int $voucherId, string $voucherCode): string
{
    return ppstudioRequestSecurityService()->buildVoucherVerifySignature($secret, $voucherId, $voucherCode);
}

function isValidVoucherVerifySignature(string $secret, int $voucherId, string $voucherCode, string $signature): bool
{
    return ppstudioRequestSecurityService()->isValidVoucherVerifySignature($secret, $voucherId, $voucherCode, $signature);
}

function buildVoucherVerifyUrl(array $siteSettings, int $voucherId, string $voucherCode, ?string $secret = null): string
{
    return buildVoucherSignedPublicUrl($siteSettings, $voucherId, $voucherCode, '/voucher/verify', $secret);
}

function buildVoucherViewUrl(array $siteSettings, int $voucherId, string $voucherCode, ?string $secret = null): string
{
    return buildVoucherSignedPublicUrl($siteSettings, $voucherId, $voucherCode, '/voucher/view', $secret);
}

function buildVoucherSignedPublicUrl(array $siteSettings, int $voucherId, string $voucherCode, string $path, ?string $secret = null): string
{
    return ppstudioRequestSecurityService()->buildVoucherSignedPublicUrl($siteSettings, $voucherId, $voucherCode, $path, $secret);
}
