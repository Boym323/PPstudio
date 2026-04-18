<?php
declare(strict_types=1);

$view = is_array($__view ?? null) ? $__view : [];
$resolvedPageTitle = (string) ($view['page_title'] ?? ($page_title ?? 'PP Studio'));
$resolvedInlineStyles = (string) ($view['inline_styles'] ?? ($inline_styles ?? ''));
$resolvedContentTemplate = (string) ($view['content_template'] ?? ($content_template ?? ''));
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= \PPStudio\Support\ViewHelper::escape($resolvedPageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/site.css">
    <?php if ($resolvedInlineStyles !== ''): ?>
        <style>
<?= $resolvedInlineStyles ?>
        </style>
    <?php endif; ?>
</head>
<body>
    <?php require __DIR__ . '/../' . $resolvedContentTemplate . '.php'; ?>
</body>
</html>
