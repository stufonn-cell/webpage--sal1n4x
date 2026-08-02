<?php

use PsiClinic\Domain\Notes;
use PsiClinic\Domain\Patients;
use PsiClinic\Support\Icons;

$pageTitle = 'Nota de sesion';
$isLocked = (int) $note['is_locked'] === 1;
$sections = [
    'Subjetivo / Datos' => $note['subjective'],
    'Objetivo' => $note['objective'],
    'Analisis' => $note['assessment'],
    'Plan' => $note['plan'],
    'Tarea entre sesiones' => $note['homework'],
];
?>
<div class="page-head">
    <div>
        <h1>Sesion #<?= (int) $note['session_number'] ?></h1>
        <p class="page-head__subtitle">
            <a href="/pacientes/<?= (int) $note['patient_id'] ?>"><?= e($note['first_name'] . ' ' . $note['last_name']) ?></a>
            · <?= e($note['record_number']) ?> · <?= e(formatDate((string) $note['session_date'])) ?>
        </p>
    </div>
    <div class="flex gap-1">
        <a class="btn" href="/notas/<?= (int) $note['id'] ?>/imprimir" target="_blank"><?= Icons::render('print', 16) ?> Imprimir</a>
        <?php if (!$isLocked): ?>
            <a class="btn" href="/notas/<?= (int) $note['id'] ?>/editar"><?= Icons::render('edit', 16) ?> Editar</a>
            <form method="post" action="/notas/<?= (int) $note['id'] ?>/firmar" data-confirm="Al firmar, la nota queda bloqueada. Continuar?">
                <?= csrf() ?>
                <button class="btn btn--primary" type="submit"><?= Icons::render('shield', 16) ?> Firmar</button>
            </form>
        <?php else: ?>
            <span class="badge badge--success"><?= Icons::render('check', 14) ?> Firmada el <?= e(formatDateTime($note['locked_at'])) ?></span>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid--sidebar">
    <div class="card">
        <div class="card__head">
            <h2 class="card__title"><?= e(Notes::FORMATS[$note['format']]) ?></h2>
            <span class="badge badge--<?= $note['risk_level'] === 'high' ? 'danger' : ($note['risk_level'] === 'moderate' ? 'warning' : 'success') ?>">
                Riesgo: <?= e(Patients::RISK_LEVELS[$note['risk_level']]) ?>
            </span>
        </div>
        <div class="card__body">
            <?php foreach ($sections as $title => $body): ?>
                <?php if (trim((string) $body) === '') { continue; } ?>
                <section class="mb-3">
                    <h3><?= e($title) ?></h3>
                    <p class="text-soft" style="white-space:pre-line"><?= e($body) ?></p>
                </section>
            <?php endforeach; ?>
        </div>
    </div>

    <div>
        <div class="card mb-2">
            <div class="card__head"><h2 class="card__title">Detalle</h2></div>
            <div class="card__body">
                <dl class="definition">
                    <dt>Profesional</dt><dd><?= e($note['author_name']) ?></dd>
                    <dt>Registro</dt><dd><?= e($note['license_number'] ?: '-') ?></dd>
                    <dt>Intervenciones</dt><dd><?= e($note['interventions'] ?: '-') ?></dd>
                    <dt>Animo</dt><dd><?= $note['mood_score'] === null ? '-' : (int) $note['mood_score'] . ' / 10' ?></dd>
                    <dt>Creada</dt><dd><?= e(formatDateTime((string) $note['created_at'])) ?></dd>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card__body">
                <p class="text-sm text-soft mb-0">
                    Las notas sin firmar pueden editarse durante <?= (int) $lockHours ?> horas segun la configuracion de la clinica.
                    Toda apertura y modificacion queda registrada en la auditoria.
                </p>
            </div>
        </div>
    </div>
</div>
