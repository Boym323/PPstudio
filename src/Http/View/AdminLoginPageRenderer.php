<?php
declare(strict_types=1);

namespace PPStudio\Http\View;

final class AdminLoginPageRenderer
{
    public function render(string $loginError, string $pageTitle, string $eyebrow, string $heading): never
    {
        $adminCssVersion = (string) (@filemtime(__DIR__ . '/../../../assets/css/admin.css') ?: time());

        include __DIR__ . '/Templates/admin-login-page.php';
        exit;
    }
}
