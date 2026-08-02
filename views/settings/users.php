<?php

use PsiClinic\Core\Auth;
use PsiClinic\Support\Icons;

$pageTitle = 'Usuarios';
$isAdmin = Auth::is('admin');
$roleLabels = ['admin' => 'Administrador', 'psychologist' => 'Psicologo', 'assistant' => 'Asistente', 'patient' => 'Paciente'];
?>
<div class="page-head">
    <div>
        <h1>Usuarios</h1>
        <p class="page-head__subtitle"><?= count($users) ?> cuentas registradas</p>
    </div>
</div>

<div class="grid grid--sidebar">
    <div class="card">
        <div class="card__head"><h2 class="card__title">Cuentas</h2></div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Usuario</th><th>Rol</th><th>Correo</th><th>Ultimo ingreso</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($users as $account): ?>
                    <tr>
                        <td>
                            <div class="cell-main">
                                <span class="avatar avatar--sm"><?= e(initials((string) $account['full_name'])) ?></span>
                                <span>
                                    <span class="cell-main__name"><?= e($account['full_name']) ?></span>
                                    <span class="cell-main__meta"><?= e($account['username']) ?><?= $account['record_number'] ? ' · ' . e($account['record_number']) : '' ?></span>
                                </span>
                            </div>
                        </td>
                        <td><span class="badge badge--brand"><?= e($roleLabels[$account['role']] ?? $account['role']) ?></span></td>
                        <td class="text-sm text-soft"><?= e($account['email']) ?></td>
                        <td class="text-sm text-soft"><?= e(formatDateTime($account['last_login_at'])) ?></td>
                        <td><span class="badge badge--<?= (int) $account['is_active'] === 1 ? 'success' : 'danger' ?>"><?= (int) $account['is_active'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                        <td class="text-right">
                            <?php if ($isAdmin && (int) $account['id'] !== (int) Auth::id()): ?>
                                <button class="btn btn--ghost btn--sm" type="submit" form="toggle-user-<?= (int) $account['id'] ?>">
                                    <?= (int) $account['is_active'] === 1 ? 'Desactivar' : 'Activar' ?>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($isAdmin): ?>
        <div class="card">
            <div class="card__head"><h2 class="card__title">Nuevo usuario</h2></div>
            <div class="card__body">
                <form method="post" action="/ajustes/usuarios">
                    <?= csrf() ?>
                    <div class="field mb-2">
                        <label for="full_name">Nombre completo</label>
                        <input id="full_name" name="full_name" required>
                    </div>
                    <div class="field mb-2">
                        <label for="username">Usuario</label>
                        <input id="username" name="username" required>
                    </div>
                    <div class="field mb-2">
                        <label for="email">Correo</label>
                        <input id="email" name="email" type="email" required>
                    </div>
                    <div class="field mb-2">
                        <label for="password">Contrasena</label>
                        <input id="password" name="password" type="password" minlength="8" required>
                    </div>
                    <div class="field mb-2">
                        <label for="role">Rol</label>
                        <select id="role" name="role">
                            <option value="psychologist">Psicologo</option>
                            <option value="assistant">Asistente</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    <div class="field mb-2">
                        <label for="license_number">Registro profesional</label>
                        <input id="license_number" name="license_number">
                    </div>
                    <button class="btn btn--primary btn--block" type="submit"><?= Icons::render('plus', 16) ?> Crear usuario</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($isAdmin): ?>
    <?php foreach ($users as $account): ?>
        <?php if ((int) $account['id'] === (int) Auth::id()) { continue; } ?>
        <form id="toggle-user-<?= (int) $account['id'] ?>" method="post" action="/ajustes/usuarios/<?= (int) $account['id'] ?>/estado">
            <?= csrf() ?>
        </form>
    <?php endforeach; ?>
<?php endif; ?>
