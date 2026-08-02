<?php

use PsiClinic\Domain\Appointments;

$pageTitle = 'Mis citas';
?>
<div class="page-head">
    <div>
        <h1>Mis citas</h1>
        <p class="page-head__subtitle">Historial completo de sesiones agendadas.</p>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Fecha</th><th>Profesional</th><th>Modalidad</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($appointments as $appointment): ?>
                <tr>
                    <td class="fw-600"><?= e(formatDateTime((string) $appointment['starts_at'])) ?></td>
                    <td class="text-sm text-soft"><?= e($appointment['psychologist_name']) ?></td>
                    <td class="text-sm"><?= e(Appointments::MODALITIES[$appointment['modality']]) ?></td>
                    <td><span class="badge"><?= e(Appointments::STATUSES[$appointment['status']]) ?></span></td>
                    <td class="text-right">
                        <?php if ($appointment['modality'] === 'online' && $appointment['meeting_url'] && strtotime((string) $appointment['ends_at']) > time()): ?>
                            <a class="btn btn--accent btn--sm" href="<?= e($appointment['meeting_url']) ?>" target="_blank" rel="noopener">Entrar a la sesion</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($appointments === []): ?>
                <tr><td colspan="5"><div class="empty"><div class="empty__title">Sin citas registradas</div></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
