<?php

use PsiClinic\Core\Auth;
use PsiClinic\Support\Icons;

$pageTitle = 'Configuracion';
$isAdmin = Auth::is('admin');
?>
<div class="page-head">
    <div>
        <h1>Configuracion de la clinica</h1>
        <p class="page-head__subtitle">Estos valores alimentan documentos, agenda y facturacion.</p>
    </div>
</div>

<?php if (!$isAdmin): ?>
    <div class="alert alert--info"><?= Icons::render('alert', 16) ?><span>Solo un administrador puede modificar esta configuracion.</span></div>
<?php endif; ?>

<form method="post" action="/ajustes">
    <?= csrf() ?>

    <div class="card mb-2">
        <div class="card__head"><h2 class="card__title">Identidad</h2></div>
        <div class="card__body">
            <div class="form-grid">
                <div class="field">
                    <label for="clinic_name">Nombre de la clinica</label>
                    <input id="clinic_name" name="clinic_name" value="<?= e($settings['clinic_name']) ?>" <?= $isAdmin ? '' : 'disabled' ?>>
                </div>
                <div class="field">
                    <label for="clinic_tagline">Descriptor</label>
                    <input id="clinic_tagline" name="clinic_tagline" value="<?= e($settings['clinic_tagline']) ?>" <?= $isAdmin ? '' : 'disabled' ?>>
                </div>
                <div class="field">
                    <label for="clinic_email">Correo de contacto</label>
                    <input id="clinic_email" name="clinic_email" type="email" value="<?= e($settings['clinic_email']) ?>" <?= $isAdmin ? '' : 'disabled' ?>>
                </div>
                <div class="field">
                    <label for="clinic_phone">Telefono</label>
                    <input id="clinic_phone" name="clinic_phone" value="<?= e($settings['clinic_phone']) ?>" <?= $isAdmin ? '' : 'disabled' ?>>
                </div>
                <div class="field field--full">
                    <label for="clinic_address">Direccion</label>
                    <input id="clinic_address" name="clinic_address" value="<?= e($settings['clinic_address']) ?>" <?= $isAdmin ? '' : 'disabled' ?>>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-2">
        <div class="card__head"><h2 class="card__title">Operacion</h2></div>
        <div class="card__body">
            <div class="form-grid">
                <div class="field">
                    <label for="session_duration">Duracion de sesion (minutos)</label>
                    <input id="session_duration" name="session_duration" type="number" min="15" value="<?= e($settings['session_duration']) ?>" <?= $isAdmin ? '' : 'disabled' ?>>
                </div>
                <div class="field">
                    <label for="default_fee">Tarifa por defecto</label>
                    <input id="default_fee" name="default_fee" type="number" step="0.01" value="<?= e($settings['default_fee']) ?>" <?= $isAdmin ? '' : 'disabled' ?>>
                </div>
                <div class="field">
                    <label for="currency">Moneda</label>
                    <input id="currency" name="currency" value="<?= e($settings['currency']) ?>" <?= $isAdmin ? '' : 'disabled' ?>>
                </div>
                <div class="field">
                    <label for="note_lock_hours">Horas para editar una nota</label>
                    <input id="note_lock_hours" name="note_lock_hours" type="number" min="0" value="<?= e($settings['note_lock_hours']) ?>" <?= $isAdmin ? '' : 'disabled' ?>>
                </div>
                <div class="field">
                    <label for="working_hours_start">Inicio de jornada</label>
                    <input id="working_hours_start" name="working_hours_start" type="time" value="<?= e($settings['working_hours_start']) ?>" <?= $isAdmin ? '' : 'disabled' ?>>
                </div>
                <div class="field">
                    <label for="working_hours_end">Fin de jornada</label>
                    <input id="working_hours_end" name="working_hours_end" type="time" value="<?= e($settings['working_hours_end']) ?>" <?= $isAdmin ? '' : 'disabled' ?>>
                </div>

                <?php if ($isAdmin): ?>
                    <div class="form-actions">
                        <button class="btn btn--primary" type="submit"><?= Icons::render('check', 16) ?> Guardar configuracion</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>
