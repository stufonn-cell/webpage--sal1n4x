<?php

use PsiClinic\Core\View;
use PsiClinic\Domain\Patients;
use PsiClinic\Support\Icons;

$pageTitle = 'Pacientes';
?>
<div class="page-head">
    <div>
        <h1>Pacientes</h1>
        <p class="page-head__subtitle"><?= (int) $result['total'] ?> registros en la historia clinica</p>
    </div>
    <a class="btn btn--primary" href="/pacientes/nuevo"><?= Icons::render('plus', 16) ?> Nuevo paciente</a>
</div>

<form class="filters" method="get" action="/pacientes">
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Nombre, documento o historia">
    <select name="status">
        <option value="">Todos los estados</option>
        <?php foreach (Patients::STATUSES as $key => $label): ?>
            <option value="<?= e($key) ?>"<?= $status === $key ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit"><?= Icons::render('search', 16) ?> Filtrar</button>
    <?php if ($search !== '' || $status !== ''): ?>
        <a class="btn btn--ghost" href="/pacientes">Limpiar</a>
    <?php endif; ?>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead>
            <tr>
                <th>Paciente</th>
                <th>Historia</th>
                <th>Edad</th>
                <th>Profesional</th>
                <th>Sesiones</th>
                <th>Ultima sesion</th>
                <th>Riesgo</th>
                <th>Estado</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($result['rows'] as $patient): ?>
                <tr>
                    <td>
                        <a class="cell-main" href="/pacientes/<?= (int) $patient['id'] ?>">
                            <span class="avatar avatar--sm"><?= e(initials($patient['first_name'] . ' ' . $patient['last_name'])) ?></span>
                            <span>
                                <span class="cell-main__name"><?= e($patient['last_name'] . ', ' . $patient['first_name']) ?></span>
                                <span class="cell-main__meta"><?= e($patient['email'] ?: 'Sin correo') ?></span>
                            </span>
                        </a>
                    </td>
                    <td class="text-sm text-soft"><?= e($patient['record_number']) ?></td>
                    <td class="text-sm"><?= e(ageFrom($patient['birth_date'])) ?></td>
                    <td class="text-sm text-soft"><?= e($patient['psychologist_name'] ?: 'Sin asignar') ?></td>
                    <td class="text-sm"><?= (int) $patient['sessions_count'] ?></td>
                    <td class="text-sm text-soft"><?= e(formatDate($patient['last_session'])) ?></td>
                    <td>
                        <?php $risk = (string) $patient['risk_level']; ?>
                        <span class="badge badge--<?= $risk === 'high' ? 'danger' : ($risk === 'moderate' ? 'warning' : ($risk === 'low' ? 'info' : 'success')) ?>">
                            <?= e(Patients::RISK_LEVELS[$risk]) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge badge--<?= $patient['status'] === 'active' ? 'brand' : '' ?>"><?= e(Patients::STATUSES[$patient['status']]) ?></span>
                    </td>
                    <td class="text-right">
                        <a class="btn btn--ghost btn--sm" href="/pacientes/<?= (int) $patient['id'] ?>/editar"><?= Icons::render('edit', 15) ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($result['rows'] === []): ?>
                <tr><td colspan="9">
                    <div class="empty">
                        <div class="empty__title">Sin resultados</div>
                        <p class="text-sm">Ajusta los filtros o registra un nuevo paciente.</p>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= View::partial('partials/pagination', ['result' => $result, 'baseUrl' => '/pacientes?q=' . urlencode($search) . '&status=' . urlencode($status)]) ?>
</div>
