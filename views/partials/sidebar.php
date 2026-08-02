<?php

use PsiClinic\Core\Auth;
use PsiClinic\Domain\Settings;
use PsiClinic\Support\Icons;

$groups = [
    'Clinica' => [
        ['/dashboard', 'dashboard', 'Panel'],
        ['/pacientes', 'patients', 'Pacientes'],
        ['/agenda', 'calendar', 'Agenda'],
        ['/notas', 'notes', 'Notas clinicas'],
        ['/evaluaciones', 'chart', 'Evaluaciones'],
    ],
    'Gestion' => [
        ['/consentimientos', 'clipboard', 'Consentimientos'],
        ['/documentos', 'document', 'Documentos'],
        ['/facturacion', 'invoice', 'Facturacion'],
    ],
    'Sistema' => [
        ['/ajustes', 'settings', 'Configuracion'],
        ['/ajustes/usuarios', 'user', 'Usuarios'],
        ['/ajustes/auditoria', 'shield', 'Auditoria'],
    ],
];
?>
<aside class="sidebar" data-sidebar>
    <div class="sidebar__brand">
        <img src="/assets/img/logo.svg" width="36" height="36" alt="">
        <span>
            <span class="sidebar__brand-name"><?= e(Settings::get('clinic_name', 'PsiClinic')) ?></span>
            <span class="sidebar__brand-tag">Historia clinica</span>
        </span>
    </div>

    <nav class="sidebar__nav">
        <?php foreach ($groups as $title => $items): ?>
            <div class="sidebar__group">
                <div class="sidebar__group-title"><?= e($title) ?></div>
                <?php foreach ($items as [$href, $icon, $label]): ?>
                    <a class="nav-item<?= $path === $href || ($href !== '/dashboard' && str_starts_with($path, $href) && $href !== '/ajustes') ? ' is-active' : '' ?>"
                       href="<?= e($href) ?>">
                        <?= Icons::render($icon, 18) ?>
                        <span><?= e($label) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar__footer">
        <span class="avatar avatar--sm"><?= e(initials((string) ($user['full_name'] ?? 'U'))) ?></span>
        <span style="min-width:0">
            <span class="text-sm fw-600" style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <?= e($user['full_name'] ?? '') ?>
            </span>
            <span class="text-xs text-muted"><?= e(ucfirst((string) Auth::role())) ?></span>
        </span>
    </div>
</aside>
