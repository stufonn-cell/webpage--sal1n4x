<?php

use PsiClinic\Support\Icons;

$pageTitle = 'Mi perfil';
?>
<div class="page-head">
    <div class="flex-center" style="gap:1rem">
        <span class="avatar avatar--lg"><?= e(initials((string) $user['full_name'])) ?></span>
        <div>
            <h1 class="mb-0"><?= e($user['full_name']) ?></h1>
            <p class="page-head__subtitle"><?= e($user['username']) ?> · <?= e($user['role']) ?></p>
        </div>
    </div>
</div>

<div class="card" style="max-width:720px">
    <div class="card__head"><h2 class="card__title">Datos de la cuenta</h2></div>
    <div class="card__body">
        <form method="post" action="/perfil">
            <?= csrf() ?>
            <div class="form-grid">
                <div class="field field--full">
                    <label for="full_name">Nombre completo</label>
                    <input id="full_name" name="full_name" value="<?= e($user['full_name']) ?>" required>
                </div>
                <div class="field">
                    <label for="email">Correo</label>
                    <input id="email" name="email" type="email" value="<?= e($user['email']) ?>" required>
                </div>
                <div class="field">
                    <label for="phone">Telefono</label>
                    <input id="phone" name="phone" value="<?= e($user['phone'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="license_number">Registro profesional</label>
                    <input id="license_number" name="license_number" value="<?= e($user['license_number'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="specialty">Especialidad</label>
                    <input id="specialty" name="specialty" value="<?= e($user['specialty'] ?? '') ?>">
                </div>
                <div class="field field--full">
                    <label for="password">Nueva contrasena</label>
                    <input id="password" name="password" type="password" minlength="8" placeholder="Dejar vacio para conservar la actual">
                    <span class="field-hint">Minimo 8 caracteres.</span>
                </div>
                <div class="form-actions">
                    <button class="btn btn--primary" type="submit"><?= Icons::render('check', 16) ?> Guardar cambios</button>
                </div>
            </div>
        </form>
    </div>
</div>
