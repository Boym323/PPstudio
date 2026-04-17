<?php
declare(strict_types=1);

namespace PPStudio\Service;

use mysqli;
use PPStudio\Http\Controller\Admin\AdminVoucherDataLoader;
use PPStudio\Http\Controller\Admin\AdminVoucherPostActionHandler;
use PPStudio\Repository\VoucherRepository;

final class AdminVoucherModule
{
    private ?AdminVoucherCatalogService $catalogService = null;

    private ?AdminVoucherDataLoader $dataLoader = null;

    private ?AdminVoucherPostActionHandler $postActionHandler = null;

    private ?VoucherRepository $voucherRepository = null;

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
            new AdminVoucherBatchGenerateUseCase($this->voucherRepository()),
            new AdminVoucherCreateUseCase($this->voucherRepository()),
            new AdminVoucherEmailSendUseCase($this->voucherRepository(), $this->emailConfig, $this->siteSettings),
            new AdminVoucherRedeemUseCase($this->connection, $this->voucherRepository())
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
}
