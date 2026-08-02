<?php

use PsiClinic\Domain\Notes;
use PsiClinic\Domain\Settings;

$sections = [
    'Subjetivo / Datos' => $note['subjective'],
    'Objetivo' => $note['objective'],
    'Analisis' => $note['assessment'],
    'Plan' => $note['plan'],
    'Tarea entre sesiones' => $note['homework'],
];
?>
<div style="max-width:820px;margin:0 auto;padding:2.5rem 2rem;background:var(--surface);color:var(--text)">
    <header style="display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid var(--border);padding-bottom:1rem;margin-bottom:1.5rem">
        <div class="flex-center" style="gap:.8rem">
            <img src="/assets/img/logo.svg" width="42" height="42" alt="">
            <div>
                <strong style="font-size:1.1rem"><?= e(Settings::get('clinic_name')) ?></strong>
                <div class="text-xs text-muted"><?= e(Settings::get('clinic_tagline')) ?></div>
            </div>
        </div>
        <div class="text-right text-xs text-muted">
            <div>Nota de sesion #<?= (int) $note['session_number'] ?></div>
            <div><?= e(formatDate((string) $note['session_date'])) ?></div>
        </div>
    </header>

    <h1 style="font-size:1.3rem"><?= e($note['first_name'] . ' ' . $note['last_name']) ?></h1>
    <p class="text-sm text-muted">
        Historia <?= e($note['record_number']) ?> · Documento <?= e($note['document_id'] ?: '-') ?> ·
        <?= e(ageFrom($note['birth_date'])) ?> anos
    </p>

    <p class="text-sm"><strong>Formato:</strong> <?= e(Notes::FORMATS[$note['format']]) ?></p>

    <?php foreach ($sections as $title => $body): ?>
        <?php if (trim((string) $body) === '') { continue; } ?>
        <section style="margin-bottom:1.2rem">
            <h3 style="font-size:.95rem;border-bottom:1px solid var(--border);padding-bottom:.25rem"><?= e($title) ?></h3>
            <p class="text-sm" style="white-space:pre-line"><?= e($body) ?></p>
        </section>
    <?php endforeach; ?>

    <footer style="margin-top:3rem;border-top:1px solid var(--border);padding-top:1rem">
        <p class="text-sm mb-0"><?= e($note['author_name']) ?></p>
        <p class="text-xs text-muted">Registro profesional <?= e($note['license_number'] ?: 'no registrado') ?></p>
        <p class="text-xs text-muted">Documento generado el <?= e(formatDateTime(date('Y-m-d H:i:s'))) ?> por PsiClinic. Hecho por Salinas (github.com/stufonn-cell).</p>
    </footer>

    <div class="no-print text-center mt-3">
        <button class="btn btn--primary" onclick="window.print()">Imprimir</button>
    </div>
</div>
