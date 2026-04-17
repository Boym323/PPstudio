<?php
declare(strict_types=1);

namespace PPStudio\Service;

use mysqli;
use PPStudio\Http\Controller\Admin\AdminVoucherDataLoader;
use PPStudio\Http\Controller\Admin\AdminVoucherPostActionHandler;
use PPStudio\Repository\VoucherRepository;

final class AdminVoucherModule
{
    private ?AdminVoucherHelper $voucherHelper = null;

    private ?AdminVoucherCatalogService $catalogService = null;

    private ?AdminVoucherDataLoader $dataLoader = null;

    private ?AdminVoucherPostActionHandler $postActionHandler = null;

    private ?VoucherRepository $voucherRepository = null;

    private ?MailerIntegrationService $mailerIntegrationService = null;

    public function __construct(
        private mysqli $connection,
        private array $emailConfig = [],
        private array $siteSettings = []
    ) {
    }

    public function dataLoader(): AdminVoucherDataLoader
    {
        if ($this->dataLoader instanceof AdminVoucherDataLoader) {
            return $this->dataLoader;
        }

        $this->dataLoader = new AdminVoucherDataLoader($this->voucherRepository());

        return $this->dataLoader;
    }

    public function catalogService(): AdminVoucherCatalogService
    {
        if ($this->catalogService instanceof AdminVoucherCatalogService) {
            return $this->catalogService;
        }

        $this->catalogService = new AdminVoucherCatalogService();

        return $this->catalogService;
    }

    public function postActionHandler(): AdminVoucherPostActionHandler
    {
        if ($this->postActionHandler instanceof AdminVoucherPostActionHandler) {
            return $this->postActionHandler;
        }

        $this->postActionHandler = new AdminVoucherPostActionHandler(
            new AdminVoucherBatchGenerateUseCase($this->voucherRepository(), $this->voucherHelper()),
            new AdminVoucherCreateUseCase($this->voucherRepository(), $this->voucherHelper()),
            new AdminVoucherEmailSendUseCase(
                $this->voucherRepository(),
                $this->mailerIntegrationService(),
                $this->siteSettings,
                $this->voucherHelper()
            ),
            new AdminVoucherRedeemUseCase($this->connection, $this->voucherRepository(), $this->voucherHelper())
        );

        return $this->postActionHandler;
    }

    public function voucherRepository(): VoucherRepository
    {
        if ($this->voucherRepository instanceof VoucherRepository) {
            return $this->voucherRepository;
        }

        $this->voucherRepository = new VoucherRepository($this->connection);

        return $this->voucherRepository;
    }

    public function mailerIntegrationService(): MailerIntegrationService
    {
        if ($this->mailerIntegrationService instanceof MailerIntegrationService) {
            return $this->mailerIntegrationService;
        }

        $this->mailerIntegrationService = new MailerIntegrationService($this->emailConfig);

        return $this->mailerIntegrationService;
    }

    private function voucherHelper(): AdminVoucherHelper
    {
        if ($this->voucherHelper instanceof AdminVoucherHelper) {
            return $this->voucherHelper;
        }

        $this->voucherHelper = new AdminVoucherHelper();

        return $this->voucherHelper;
    }
}
