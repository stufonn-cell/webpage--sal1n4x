<?php

use PsiClinic\Support\Icons;

$pageTitle = 'Consentimientos';
?>
<div class="page-head">
    <div>
        <h1>Consentimientos informados</h1>
        <p class="page-head__subtitle">Genera el documento y el paciente lo firma desde su portal.</p>
    </div>
</div>

<div class="grid grid--sidebar">
    <div class="card">
        <div class="card__head"><h2 class="card__title">Documentos generados</h2></div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Paciente</th><th>Documento</th><th>Generado</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($consents as $consent): ?>
                    <tr>
                        <td>
                            <a class="cell-main" href="/pacientes/<?= (int) $consent['patient_id'] ?>">
                                <span class="avatar avatar--sm"><?= e(initials($consent['first_name'] . ' ' . $consent['last_name'])) ?></span>
                                <span>
                                    <span class="cell-main__name"><?= e($consent['first_name'] . ' ' . $consent['last_name']) ?></span>
                                    <span class="cell-main__meta"><?= e($consent['record_number']) ?></span>
                                </span>
                            </a>
                        </td>
                        <td class="text-sm"><?= e($consent['title']) ?></td>
                        <td class="text-sm text-soft"><?= e(formatDate((string) $consent['created_at'])) ?></td>
                        <td><span class="badge badge--<?= $consent['status'] === 'signed' ? 'success' : 'warning' ?>"><?= $consent['status'] === 'signed' ? 'Firmado' : 'Pendiente' ?></span></td>
                        <td class="text-right"><a class="btn btn--ghost btn--sm" href="/consentimientos/<?= (int) $consent['id'] ?>">Abrir</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($consents === []): ?>
                    <tr><td colspan="5"><div class="empty"><div class="empty__title">Sin consentimientos</div></div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card__head"><h2 class="card__title">Generar consentimiento</h2></div>
        <div class="card__body">
            <form method="post" action="/consentimientos">
                <?= csrf() ?>
                <div class="field mb-2">
                    <label for="patient_id">Paciente</label>
                    <select id="patient_id" name="patient_id" required>
                        <option value="">Selecciona un paciente</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?= (int) $patient['id'] ?>"><?= e($patient['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field mb-2">
                    <label for="template_code">Plantilla</label>
                    <select id="template_code" name="template_code">
                        <?php foreach ($templates as $code => $template): ?>
                            <option value="<?= e($code) ?>"><?= e($template['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn--primary btn--block" type="submit"><?= Icons::render('plus', 16) ?> Generar</button>
            </form>
        </div>
    </div>
</div>
