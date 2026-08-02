<?php

use PsiClinic\Domain\Notes;
use PsiClinic\Domain\Patients;
use PsiClinic\Support\Icons;

$isEdit = $note !== null;
$pageTitle = $isEdit ? 'Editar nota' : 'Nueva nota de sesion';
$selectedInterventions = array_map('trim', explode(',', (string) ($note['interventions'] ?? '')));
?>
<div class="page-head">
    <div>
        <h1><?= $isEdit ? 'Editar nota de sesion' : 'Nueva nota de sesion' ?></h1>
        <p class="page-head__subtitle">Sesion numero <?= (int) $sessionNumber ?>. Una vez firmada, la nota queda bloqueada.</p>
    </div>
    <a class="btn btn--ghost" href="/notas">Volver</a>
</div>

<form method="post" action="<?= $isEdit ? '/notas/' . (int) $note['id'] : '/notas' ?>">
    <?= csrf() ?>
    <?= $isEdit ? method('PUT') : '' ?>
    <input type="hidden" name="session_number" value="<?= (int) $sessionNumber ?>">

    <div class="card mb-2">
        <div class="card__head"><h2 class="card__title">Datos de la sesion</h2></div>
        <div class="card__body">
            <div class="form-grid">
                <div class="field<?= hasError('patient_id') ?>">
                    <label for="patient_id">Paciente</label>
                    <select id="patient_id" name="patient_id" required <?= $isEdit ? 'disabled' : 'data-reload-on-change="paciente"' ?>>
                        <option value="">Selecciona un paciente</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?= (int) $patient['id'] ?>"<?= (int) $selectedPatient === (int) $patient['id'] ? ' selected' : '' ?>>
                                <?= e($patient['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="patient_id" value="<?= (int) $note['patient_id'] ?>">
                    <?php endif; ?>
                    <?= fieldError('patient_id') ?>
                </div>

                <div class="field">
                    <label for="session_date">Fecha de la sesion</label>
                    <input id="session_date" name="session_date" type="date" required
                           value="<?= e($note['session_date'] ?? date('Y-m-d')) ?>">
                </div>

                <div class="field">
                    <label for="format">Formato de la nota</label>
                    <select id="format" name="format">
                        <?php foreach (Notes::FORMATS as $key => $label): ?>
                            <option value="<?= e($key) ?>"<?= ($note['format'] ?? 'soap') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="appointment_id">Cita asociada</label>
                    <select id="appointment_id" name="appointment_id">
                        <option value="">Sin cita asociada</option>
                        <?php foreach ($appointments as $appointment): ?>
                            <option value="<?= (int) $appointment['id'] ?>"<?= (int) ($note['appointment_id'] ?? 0) === (int) $appointment['id'] ? ' selected' : '' ?>>
                                <?= e(formatDateTime((string) $appointment['starts_at'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-2">
        <div class="card__head"><h2 class="card__title">Contenido clinico</h2></div>
        <div class="card__body">
            <div class="form-grid">
                <div class="field field--full">
                    <label for="subjective">Subjetivo / Datos</label>
                    <textarea id="subjective" name="subjective" placeholder="Relato del paciente, motivo de la consulta de hoy, cambios desde la ultima sesion"><?= e($note['subjective'] ?? '') ?></textarea>
                </div>
                <div class="field field--full">
                    <label for="objective">Objetivo</label>
                    <textarea id="objective" name="objective" placeholder="Observacion conductual, estado mental, resultados de instrumentos"><?= e($note['objective'] ?? '') ?></textarea>
                </div>
                <div class="field field--full">
                    <label for="assessment">Analisis</label>
                    <textarea id="assessment" name="assessment" placeholder="Hipotesis clinica, evolucion, formulacion del caso"><?= e($note['assessment'] ?? '') ?></textarea>
                </div>
                <div class="field field--full">
                    <label for="plan">Plan</label>
                    <textarea id="plan" name="plan" placeholder="Objetivos, tecnicas a aplicar, frecuencia y proximos pasos"><?= e($note['plan'] ?? '') ?></textarea>
                </div>
                <div class="field field--full">
                    <label for="homework">Tarea entre sesiones</label>
                    <textarea id="homework" name="homework"><?= e($note['homework'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-2">
        <div class="card__head"><h2 class="card__title">Intervenciones aplicadas</h2></div>
        <div class="card__body">
            <div class="checkbox-grid">
                <?php foreach (Notes::INTERVENTIONS as $intervention): ?>
                    <label class="checkbox">
                        <input type="checkbox" name="interventions[]" value="<?= e($intervention) ?>"
                            <?= in_array($intervention, $selectedInterventions, true) ? ' checked' : '' ?>>
                        <span><?= e($intervention) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card__head"><h2 class="card__title">Valoracion de cierre</h2></div>
        <div class="card__body">
            <div class="form-grid">
                <div class="field">
                    <label for="mood_score">Estado de animo percibido (0 a 10)</label>
                    <input id="mood_score" name="mood_score" type="number" min="0" max="10" value="<?= e($note['mood_score'] ?? '') ?>">
                    <span class="field-hint">Alimenta la curva de evolucion del paciente.</span>
                </div>
                <div class="field">
                    <label for="risk_level">Nivel de riesgo</label>
                    <select id="risk_level" name="risk_level">
                        <?php foreach (Patients::RISK_LEVELS as $key => $label): ?>
                            <option value="<?= e($key) ?>"<?= ($note['risk_level'] ?? 'none') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field-hint">Actualiza el semaforo de riesgo del paciente.</span>
                </div>

                <div class="form-actions">
                    <a class="btn btn--ghost" href="/notas">Cancelar</a>
                    <button class="btn btn--primary" type="submit"><?= Icons::render('check', 16) ?> Guardar nota</button>
                </div>
            </div>
        </div>
    </div>
</form>
