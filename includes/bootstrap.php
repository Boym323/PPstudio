<?php
declare(strict_types=1);

$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'PPStudio\\';

        if (! str_starts_with($class, $prefix)) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';

        if (is_file($path)) {
            require_once $path;
        }
    });
}

if (! function_exists('ppstudioSecurityFacade')) {
    function ppstudioSecurityFacade(): \PPStudio\Security\SecurityFacade
    {
        static $facade = null;

        if (! $facade instanceof \PPStudio\Security\SecurityFacade) {
            $facade = new \PPStudio\Security\SecurityFacade();
        }

        return $facade;
    }
}

if (! function_exists('isHttpsRequest')) {
    function isHttpsRequest(): bool
    {
        return ppstudioSecurityFacade()->isHttpsRequest();
    }
}

if (! function_exists('startSecureSession')) {
    function startSecureSession(): void
    {
        ppstudioSecurityFacade()->startSecureSession();
    }
}

if (! function_exists('getClientIpAddress')) {
    function getClientIpAddress(): string
    {
        return ppstudioSecurityFacade()->getClientIpAddress();
    }
}

if (! function_exists('getCsrfToken')) {
    function getCsrfToken(): string
    {
        return ppstudioSecurityFacade()->getCsrfToken();
    }
}

if (! function_exists('csrfInputField')) {
    function csrfInputField(string $fieldName = '_csrf'): string
    {
        return ppstudioSecurityFacade()->csrfInputField($fieldName);
    }
}

if (! function_exists('isValidCsrfToken')) {
    function isValidCsrfToken(?string $token): bool
    {
        return ppstudioSecurityFacade()->isValidCsrfToken($token);
    }
}

if (! function_exists('storageDir')) {
    function storageDir(): string
    {
        return ppstudioSecurityFacade()->storageDir();
    }
}

if (! function_exists('loginThrottleState')) {
    function loginThrottleState(
        string $scope,
        string $ipAddress,
        string $username,
        int $limit = 5,
        int $windowSeconds = 900
    ): array {
        return ppstudioSecurityFacade()->loginThrottleState($scope, $ipAddress, $username, $limit, $windowSeconds);
    }
}

if (! function_exists('loginThrottleRegisterFailure')) {
    function loginThrottleRegisterFailure(
        string $scope,
        string $ipAddress,
        string $username,
        int $limit = 5,
        int $windowSeconds = 900
    ): array {
        return ppstudioSecurityFacade()->loginThrottleRegisterFailure($scope, $ipAddress, $username, $limit, $windowSeconds);
    }
}

if (! function_exists('loginThrottleReset')) {
    function loginThrottleReset(string $scope, string $ipAddress, string $username): void
    {
        ppstudioSecurityFacade()->loginThrottleReset($scope, $ipAddress, $username);
    }
}

if (! function_exists('voucherVerifySecret')) {
    function voucherVerifySecret(): string
    {
        return ppstudioSecurityFacade()->voucherVerifySecret();
    }
}

if (! function_exists('buildVoucherVerifySignature')) {
    function buildVoucherVerifySignature(string $secret, int $voucherId, string $voucherCode): string
    {
        return ppstudioSecurityFacade()->buildVoucherVerifySignature($secret, $voucherId, $voucherCode);
    }
}

if (! function_exists('isValidVoucherVerifySignature')) {
    function isValidVoucherVerifySignature(string $secret, int $voucherId, string $voucherCode, string $signature): bool
    {
        return ppstudioSecurityFacade()->isValidVoucherVerifySignature($secret, $voucherId, $voucherCode, $signature);
    }
}

if (! function_exists('buildVoucherVerifyUrl')) {
    function buildVoucherVerifyUrl(array $siteSettings, int $voucherId, string $voucherCode, ?string $secret = null): string
    {
        return ppstudioSecurityFacade()->buildVoucherVerifyUrl($siteSettings, $voucherId, $voucherCode, $secret);
    }
}

if (! function_exists('buildVoucherViewUrl')) {
    function buildVoucherViewUrl(array $siteSettings, int $voucherId, string $voucherCode, ?string $secret = null): string
    {
        return ppstudioSecurityFacade()->buildVoucherViewUrl($siteSettings, $voucherId, $voucherCode, $secret);
    }
}

if (! function_exists('buildVoucherSignedPublicUrl')) {
    function buildVoucherSignedPublicUrl(
        array $siteSettings,
        int $voucherId,
        string $voucherCode,
        string $path,
        ?string $secret = null
    ): string {
        return ppstudioSecurityFacade()->buildVoucherSignedPublicUrl($siteSettings, $voucherId, $voucherCode, $path, $secret);
    }
}

if (! function_exists('requirePublicSiteAccessOrPrompt')) {
    function requirePublicSiteAccessOrPrompt(): void
    {
        ppstudioSecurityFacade()->publicSiteLockService()->requireAccessOrPrompt($_SERVER, $_POST);
    }
}

if (! function_exists('requirePublicSiteAccessOrJsonError')) {
    function requirePublicSiteAccessOrJsonError(): void
    {
        ppstudioSecurityFacade()->publicSiteLockService()->requireAccessOrJsonError();
    }
}
