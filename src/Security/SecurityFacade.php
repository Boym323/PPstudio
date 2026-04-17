<?php
declare(strict_types=1);

namespace PPStudio\Security;

final class SecurityFacade
{
    private ?SessionService $sessionService = null;

    private ?CsrfService $csrfService = null;

    private ?RequestSecurityService $requestSecurityService = null;

    private ?SecurityEventLogger $securityEventLogger = null;

    private ?ReservationAntispamService $reservationAntispamService = null;

    private ?PublicSiteLockService $publicSiteLockService = null;

    public function isHttpsRequest(): bool
    {
        return $this->sessionService()->isHttpsRequest();
    }

    public function startSecureSession(): void
    {
        $this->sessionService()->start();
    }

    public function getClientIpAddress(): string
    {
        return $this->requestSecurityService()->clientIpAddress();
    }

    public function getCsrfToken(): string
    {
        return $this->csrfService()->token();
    }

    public function csrfInputField(string $fieldName = '_csrf'): string
    {
        return $this->csrfService()->inputField($fieldName);
    }

    public function isValidCsrfToken(?string $token): bool
    {
        return $this->csrfService()->isValid($token);
    }

    public function storageDir(): string
    {
        return $this->requestSecurityService()->storageDir();
    }

    public function loginRateLimitPath(string $scope): string
    {
        return $this->requestSecurityService()->loginRateLimitPath($scope);
    }

    public function loginRateLimitKey(string $scope, string $ipAddress, string $username): string
    {
        return $this->requestSecurityService()->loginRateLimitKey($scope, $ipAddress, $username);
    }

    public function loginThrottleState(
        string $scope,
        string $ipAddress,
        string $username,
        int $limit = 5,
        int $windowSeconds = 900
    ): array {
        return $this->requestSecurityService()->loginThrottleState($scope, $ipAddress, $username, $limit, $windowSeconds);
    }

    public function loginThrottleRegisterFailure(
        string $scope,
        string $ipAddress,
        string $username,
        int $limit = 5,
        int $windowSeconds = 900
    ): array {
        return $this->requestSecurityService()->loginThrottleRegisterFailure($scope, $ipAddress, $username, $limit, $windowSeconds);
    }

    public function loginThrottleReset(string $scope, string $ipAddress, string $username): void
    {
        $this->requestSecurityService()->loginThrottleReset($scope, $ipAddress, $username);
    }

    public function voucherVerifySecret(): string
    {
        return $this->requestSecurityService()->voucherVerifySecret();
    }

    public function buildVoucherVerifySignature(string $secret, int $voucherId, string $voucherCode): string
    {
        return $this->requestSecurityService()->buildVoucherVerifySignature($secret, $voucherId, $voucherCode);
    }

    public function isValidVoucherVerifySignature(string $secret, int $voucherId, string $voucherCode, string $signature): bool
    {
        return $this->requestSecurityService()->isValidVoucherVerifySignature($secret, $voucherId, $voucherCode, $signature);
    }

    public function buildVoucherVerifyUrl(array $siteSettings, int $voucherId, string $voucherCode, ?string $secret = null): string
    {
        return $this->buildVoucherSignedPublicUrl($siteSettings, $voucherId, $voucherCode, '/voucher/verify', $secret);
    }

    public function buildVoucherViewUrl(array $siteSettings, int $voucherId, string $voucherCode, ?string $secret = null): string
    {
        return $this->buildVoucherSignedPublicUrl($siteSettings, $voucherId, $voucherCode, '/voucher/view', $secret);
    }

    public function buildVoucherSignedPublicUrl(array $siteSettings, int $voucherId, string $voucherCode, string $path, ?string $secret = null): string
    {
        return $this->requestSecurityService()->buildVoucherSignedPublicUrl($siteSettings, $voucherId, $voucherCode, $path, $secret);
    }

    public function securityEventLogger(): SecurityEventLogger
    {
        if (! $this->securityEventLogger instanceof SecurityEventLogger) {
            $this->securityEventLogger = new SecurityEventLogger($this->requestSecurityService());
        }

        return $this->securityEventLogger;
    }

    public function reservationAntispamService(): ReservationAntispamService
    {
        if (! $this->reservationAntispamService instanceof ReservationAntispamService) {
            $this->reservationAntispamService = new ReservationAntispamService(
                $this->sessionService(),
                $this->requestSecurityService(),
                $this->securityEventLogger()
            );
        }

        return $this->reservationAntispamService;
    }

    public function publicSiteLockService(): PublicSiteLockService
    {
        if (! $this->publicSiteLockService instanceof PublicSiteLockService) {
            $this->publicSiteLockService = new PublicSiteLockService(
                $this->sessionService(),
                $this->csrfService(),
                $this->requestSecurityService()
            );
        }

        return $this->publicSiteLockService;
    }

    public function sessionService(): SessionService
    {
        if (! $this->sessionService instanceof SessionService) {
            $this->sessionService = new SessionService();
        }

        return $this->sessionService;
    }

    public function csrfService(): CsrfService
    {
        if (! $this->csrfService instanceof CsrfService) {
            $this->csrfService = new CsrfService($this->sessionService());
        }

        return $this->csrfService;
    }

    public function requestSecurityService(): RequestSecurityService
    {
        if (! $this->requestSecurityService instanceof RequestSecurityService) {
            $this->requestSecurityService = new RequestSecurityService($this->sessionService());
        }

        return $this->requestSecurityService;
    }
}
