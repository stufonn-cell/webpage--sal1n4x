<?php

use PsiClinic\Support\Icons;
?>
<header class="topbar">
    <button class="btn btn--ghost btn--icon sidebar-toggle" type="button" data-sidebar-toggle aria-label="Menu">
        <?= Icons::render('dashboard', 18) ?>
    </button>

    <div class="topbar__search">
        <?= Icons::render('search', 17) ?>
        <input type="search" placeholder="Buscar pacientes, notas, historias..." data-global-search autocomplete="off">
        <span class="topbar__hint">Ctrl K</span>
        <div class="search-results" data-search-results></div>
    </div>

    <div class="topbar__actions">
        <a class="btn btn--primary btn--sm" href="/pacientes/nuevo"><?= Icons::render('plus', 16) ?> Nuevo paciente</a>
        <a class="btn btn--sm" href="/agenda/nueva"><?= Icons::render('calendar', 16) ?> Agendar</a>

        <button class="btn btn--ghost btn--icon" type="button" data-theme-toggle aria-label="Cambiar tema">
            <?= Icons::render($theme === 'dark' ? 'sun' : 'moon', 18) ?>
        </button>

        <a class="btn btn--ghost btn--icon" href="/perfil" aria-label="Perfil">
            <span class="avatar avatar--sm"><?= e(initials((string) ($user['full_name'] ?? 'U'))) ?></span>
        </a>

        <form method="post" action="/logout">
            <?= csrf() ?>
            <button class="btn btn--ghost btn--icon" type="submit" aria-label="Cerrar sesion">
                <?= Icons::render('logout', 18) ?>
            </button>
        </form>
    </div>
</header>
