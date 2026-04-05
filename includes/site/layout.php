<!DOCTYPE html>
<html lang="cs">
<head>
    <?php
    $cssVersion = (string) (@filemtime(__DIR__ . '/../../frontend/site.css') ?: time());
    $jsVersion = (string) (@filemtime(__DIR__ . '/../../frontend/site.js') ?: time());
    ?>
    <meta charset="UTF-8">
    <link rel="apple-touch-icon" sizes="180x180" href="/frontend/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/frontend/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/frontend/favicon/favicon-16x16.png">
    <link rel="manifest" href="/frontend/favicon/site.webmanifest">
    <link rel="shortcut icon" href="/frontend/favicon/favicon.ico">
    <link rel="icon" type="image/png" sizes="192x192" href="/frontend/favicon/android-chrome-192x192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/frontend/favicon/android-chrome-512x512.png">
    <meta name="theme-color" content="#f7f1e8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= escape($description) ?>">
    <meta name="keywords" content="kosmetický salon Zlín, kosmetika, péče o pleť, lash lifting, laminace obočí, kosmetické poradenství, PP Studio, Interhotel Zlín">
    <title><?= escape($title) ?></title>
    <meta property="og:title" content="<?= escape($title) ?>">
    <meta property="og:description" content="<?= escape($description) ?>">
    <meta property="og:image" content="https://www.ppstudio.cz/frontend/Paji.jpeg">
    <meta property="og:url" content="https://www.ppstudio.cz/">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="cs_CZ">
    <link rel="canonical" href="https://www.ppstudio.cz/">

    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"></noscript>
    <link rel="stylesheet" href="/frontend/site.css?v=<?= escape($cssVersion) ?>">

</head>
<body>
    <?php include __DIR__ . '/partials/nav.php'; ?>

    <main>
        <?php include $template; ?>
    </main>

    <?php include __DIR__ . '/partials/footer.php'; ?>

    <script src="/frontend/site.js?v=<?= escape($jsVersion) ?>" defer></script>
</body>
</html>
