<?php

use PsiClinic\Core\View;
use PsiClinic\Domain\Patients;
use PsiClinic\Support\Icons;

$pageTitle = 'Notas clinicas';
?>
<div class="page-head">
    <div>
        <h1>Notas clinicas</h1>
        <p class="page-head__subtitle"><?= (int) $result['total'] ?> registros de sesion</p>
    </div>
    <a class="btn btn--primary" href="/notas/nueva"><?= Icons::render('plus', 16) ?> Nueva nota</a>
</div>

<form class="filters" method="get" action="/notas">
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Paciente o contenido de la nota" style="min-width:280px">
    <button class="btn" type="submit"><?= Icons::render('search', 16) ?> Buscar</button>
    <?php if ($search !== ''): ?><a class="btn btn--ghost" href="/notas">Limpiar</a><?php endif; ?>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Paciente</th><th>Sesion</th><th>Fecha</th><th>Formato</th><th>Profesional</th><th>Riesgo</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($result['rows'] as $note): ?>
                <tr>
                    <td>
                        <a class="cell-main" href="/pacientes/<?= (int) $note['patient_id'] ?>">
                            <span class="avatar avatar--sm"><?= e(initials($note['first_name'] . ' ' . $note['last_name'])) ?></span>
                            <span>
                                <span class="cell-main__name"><?= e($note['first_name'] . ' ' . $note['last_name']) ?></span>
                                <span class="cell-main__meta"><?= e($note['record_number']) ?></span>
                            </span>
                        </a>
                    </td>
                    <td class="fw-600">#<?= (int) $note['session_number'] ?></td>
                    <td class="text-sm"><?= e(formatDate((string) $note['session_date'])) ?></td>
                    <td><span class="badge"><?= e(strtoupper((string) $note['format'])) ?></span></td>
                    <td class="text-sm text-soft"><?= e($note['author_name']) ?></td>
                    <td class="text-sm"><?= e(Patients::RISK_LEVELS[$note['risk_level']]) ?></td>
                    <td><span class="badge badge--<?= (int) $note['is_locked'] === 1 ? 'success' : 'warning' ?>"><?= (int) $note['is_locked'] === 1 ? 'Firmada' : 'Borrador' ?></span></td>
                    <td class="text-right"><a class="btn btn--ghost btn--sm" href="/notas/<?= (int) $note['id'] ?>">Abrir</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($result['rows'] === []): ?>
                <tr><td colspan="8"><div class="empty"><div class="empty__title">Sin notas</div><p class="text-sm">Registra la primera nota de sesion.</p></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= View::partial('partials/pagination', ['result' => $result, 'baseUrl' => '/notas?q=' . urlencode($search)]) ?>
</div>
