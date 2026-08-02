<?php

use PsiClinic\Core\Auth;
use PsiClinic\Domain\Appointments;
use PsiClinic\Domain\Patients;
use PsiClinic\Support\Chart;
use PsiClinic\Support\Icons;

$pageTitle = 'Panel';
$user = Auth::user() ?? [];
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Buenos dias' : ($hour < 19 ? 'Buenas tardes' : 'Buenas noches');
?>
<div class="page-head">
    <div>
        <h1><?= e($greeting) ?>, <?= e(explode(' ', (string) ($user['full_name'] ?? ''))[0]) ?></h1>
        <p class="page-head__subtitle"><?= e(ucfirst(formatDate(date('Y-m-d'), 'l, d \d\e F \d\e Y'))) ?> · <?= count($today) ?> citas hoy</p>
    </div>
    <div class="flex gap-1">
        <a class="btn" href="/notas/nueva"><?= Icons::render('notes', 16) ?> Nueva nota</a>
        <a class="btn btn--primary" href="/evaluaciones/nueva"><?= Icons::render('clipboard', 16) ?> Aplicar instrumento</a>
    </div>
</div>

<div class="grid grid--4 mb-3">
    <div class="stat">
        <span class="stat__icon"><?= Icons::render('patients', 20) ?></span>
        <span>
            <span class="stat__value"><?= (int) $metrics['active_patients'] ?></span>
            <span class="stat__label">Pacientes activos</span>
        </span>
    </div>
    <div class="stat">
        <span class="stat__icon stat__icon--accent"><?= Icons::render('notes', 20) ?></span>
        <span>
            <span class="stat__value"><?= (int) $metrics['sessions_this_month'] ?></span>
            <span class="stat__label">Sesiones del mes</span>
        </span>
    </div>
    <div class="stat">
        <span class="stat__icon stat__icon--warn"><?= Icons::render('clipboard', 20) ?></span>
        <span>
            <span class="stat__value"><?= (int) $metrics['pending_assessments'] ?></span>
            <span class="stat__label">Cuestionarios pendientes</span>
        </span>
    </div>
    <div class="stat">
        <span class="stat__icon stat__icon--danger"><?= Icons::render('flag', 20) ?></span>
        <span>
            <span class="stat__value"><?= (int) $metrics['high_risk'] ?></span>
            <span class="stat__label">Casos en vigilancia</span>
        </span>
    </div>
</div>

