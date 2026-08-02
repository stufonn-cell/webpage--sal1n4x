<?php

use PsiClinic\Domain\Appointments;
use PsiClinic\Support\Icons;

$isEdit = $appointment !== null;
$pageTitle = $isEdit ? 'Editar cita' : 'Nueva cita';
?>
<div class="page-head">
    <div>
        <h1><?= $isEdit ? 'Editar cita' : 'Agendar cita' ?></h1>
        <p class="page-head__subtitle">El sistema valida que el profesional no tenga otra cita en el mismo horario.</p>
    </div>
    <a class="btn btn--ghost" href="/agenda">Volver a la agenda</a>
</div>

<div class="card">
    <div class="card__body">
        <form method="post" action="<?= $isEdit ? '/agenda/' . (int) $appointment['id'] : '/agenda' ?>">
            <?= csrf() ?>
            <?= $isEdit ? method('PUT') : '' ?>

            <div class="form-grid">
                <div class="field<?= hasError('patient_id') ?>">
                    <label for="patient_id">Paciente</label>
                    <select id="patient_id" name="patient_id" required>
                        <option value="">Selecciona un paciente</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?= (int) $patient['id'] ?>"<?= (int) ($appointment['patient_id'] ?? 0) === (int) $patient['id'] ? ' selected' : '' ?>>
                                <?= e($patient['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= fieldError('patient_id') ?>
                </div>

                <div class="field">
                    <label for="psychologist_id">Profesional</label>
                    <select id="psychologist_id" name="psychologist_id" required>
                        <?php foreach ($psychologists as $psychologist): ?>
                            <option value="<?= (int) $psychologist['id'] ?>"<?= (int) ($appointment['psychologist_id'] ?? 0) === (int) $psychologist['id'] ? ' selected' : '' ?>>
                                <?= e($psychologist['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="date">Fecha</label>
                    <input id="date" name="date" type="date" value="<?= e($defaultDate) ?>" required>
                </div>

                <div class="field">
                    <label for="time">Hora de inicio</label>
                    <input id="time" name="time" type="time" value="<?= e($defaultTime) ?>" required>
                </div>

                <div class="field">
                    <label for="duration">Duracion (minutos)</label>
                    <input id="duration" name="duration" type="number" min="15" step="5" value="<?= (int) $duration ?>" required>
                </div>

                <div class="field">
                    <label for="modality">Modalidad</label>
                    <select id="modality" name="modality">
                        <?php foreach (Appointments::MODALITIES as $key => $label): ?>
                            <option value="<?= e($key) ?>"<?= ($appointment['modality'] ?? '') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="status">Estado</label>
                    <select id="status" name="status">
                        <?php foreach (Appointments::STATUSES as $key => $label): ?>
                            <option value="<?= e($key) ?>"<?= ($appointment['status'] ?? 'scheduled') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="session_type">Tipo de sesion</label>
                    <input id="session_type" name="session_type" value="<?= e($appointment['session_type'] ?? '') ?>" placeholder="Primera consulta, seguimiento, pareja">
                </div>

                <div class="field">
                    <label for="fee">Tarifa</label>
                    <input id="fee" name="fee" type="number" step="0.01" min="0" value="<?= e($defaultFee) ?>">
                </div>

                <div class="field">
                    <label for="location">Consultorio</label>
                    <input id="location" name="location" value="<?= e($appointment['location'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="meeting_url">Enlace de videollamada</label>
                    <input id="meeting_url" name="meeting_url" value="<?= e($appointment['meeting_url'] ?? '') ?>" placeholder="https://">
                </div>

                <div class="field field--full">
                    <label for="notes">Observaciones</label>
                    <textarea id="notes" name="notes"><?= e($appointment['notes'] ?? '') ?></textarea>
                </div>

                <div class="form-actions">
                    <?php if ($isEdit): ?>
                        <button class="btn btn--danger" type="submit" form="delete-appointment">
                            <?= Icons::render('trash', 15) ?> Eliminar
                        </button>
                    <?php endif; ?>
                    <a class="btn btn--ghost" href="/agenda">Cancelar</a>
                    <button class="btn btn--primary" type="submit"><?= Icons::render('check', 16) ?> Guardar cita</button>
                </div>
            </div>
        </form>

        <?php if ($isEdit): ?>
            <form id="delete-appointment" method="post" action="/agenda/<?= (int) $appointment['id'] ?>"
                  data-confirm="Eliminar esta cita de forma permanente?">
                <?= csrf() ?><?= method('DELETE') ?>
            </form>
        <?php endif; ?>
    </div>
</div>
