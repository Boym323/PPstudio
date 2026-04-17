<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

final class AdminLiteViewStateFactory
{
    private AdminViewStateFactory $adminViewStateFactory;

    public function __construct(?AdminViewStateFactory $adminViewStateFactory = null)
    {
        $this->adminViewStateFactory = $adminViewStateFactory ?? new AdminViewStateFactory();
    }

    /**
     * @param array<string, mixed> $get
     * @return array<string, mixed>
     */
    public function create(array $get, string $error = ''): array
    {
        return array_replace($this->adminViewStateFactory->create($get, $error), [
            'allowedAdminTabs' => [
                'dashboard',
                'dostupnost',
                'rezervace-list',
                'sluzby-admin',
            ],
            'adminBasePath' => '/admin-lite.php',
        ]);
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->create([]));
    }
}