<div class="grid grid--sidebar mb-3">
    <div class="card">
        <div class="card__head">
            <h2 class="card__title">Sesiones por mes</h2>
            <span class="badge badge--brand">Ultimos 6 meses</span>
        </div>
        <div class="card__body"><?= Chart::bars($sessionsSeries, 620, 200) ?></div>
    </div>

    <div class="card">
        <div class="card__head">
            <h2 class="card__title">Nivel de riesgo</h2>
        </div>
        <div class="card__body">
            <?php
            $riskLabels = [];
            foreach ($riskDistribution as $key => $value) {
                $riskLabels[Patients::RISK_LEVELS[$key]] = $value;
            }
            ?>
            <?= Chart::donut($riskLabels, ['#17a673', '#2f7bd9', '#d98207', '#d94848']) ?>
            <div class="chart-legend">
                <?php $palette = ['#17a673', '#2f7bd9', '#d98207', '#d94848']; $i = 0; ?>
                <?php foreach ($riskLabels as $label => $value): ?>
                    <span><i style="background:<?= e($palette[$i++ % 4]) ?>"></i><?= e($label) ?> (<?= (int) $value ?>)</span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="grid grid--3">
    <div class="card">
        <div class="card__head">
            <h2 class="card__title">Agenda de hoy</h2>
            <a class="btn btn--ghost btn--sm" href="/agenda">Ver semana</a>
        </div>
        <div class="card__body card__body--flush">
            <?php if ($today === []): ?>
                <div class="empty"><div class="empty__title">Sin citas hoy</div><p class="text-sm">Aprovecha para actualizar notas pendientes.</p></div>
            <?php else: ?>
                <?php foreach ($today as $appointment): ?>
                    <div class="list-row">
                        <span class="badge badge--brand"><?= e(date('H:i', strtotime((string) $appointment['starts_at']))) ?></span>
                        <span>
                            <span class="fw-600"><?= e($appointment['first_name'] . ' ' . $appointment['last_name']) ?></span>
                            <span class="text-xs text-muted" style="display:block"><?= e(Appointments::MODALITIES[$appointment['modality']]) ?></span>
                        </span>
                        <span class="list-row__meta"><?= e(Appointments::STATUSES[$appointment['status']]) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card__head">
            <h2 class="card__title">Casos en vigilancia</h2>
            <a class="btn btn--ghost btn--sm" href="/pacientes">Ver todos</a>
        </div>
        <div class="card__body card__body--flush">
            <?php if ($riskPatients === []): ?>
                <div class="empty"><div class="empty__title">Sin alertas activas</div><p class="text-sm">Ningun paciente activo con riesgo elevado.</p></div>
            <?php else: ?>
                <?php foreach ($riskPatients as $patient): ?>
                    <a class="list-row" href="/pacientes/<?= (int) $patient['id'] ?>">
                        <span class="avatar avatar--sm"><?= e(initials($patient['first_name'] . ' ' . $patient['last_name'])) ?></span>
                        <span>
                            <span class="fw-600"><?= e($patient['first_name'] . ' ' . $patient['last_name']) ?></span>
                            <span class="text-xs text-muted" style="display:block"><?= e($patient['record_number']) ?></span>
                        </span>
                        <span class="list-row__meta">
                            <span class="badge badge--<?= $patient['risk_level'] === 'high' ? 'danger' : 'warning' ?>">
                                <?= e(Patients::RISK_LEVELS[$patient['risk_level']]) ?>
                            </span>
                        </span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card__head">
            <h2 class="card__title">Cuestionarios pendientes</h2>
            <a class="btn btn--ghost btn--sm" href="/evaluaciones?estado=pending">Ver todos</a>
        </div>
        <div class="card__body card__body--flush">
            <?php if ($pendingAssessments === []): ?>
                <div class="empty"><div class="empty__title">Todo al dia</div><p class="text-sm">No hay cuestionarios sin responder.</p></div>
            <?php else: ?>
                <?php foreach ($pendingAssessments as $assessment): ?>
                    <div class="list-row">
                        <span class="badge"><?= e($assessment['instrument_code']) ?></span>
                        <span class="fw-600"><?= e($assessment['first_name'] . ' ' . $assessment['last_name']) ?></span>
                        <span class="list-row__meta"><?= e(formatDate((string) $assessment['created_at'])) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid grid--2 mt-3">
    <div class="card">
        <div class="card__head"><h2 class="card__title">Proximas citas</h2></div>
        <div class="card__body card__body--flush">
            <?php foreach ($upcoming as $appointment): ?>
                <div class="list-row">
                    <?= Icons::render('clock', 16) ?>
                    <span>
                        <span class="fw-600"><?= e($appointment['first_name'] . ' ' . $appointment['last_name']) ?></span>
                        <span class="text-xs text-muted" style="display:block"><?= e(formatDateTime((string) $appointment['starts_at'])) ?></span>
                    </span>
                    <span class="list-row__meta"><?= e(Appointments::MODALITIES[$appointment['modality']]) ?></span>
                </div>
            <?php endforeach; ?>
            <?php if ($upcoming === []): ?>
                <div class="empty"><p class="text-sm">No hay citas programadas.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card__head"><h2 class="card__title">Ultimas notas registradas</h2></div>
        <div class="card__body card__body--flush">
            <?php foreach ($recentNotes as $note): ?>
                <a class="list-row" href="/notas/<?= (int) $note['id'] ?>">
                    <?= Icons::render('notes', 16) ?>
                    <span>
                        <span class="fw-600">Sesion <?= (int) $note['session_number'] ?> · <?= e($note['first_name'] . ' ' . $note['last_name']) ?></span>
                        <span class="text-xs text-muted" style="display:block"><?= e(formatDate((string) $note['session_date'])) ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
            <?php if ($recentNotes === []): ?>
                <div class="empty"><p class="text-sm">Aun no hay notas clinicas.</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>
