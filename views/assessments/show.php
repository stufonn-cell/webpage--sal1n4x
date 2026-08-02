<?php

use PsiClinic\Support\Chart;
use PsiClinic\Support\Icons;

$pageTitle = 'Informe ' . $assessment['instrument_code'];
$scaleLabels = array_flip($instrument['scale'] ?? []);
$criticalAlerts = [];

foreach ($instrument['critical_items'] ?? [] as $criticalIndex) {
    if ((int) ($answers[$criticalIndex] ?? 0) > 0) {
        $criticalAlerts[] = $instrument['items'][$criticalIndex];
    }
}
?>
<div class="page-head">
    <div>
        <h1><?= e($assessment['instrument_code']) ?> · <?= e($instrument['name'] ?? '') ?></h1>
        <p class="page-head__subtitle">
            <a href="/pacientes/<?= (int) $assessment['patient_id'] ?>"><?= e($assessment['first_name'] . ' ' . $assessment['last_name']) ?></a>
            · <?= e($assessment['record_number']) ?> · aplicada el <?= e(formatDateTime($assessment['administered_at'])) ?>
        </p>
    </div>
    <div class="flex gap-1">
        <button class="btn" type="button" onclick="window.print()"><?= Icons::render('print', 16) ?> Imprimir</button>
        <form method="post" action="/evaluaciones/<?= (int) $assessment['id'] ?>" data-confirm="Eliminar esta evaluacion?">
            <?= csrf() ?><?= method('DELETE') ?>
            <button class="btn btn--danger" type="submit"><?= Icons::render('trash', 15) ?></button>
        </form>
    </div>
</div>

<?php if ($criticalAlerts !== []): ?>
    <div class="alert alert--error">
        <?= Icons::render('alert', 18) ?>
        <span>
            <strong>Item critico marcado.</strong>
            Revisar en sesion: <?= e(implode(' · ', $criticalAlerts)) ?>
        </span>
    </div>
<?php endif; ?>

<div class="grid grid--sidebar mb-3">
    <div class="card">
        <div class="card__head"><h2 class="card__title">Resultado</h2></div>
        <div class="card__body">
            <div class="score-ring mb-3">
                <?= Chart::gauge((int) $assessment['total_score'], (int) $maxScore, 'de ' . (int) $maxScore) ?>
                <div>
                    <span class="stat__label">Severidad</span>
                    <h2 class="mt-0"><?= e($assessment['severity']) ?></h2>
                    <p class="text-soft text-sm mb-0"><?= e($assessment['interpretation']) ?></p>
                </div>
            </div>

            <?php if ($subscales !== []): ?>
                <h3>Subescalas</h3>
                <div class="grid grid--3">
                    <?php foreach ($subscales as $label => $data): ?>
                        <div class="stat">
                            <span>
                                <span class="stat__value"><?= (int) $data['score'] ?></span>
                                <span class="stat__label"><?= e($label) ?></span>
                                <span class="badge mt-1"><?= e($data['severity']) ?></span>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (count($series) > 1): ?>
                <h3 class="mt-3">Evolucion del paciente</h3>
                <?= Chart::line(
                    array_map(static fn (array $row): array => [
                        'label' => formatDate((string) $row['administered_at']),
                        'value' => (int) $row['total_score'],
                    ], $series),
                    (int) $maxScore,
                    620
                ) ?>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <div class="card mb-2">
            <div class="card__head"><h2 class="card__title">Bandas de referencia</h2></div>
            <div class="card__body card__body--flush">
                <?php foreach ($instrument['bands'] ?? [] as $band): ?>
                    <?php $isCurrent = (int) $assessment['total_score'] >= $band[0] && (int) $assessment['total_score'] <= $band[1]; ?>
                    <div class="list-row" style="<?= $isCurrent ? 'background:var(--brand-50)' : '' ?>">
                        <span class="fw-600 text-sm"><?= e($band[2]) ?></span>
                        <span class="list-row__meta"><?= (int) $band[0] ?> - <?= (int) $band[1] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <div class="card__head"><h2 class="card__title">Ficha tecnica</h2></div>
            <div class="card__body">
                <dl class="definition text-sm">
                    <dt>Dominio</dt><dd><?= e($instrument['domain'] ?? '-') ?></dd>
                    <dt>Ventana temporal</dt><dd><?= e($instrument['window'] ?? '-') ?></dd>
                    <dt>Items</dt><dd><?= count($instrument['items'] ?? []) ?></dd>
                    <dt>Aplicada por</dt><dd><?= e($assessment['assigned_by_name'] ?: 'Portal del paciente') ?></dd>
                </dl>
                <?php if (trim((string) $assessment['clinician_notes']) !== ''): ?>
                    <p class="text-sm text-soft mt-2 mb-0"><?= e($assessment['clinician_notes']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card__head"><h2 class="card__title">Respuestas item por item</h2></div>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th style="width:48px">#</th><th>Item</th><th style="width:220px">Respuesta</th><th style="width:90px" class="text-right">Puntos</th></tr></thead>
            <tbody>
            <?php foreach ($instrument['items'] ?? [] as $index => $item): ?>
                <?php
                $rawValue = (int) ($answers[$index] ?? 0);
                $isReversed = in_array($index, $instrument['reverse'] ?? [], true);
                $isCritical = in_array($index, $instrument['critical_items'] ?? [], true);
                ?>
                <tr>
                    <td class="text-muted"><?= $index + 1 ?></td>
                    <td class="text-sm">
                        <?= e($item) ?>
                        <?php if ($isReversed): ?><span class="badge text-xs">inverso</span><?php endif; ?>
                        <?php if ($isCritical): ?><span class="badge badge--danger text-xs">critico</span><?php endif; ?>
                    </td>
                    <td class="text-sm text-soft"><?= e($scaleLabels[$rawValue] ?? '-') ?></td>
                    <td class="text-right fw-600"><?= $rawValue ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
