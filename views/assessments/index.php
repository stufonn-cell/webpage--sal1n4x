<?php

use PsiClinic\Core\View;
use PsiClinic\Support\Icons;

$pageTitle = 'Evaluaciones';
?>
<div class="page-head">
    <div>
        <h1>Evaluaciones psicometricas</h1>
        <p class="page-head__subtitle"><?= (int) $result['total'] ?> aplicaciones registradas</p>
    </div>
    <div class="flex gap-1">
        <a class="btn" href="/evaluaciones/catalogo"><?= Icons::render('brain', 16) ?> Catalogo</a>
        <a class="btn btn--primary" href="/evaluaciones/nueva"><?= Icons::render('plus', 16) ?> Aplicar instrumento</a>
    </div>
</div>

<form class="filters" method="get" action="/evaluaciones">
    <select name="instrumento">
        <option value="">Todos los instrumentos</option>
        <?php foreach ($instruments as $code => $definition): ?>
            <option value="<?= e($code) ?>"<?= $instrument === $code ? ' selected' : '' ?>><?= e($code) ?> · <?= e($definition['domain']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="estado">
        <option value="">Todos los estados</option>
        <option value="pending"<?= $status === 'pending' ? ' selected' : '' ?>>Pendientes</option>
        <option value="completed"<?= $status === 'completed' ? ' selected' : '' ?>>Completadas</option>
    </select>
    <button class="btn" type="submit"><?= Icons::render('search', 16) ?> Filtrar</button>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Paciente</th><th>Instrumento</th><th>Fecha</th><th>Puntaje</th><th>Severidad</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($result['rows'] as $row): ?>
                <tr>
                    <td>
                        <a class="cell-main" href="/pacientes/<?= (int) $row['patient_id'] ?>">
                            <span class="avatar avatar--sm"><?= e(initials($row['first_name'] . ' ' . $row['last_name'])) ?></span>
                            <span>
                                <span class="cell-main__name"><?= e($row['first_name'] . ' ' . $row['last_name']) ?></span>
                                <span class="cell-main__meta"><?= e($row['record_number']) ?></span>
                            </span>
                        </a>
                    </td>
                    <td><span class="badge badge--brand"><?= e($row['instrument_code']) ?></span></td>
                    <td class="text-sm"><?= e(formatDate($row['administered_at'] ?: $row['created_at'])) ?></td>
                    <td class="fw-600"><?= $row['total_score'] === null ? '-' : (int) $row['total_score'] ?></td>
                    <td class="text-sm"><?= e($row['severity'] ?: '-') ?></td>
                    <td><span class="badge badge--<?= $row['status'] === 'completed' ? 'success' : 'warning' ?>"><?= $row['status'] === 'completed' ? 'Completada' : 'Pendiente' ?></span></td>
                    <td class="text-right">
                        <?php if ($row['status'] === 'completed'): ?>
                            <a class="btn btn--ghost btn--sm" href="/evaluaciones/<?= (int) $row['id'] ?>">Ver informe</a>
                        <?php else: ?>
                            <span class="text-xs text-muted">Esperando al paciente</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($result['rows'] === []): ?>
                <tr><td colspan="7"><div class="empty"><div class="empty__title">Sin evaluaciones</div><p class="text-sm">Aplica un instrumento o asignalo al portal del paciente.</p></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= View::partial('partials/pagination', ['result' => $result, 'baseUrl' => '/evaluaciones?instrumento=' . urlencode($instrument) . '&estado=' . urlencode($status)]) ?>
</div>
