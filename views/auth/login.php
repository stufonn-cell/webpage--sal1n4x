<?php

use PsiClinic\Core\App;
use PsiClinic\Support\Icons;

$flash = App::flash();
$clinicName = $settings['clinic_name'] ?? 'PsiClinic';
?>
<main class="auth">
    <aside class="auth__aside">
        <div class="auth__brand">
            <svg width="34" height="34" viewBox="0 0 48 48" aria-hidden="true">
                <rect width="48" height="48" rx="13" fill="rgba(255,255,255,.16)"/>
                <path d="M24 10c-5.6 0-10 4.1-10 9.4 0 3.4 1.8 6.3 4.7 8v3.1a1.4 1.4 0 0 0 2.2 1.1l2-1.4c.4 0 .7.1 1.1.1 5.6 0 10-4.1 10-9.4S29.6 10 24 10Z" fill="#fff"/>
                <circle cx="19.4" cy="19.4" r="1.9" fill="#2433ae"/>
                <circle cx="28.6" cy="17.2" r="1.9" fill="#2433ae"/>
                <circle cx="27.1" cy="23.6" r="1.9" fill="#0d8f7c"/>
            </svg>
            <span><?= e($clinicName) ?></span>
        </div>

        <div>
            <h1 class="auth__headline">Historia clinica pensada para psicologia</h1>
            <p class="auth__lead">
                Agenda, notas de sesion, instrumentos psicometricos con correccion automatica y
                portal del paciente en una sola plataforma que corre en tu propio equipo.
            </p>

            <svg class="auth__illustration" viewBox="0 0 460 300" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Ilustracion de seguimiento clinico">
                <defs>
                    <linearGradient id="cardGradient" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0" stop-color="#ffffff" stop-opacity=".22"/>
                        <stop offset="1" stop-color="#ffffff" stop-opacity=".06"/>
                    </linearGradient>
                    <linearGradient id="lineGradient" x1="0" y1="0" x2="1" y2="0">
                        <stop offset="0" stop-color="#2fd0b5"/>
                        <stop offset="1" stop-color="#ffffff"/>
                    </linearGradient>
                </defs>

                <circle cx="230" cy="150" r="128" stroke="#ffffff" stroke-opacity=".14" stroke-width="1.5"/>
                <circle cx="230" cy="150" r="96" stroke="#ffffff" stroke-opacity=".1" stroke-width="1.5"/>

                <g class="drift">
                    <path d="M156 214c-20-14-32-38-32-64 0-46 38-82 86-82 46 0 82 34 82 78 0 42-32 74-76 78l-30 20c-6 4-14 0-14-8v-22Z"
                          fill="url(#cardGradient)" stroke="#ffffff" stroke-opacity=".5" stroke-width="2"/>

                    <path d="M172 128h44M172 148h64M172 168h34" stroke="#ffffff" stroke-opacity=".55" stroke-width="4" stroke-linecap="round"/>
                    <circle class="pulse-node" cx="252" cy="120" r="7" fill="#2fd0b5"/>
                    <circle class="pulse-node pulse-node--delay" cx="272" cy="152" r="5" fill="#ffffff" fill-opacity=".85"/>
                    <circle class="pulse-node pulse-node--delay-2" cx="248" cy="176" r="6" fill="#90aeff"/>
                    <path d="M252 120 272 152 248 176" stroke="#ffffff" stroke-opacity=".45" stroke-width="1.8" stroke-linejoin="round" fill="none"/>
                </g>

                <g transform="translate(292 42)">
                    <rect width="130" height="86" rx="14" fill="url(#cardGradient)" stroke="#ffffff" stroke-opacity=".45" stroke-width="1.6"/>
                    <path d="M16 62 42 40l22 16 34-34" stroke="url(#lineGradient)" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    <circle cx="98" cy="22" r="4.5" fill="#2fd0b5"/>
                    <rect x="16" y="16" width="46" height="6" rx="3" fill="#ffffff" fill-opacity=".5"/>
                </g>

                <g transform="translate(38 176)">
                    <rect width="140" height="80" rx="14" fill="url(#cardGradient)" stroke="#ffffff" stroke-opacity=".45" stroke-width="1.6"/>
                    <rect x="16" y="18" width="60" height="6" rx="3" fill="#ffffff" fill-opacity=".55"/>
                    <rect x="16" y="36" width="108" height="10" rx="5" fill="#ffffff" fill-opacity=".18"/>
                    <rect x="16" y="36" width="74" height="10" rx="5" fill="#2fd0b5"/>
                    <rect x="16" y="58" width="40" height="6" rx="3" fill="#ffffff" fill-opacity=".35"/>
                </g>

                <g transform="translate(312 190)">
                    <rect width="112" height="70" rx="14" fill="url(#cardGradient)" stroke="#ffffff" stroke-opacity=".45" stroke-width="1.6"/>
                    <circle cx="26" cy="26" r="11" fill="#ffffff" fill-opacity=".6"/>
                    <rect x="46" y="19" width="50" height="6" rx="3" fill="#ffffff" fill-opacity=".5"/>
                    <rect x="46" y="31" width="34" height="6" rx="3" fill="#ffffff" fill-opacity=".3"/>
                    <rect x="16" y="50" width="80" height="6" rx="3" fill="#ffffff" fill-opacity=".22"/>
                </g>
            </svg>

            <div class="auth__features">
                <div class="auth__feature">
                    <span class="auth__feature-icon"><?= Icons::render('brain', 16) ?></span>
                    <span>Seis instrumentos validados con puntuacion, severidad y curva de evolucion</span>
                </div>
                <div class="auth__feature">
                    <span class="auth__feature-icon"><?= Icons::render('shield', 16) ?></span>
                    <span>Registro de auditoria, firma de notas y control de acceso por rol</span>
                </div>
                <div class="auth__feature">
                    <span class="auth__feature-icon"><?= Icons::render('calendar', 16) ?></span>
                    <span>Agenda semanal con deteccion de choques de horario</span>
                </div>
            </div>
        </div>

        <p class="auth__footnote">Datos alojados en tu equipo. Sin servicios externos.</p>
    </aside>

    <section class="auth__panel">
        <button class="auth__theme" type="button" data-theme-toggle aria-label="Cambiar tema">
            <?= Icons::render('moon', 18) ?>
        </button>

        <div class="auth__card">
            <h1 class="auth__title">Bienvenido de vuelta</h1>
            <p class="auth__subtitle">Ingresa con tus credenciales para acceder a la plataforma.</p>

            <?php foreach ($flash as $message): ?>
                <div class="alert alert--<?= e($message['type'] === 'success' ? 'success' : 'error') ?>">
                    <?= Icons::render($message['type'] === 'success' ? 'check' : 'alert', 16) ?>
                    <span><?= e($message['message']) ?></span>
                </div>
            <?php endforeach; ?>

            <form method="post" action="/login" autocomplete="on">
                <?= csrf() ?>

                <div class="auth__field">
                    <label for="identifier">Usuario o correo</label>
                    <div class="auth__input-wrap">
                        <?= Icons::render('user', 17) ?>
                        <input id="identifier" name="identifier" type="text" required autofocus
                               placeholder="nombre.apellido" value="<?= old('identifier') ?>">
                    </div>
                </div>

                <div class="auth__field">
                    <label for="password">Contrasena</label>
                    <div class="auth__input-wrap">
                        <?= Icons::render('shield', 17) ?>
                        <input id="password" name="password" type="password" required placeholder="Tu contrasena">
                        <button class="auth__toggle" type="button" data-password-toggle="password" aria-label="Mostrar contrasena">
                            <?= Icons::render('search', 16) ?>
                        </button>
                    </div>
                </div>

                <div class="auth__row">
                    <label class="auth__remember">
                        <input type="checkbox" name="remember" value="1">
                        <span>Mantener sesion iniciada</span>
                    </label>
                    <span class="text-muted text-xs">Bloqueo tras 5 intentos</span>
                </div>

                <button class="auth__submit" type="submit">
                    <?= Icons::render('logout', 17) ?>
                    Entrar
                </button>
            </form>

            <div class="auth__divider">Cuentas de demostracion</div>

            <div class="auth__demo">
                <div class="auth__demo-row">
                    <span>Administrador</span>
                    <button type="button" data-fill-user="admin" data-fill-pass="Psiclinic2026">admin</button>
                </div>
                <div class="auth__demo-row">
                    <span>Psicologa</span>
                    <button type="button" data-fill-user="l.moreno" data-fill-pass="Psiclinic2026">l.moreno</button>
                </div>
                <div class="auth__demo-row">
                    <span>Paciente</span>
                    <button type="button" data-fill-user="hc-2026-0001" data-fill-pass="Paciente2026">hc-2026-0001</button>
                </div>
            </div>
            <p class="auth__credit">
                Hecho por Salinas
                <a href="https://github.com/stufonn-cell" target="_blank" rel="noopener noreferrer">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M12 2a10 10 0 0 0-3.16 19.49c.5.09.68-.22.68-.48v-1.7c-2.78.6-3.37-1.34-3.37-1.34-.45-1.16-1.11-1.47-1.11-1.47-.91-.62.07-.61.07-.61 1 .07 1.53 1.03 1.53 1.03.9 1.53 2.36 1.09 2.94.83.09-.65.35-1.09.63-1.34-2.22-.25-4.55-1.11-4.55-4.94 0-1.09.39-1.98 1.03-2.68-.1-.25-.45-1.27.1-2.64 0 0 .84-.27 2.75 1.02a9.5 9.5 0 0 1 5 0c1.91-1.29 2.75-1.02 2.75-1.02.55 1.37.2 2.39.1 2.64.64.7 1.03 1.59 1.03 2.68 0 3.84-2.34 4.68-4.57 4.93.36.31.68.92.68 1.85v2.74c0 .27.18.58.69.48A10 10 0 0 0 12 2Z"/>
                    </svg>
                    stufonn-cell
                </a>
            </p>
        </div>
    </section>
</main>
