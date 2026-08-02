<?php

use PsiClinic\Domain\Patients;
use PsiClinic\Support\Icons;

$isEdit = $patient !== null;
$pageTitle = $isEdit ? 'Editar paciente' : 'Nuevo paciente';
$value = static fn (string $key, string $fallback = ''): string => e($patient[$key] ?? $fallback);
?>
<div class="page-head">
    <div>
        <h1><?= $isEdit ? 'Editar paciente' : 'Nuevo paciente' ?></h1>
        <p class="page-head__subtitle">Historia clinica <?= e($recordNumber) ?></p>
    </div>
    <a class="btn btn--ghost" href="<?= $isEdit ? '/pacientes/' . (int) $patient['id'] : '/pacientes' ?>">Cancelar</a>
</div>

<form method="post" action="<?= $isEdit ? '/pacientes/' . (int) $patient['id'] : '/pacientes' ?>">
    <?= csrf() ?>
    <?= $isEdit ? method('PUT') : '' ?>

    <div class="card mb-2">
        <div class="card__head"><h2 class="card__title">Datos personales</h2></div>
        <div class="card__body">
            <div class="form-grid">
                <div class="field<?= hasError('first_name') ?>">
                    <label for="first_name">Nombres</label>
                    <input id="first_name" name="first_name" value="<?= $value('first_name') ?>" required>
                    <?= fieldError('first_name') ?>
                </div>
                <div class="field<?= hasError('last_name') ?>">
                    <label for="last_name">Apellidos</label>
                    <input id="last_name" name="last_name" value="<?= $value('last_name') ?>" required>
                    <?= fieldError('last_name') ?>
                </div>
                <div class="field">
                    <label for="birth_date">Fecha de nacimiento</label>
                    <input id="birth_date" name="birth_date" type="date" value="<?= $value('birth_date') ?>">
                </div>
                <div class="field">
                    <label for="gender">Genero</label>
                    <select id="gender" name="gender">
                        <?php foreach (Patients::GENDERS as $key => $label): ?>
                            <option value="<?= e($key) ?>"<?= ($patient['gender'] ?? '') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="document_type">Tipo de documento</label>
                    <input id="document_type" name="document_type" value="<?= $value('document_type') ?>" placeholder="CC, TI, CE, Pasaporte">
                </div>
                <div class="field">
                    <label for="document_id">Numero de documento</label>
                    <input id="document_id" name="document_id" value="<?= $value('document_id') ?>">
                </div>
                <div class="field">
                    <label for="occupation">Ocupacion</label>
                    <input id="occupation" name="occupation" value="<?= $value('occupation') ?>">
                </div>
                <div class="field">
                    <label for="marital_status">Estado civil</label>
                    <input id="marital_status" name="marital_status" value="<?= $value('marital_status') ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-2">
        <div class="card__head"><h2 class="card__title">Contacto</h2></div>
        <div class="card__body">
            <div class="form-grid">
                <div class="field<?= hasError('email') ?>">
                    <label for="email">Correo electronico</label>
                    <input id="email" name="email" type="email" value="<?= $value('email') ?>">
                    <span class="field-hint">Necesario para habilitar el portal del paciente.</span>
                    <?= fieldError('email') ?>
                </div>
                <div class="field">
                    <label for="phone">Telefono</label>
                    <input id="phone" name="phone" value="<?= $value('phone') ?>">
                </div>
                <div class="field field--full">
                    <label for="address">Direccion</label>
                    <input id="address" name="address" value="<?= $value('address') ?>">
                </div>
                <div class="field">
                    <label for="city">Ciudad</label>
                    <input id="city" name="city" value="<?= $value('city') ?>">
                </div>
                <div class="field">
                    <label for="country">Pais</label>
                    <input id="country" name="country" value="<?= $value('country') ?>">
                </div>
                <div class="field">
                    <label for="emergency_contact_name">Contacto de emergencia</label>
                    <input id="emergency_contact_name" name="emergency_contact_name" value="<?= $value('emergency_contact_name') ?>">
                </div>
                <div class="field">
                    <label for="emergency_contact_phone">Telefono de emergencia</label>
                    <input id="emergency_contact_phone" name="emergency_contact_phone" value="<?= $value('emergency_contact_phone') ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-2">
        <div class="card__head"><h2 class="card__title">Informacion clinica</h2></div>
        <div class="card__body">
            <div class="form-grid">
                <div class="field field--full">
                    <label for="reason_for_consult">Motivo de consulta</label>
                    <textarea id="reason_for_consult" name="reason_for_consult"><?= $value('reason_for_consult') ?></textarea>
                </div>
                <div class="field field--full">
                    <label for="relevant_history">Antecedentes relevantes</label>
                    <textarea id="relevant_history" name="relevant_history"><?= $value('relevant_history') ?></textarea>
                </div>
                <div class="field field--full">
                    <label for="current_medication">Medicacion actual</label>
                    <textarea id="current_medication" name="current_medication"><?= $value('current_medication') ?></textarea>
                </div>
                <div class="field">
                    <label for="referred_by">Remitido por</label>
                    <input id="referred_by" name="referred_by" value="<?= $value('referred_by') ?>">
                </div>
                <div class="field">
                    <label for="psychologist_id">Profesional a cargo</label>
                    <select id="psychologist_id" name="psychologist_id">
                        <option value="">Sin asignar</option>
                        <?php foreach ($psychologists as $psychologist): ?>
                            <option value="<?= (int) $psychologist['id'] ?>"<?= (int) ($patient['psychologist_id'] ?? 0) === (int) $psychologist['id'] ? ' selected' : '' ?>>
                                <?= e($psychologist['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="risk_level">Nivel de riesgo</label>
                    <select id="risk_level" name="risk_level">
                        <?php foreach (Patients::RISK_LEVELS as $key => $label): ?>
                            <option value="<?= e($key) ?>"<?= ($patient['risk_level'] ?? 'none') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="status">Estado del proceso</label>
                    <select id="status" name="status">
                        <?php foreach (Patients::STATUSES as $key => $label): ?>
                            <option value="<?= e($key) ?>"<?= ($patient['status'] ?? 'active') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-actions">
                    <a class="btn btn--ghost" href="/pacientes">Cancelar</a>
                    <button class="btn btn--primary" type="submit"><?= Icons::render('check', 16) ?> Guardar</button>
                </div>
            </div>
        </div>
    </div>
</form>
