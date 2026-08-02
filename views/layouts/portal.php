<?php

use PsiClinic\Core\App;
use PsiClinic\Core\Auth;
use PsiClinic\Core\Request;
use PsiClinic\Core\Session;
use PsiClinic\Support\Icons;

$user = Auth::user() ?? [];
$theme = (string) ($user['theme'] ?? Session::get('theme', 'light'));
$path = Request::capture()->path();

$links = [
    '/portal' => ['dashboard', 'Inicio'],
    '/portal/citas' => ['calendar', 'Mis citas'],
    '/portal/cuestionarios' => ['clipboard', 'Cuestionarios'],
    '/portal/documentos' => ['document', 'Documentos'],
];
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($pageTitle ?? 'Portal del paciente')) ?></title>
    <link rel="icon" type="image/svg+xml" href="/assets/img/logo.svg">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="main" style="margin-left:0">
    <header class="topbar">
        <a class="flex-center" href="/portal" style="gap:.6rem;color:var(--text)">
            <img src="/assets/img/logo.svg" width="32" height="32" alt="">
            <strong>Portal del paciente</strong>
        </a>

        <nav class="flex-center" style="gap:.2rem;margin-left:1.4rem">
            <?php foreach ($links as $href => [$icon, $label]): ?>
                <a class="nav-item<?= $path === $href ? ' is-active' : '' ?>" href="<?= e($href) ?>">
                    <?= Icons::render($icon, 17) ?><span><?= e($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="topbar__actions">
            <button class="btn btn--ghost btn--icon" type="button" data-theme-toggle aria-label="Cambiar tema">
                <?= Icons::render($theme === 'dark' ? 'sun' : 'moon', 18) ?>
            </button>
            <span class="avatar avatar--sm"><?= e(initials((string) ($user['full_name'] ?? 'P'))) ?></span>
            <form method="post" action="/logout">
                <?= csrf() ?>
                <button class="btn btn--ghost btn--sm" type="submit"><?= Icons::render('logout', 16) ?> Salir</button>
            </form>
        </div>
    </header>

    <main class="content" style="max-width:1080px;margin:0 auto;width:100%">
        <?= $content ?>
    </main>

    <?= PsiClinic\Core\View::partial('partials/footer') ?>
</div>

<div class="flash-stack" data-flash-stack>
    <?php foreach (App::flash() as $message): ?>
        <div class="flash flash--<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
    <?php endforeach; ?>
</div>

<script src="/assets/js/app.js" defer></script>
</body>
</html>
