<?php

use PsiClinic\Domain\Appointments;
use PsiClinic\Domain\Instruments;
use PsiClinic\Support\Chart;
use PsiClinic\Support\Icons;

$pageTitle = 'Portal del paciente';
$completed = array_values(array_filter($progress, static fn (array $row): bool => $row['status'] === 'completed'));
$pendingConsents = array_values(array_filter($consents, static fn (array $row): bool => $row['status'] !== 'signed'));
?>
<div class="page-head">
    <div>
        <h1>Hola, <?= e($patient['first_name']) ?></h1>
        <p class="page-head__subtitle">Este es tu espacio para consultar citas, responder cuestionarios y revisar documentos.</p>
    </div>
</div>

<?php if ($pending !== [] || $pendingConsents !== []): ?>
    <div class="alert alert--info">
        <?= Icons::render('alert', 18) ?>
        <span>
            Tienes <?= count($pending) ?> cuestionario(s) y <?= count($pendingConsents) ?> consentimiento(s) pendientes.
        </span>
    </div>
<?php endif; ?>

<div class="grid grid--2 mb-3">
    <div class="card">
        <div class="card__head">
            <h2 class="card__title">Proximas citas</h2>
            <a class="btn btn--ghost btn--sm" href="/portal/citas">Ver todas</a>
        </div>
        <div class="card__body card__body--flush">
            <?php foreach ($appointments as $appointment): ?>
                <div class="list-row">
                    <?= Icons::render('calendar', 17) ?>
                    <span>
                        <span class="fw-600"><?= e(formatDateTime((string) $appointment['starts_at'])) ?></span>
                        <span class="text-xs text-muted" style="display:block">
                            <?= e($appointment['psychologist_name']) ?> · <?= e(Appointments::MODALITIES[$appointment['modality']]) ?>
                        </span>
                    </span>
                    <span class="list-row__meta">
                        <?php if ($appointment['modality'] === 'online' && $appointment['meeting_url']): ?>
                            <a class="btn btn--sm btn--accent" href="<?= e($appointment['meeting_url']) ?>" target="_blank" rel="noopener">Entrar</a>
                        <?php else: ?>
                            <span class="badge"><?= e(Appointments::STATUSES[$appointment['status']]) ?></span>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endforeach; ?>
            <?php if ($appointments === []): ?>
                <div class="empty"><div class="empty__title">Sin citas programadas</div><p class="text-sm">Tu profesional agendara la proxima sesion.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card__head">
            <h2 class="card__title">Cuestionarios por responder</h2>
            <a class="btn btn--ghost btn--sm" href="/portal/cuestionarios">Ver historial</a>
        </div>
        <div class="card__body card__body--flush">
            <?php foreach ($pending as $assessment): ?>
                <div class="list-row">
                    <span class="badge badge--brand"><?= e($assessment['instrument_code']) ?></span>
                    <span class="text-sm"><?= e(Instruments::get((string) $assessment['instrument_code'])['name'] ?? '') ?></span>
                    <span class="list-row__meta">
                        <a class="btn btn--primary btn--sm" href="/portal/cuestionarios/<?= (int) $assessment['id'] ?>">Responder</a>
                    </span>
                </div>
            <?php endforeach; ?>
            <?php if ($pending === []): ?>
                <div class="empty"><div class="empty__title">Todo al dia</div><p class="text-sm">No tienes cuestionarios pendientes.</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($completed !== []): ?>
    <div class="card mb-3">
        <div class="card__head"><h2 class="card__title">Tu evolucion</h2></div>
        <div class="card__body">
            <?php
            $grouped = [];
            foreach ($completed as $row) {
                $grouped[(string) $row['instrument_code']][] = $row;
            }
            ?>
            <?php foreach ($grouped as $code => $rows): ?>
                <?php $ordered = array_reverse($rows); ?>
                <h3><?= e($code) ?></h3>
                <?= Chart::line(
                    array_map(static fn (array $row): array => [
                        'label' => formatDate((string) $row['administered_at']),
                        'value' => (int) $row['total_score'],
                    ], $ordered),
                    Instruments::maxScore((string) $code),
                    640
                ) ?>
                <p class="text-xs text-muted mb-3">Ultimo resultado: <?= e($ordered[count($ordered) - 1]['severity']) ?></p>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card__head"><h2 class="card__title">Consentimientos</h2></div>
    <div class="card__body card__body--flush">
        <?php foreach ($consents as $consent): ?>
            <a class="list-row" href="/consentimientos/<?= (int) $consent['id'] ?>">
                <?= Icons::render('clipboard', 17) ?>
                <span class="text-sm fw-600"><?= e($consent['title']) ?></span>
                <span class="list-row__meta">
                    <span class="badge badge--<?= $consent['status'] === 'signed' ? 'success' : 'warning' ?>">
                        <?= $consent['status'] === 'signed' ? 'Firmado' : 'Pendiente de firma' ?>
                    </span>
                </span>
            </a>
        <?php endforeach; ?>
        <?php if ($consents === []): ?>
            <div class="empty"><p class="text-sm mb-0">No hay documentos por firmar.</p></div>
        <?php endif; ?>
    </div>
</div>
