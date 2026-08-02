<?php
/** @var string $content */
$theme = PsiClinic\Core\Session::get('theme', 'light');
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'PsiClinic') ?></title>
    <link rel="icon" type="image/svg+xml" href="/assets/img/logo.svg">
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body>
<?= $content ?>
<script src="/assets/js/app.js" defer></script>
</body>
</html>
