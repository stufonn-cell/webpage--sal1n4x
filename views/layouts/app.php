<?php

use PsiClinic\Core\App;
use PsiClinic\Core\Auth;
use PsiClinic\Core\Request;
use PsiClinic\Core\Session;
use PsiClinic\Core\View;

$user = Auth::user() ?? [];
$theme = (string) ($user['theme'] ?? Session::get('theme', 'light'));
$path = Request::capture()->path();
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($pageTitle ?? 'Panel') . ' - ' . ($appName ?? 'PsiClinic')) ?></title>
    <link rel="icon" type="image/svg+xml" href="/assets/img/logo.svg">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="app-shell">
    <?= View::partial('partials/sidebar', ['path' => $path, 'user' => $user]) ?>

    <div class="main">
        <?= View::partial('partials/topbar', ['user' => $user, 'theme' => $theme]) ?>

        <main class="content">
            <?= $content ?>
        </main>

        <?= View::partial('partials/footer') ?>
    </div>
</div>

<div class="flash-stack" data-flash-stack>
    <?php foreach (App::flash() as $message): ?>
        <div class="flash flash--<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
    <?php endforeach; ?>
</div>

<script src="/assets/js/app.js" defer></script>
</body>
</html>
