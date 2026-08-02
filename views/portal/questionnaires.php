<?php

use PsiClinic\Support\Icons;

$pageTitle = 'Cuestionarios';
?>
<div class="page-head">
    <div>
        <h1>Cuestionarios</h1>
        <p class="page-head__subtitle">Responder con sinceridad ayuda a tu profesional a ajustar el proceso.</p>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Instrumento</th><th>Asignado</th><th>Estado</th><th>Resultado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($assessments as $assessment): ?>
                <?php $definition = $instruments[$assessment['instrument_code']] ?? null; ?>
                <tr>
                    <td>
                        <span class="fw-600"><?= e($assessment['instrument_code']) ?></span>
                        <span class="text-xs text-muted" style="display:block"><?= e($definition['name'] ?? '') ?></span>
                    </td>
                    <td class="text-sm text-soft"><?= e(formatDate((string) $assessment['created_at'])) ?></td>
                    <td><span class="badge badge--<?= $assessment['status'] === 'completed' ? 'success' : 'warning' ?>"><?= $assessment['status'] === 'completed' ? 'Respondido' : 'Pendiente' ?></span></td>
                    <td class="text-sm"><?= $assessment['status'] === 'completed' ? e($assessment['severity']) : '-' ?></td>
                    <td class="text-right">
                        <?php if ($assessment['status'] !== 'completed'): ?>
                            <a class="btn btn--primary btn--sm" href="/portal/cuestionarios/<?= (int) $assessment['id'] ?>">
                                <?= Icons::render('clipboard', 15) ?> Responder
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($assessments === []): ?>
                <tr><td colspan="5"><div class="empty"><div class="empty__title">Sin cuestionarios asignados</div></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
