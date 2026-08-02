<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Support;

final class Icons
{
    private const PATHS = [
        'dashboard' => '<path d="M4 13h6V4H4v9Zm0 7h6v-5H4v5Zm10 0h6v-9h-6v9Zm0-16v5h6V4h-6Z"/>',
        'patients' => '<path d="M16 11a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm-8 1a3 3 0 1 0-3-3 3 3 0 0 0 3 3Zm0 2c-2.7 0-6 1.3-6 3.5V20h8v-2.5c0-1 .4-1.9 1.1-2.6A11 11 0 0 0 8 14Zm8 0c-3 0-6 1.5-6 3.6V20h12v-2.4c0-2.1-3-3.6-6-3.6Z"/>',
        'calendar' => '<path d="M7 2v2H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2H7Zm12 8v9H5v-9h14Z"/>',
        'notes' => '<path d="M6 2h9l5 5v15H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm8 1.5V8h4.5L14 3.5ZM8 12h8v1.6H8V12Zm0 4h8v1.6H8V16Z"/>',
        'chart' => '<path d="M4 20h17v-1.8H5.8V3H4v17Zm4-3.5 3.6-4.6 3 2.7L20 7.5l-1.4-1.1-4.2 5.4-3-2.7L7 15.4l1 1.1Z"/>',
        'clipboard' => '<path d="M9 2h6a2 2 0 0 1 2 2h1a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1a2 2 0 0 1 2-2Zm0 2v1h6V4H9Zm-1 7h8v1.7H8V11Zm0 4h8v1.7H8V15Z"/>',
        'invoice' => '<path d="M6 2h12v20l-3-2-3 2-3-2-3 2V2Zm3 5h6v1.7H9V7Zm0 4h6v1.7H9V11Zm0 4h4v1.7H9V15Z"/>',
        'document' => '<path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-7-7Zm0 2.5L17.5 9H13V4.5Z"/>',
        'settings' => '<path d="m12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Zm8.9 3.5a8.9 8.9 0 0 0-.1-1.3l2-1.5-2-3.4-2.4 1a8.6 8.6 0 0 0-2.2-1.3L15.8 3H8.2l-.4 2.5A8.6 8.6 0 0 0 5.6 6.8l-2.4-1-2 3.4 2 1.5a8.9 8.9 0 0 0 0 2.6l-2 1.5 2 3.4 2.4-1a8.6 8.6 0 0 0 2.2 1.3l.4 2.5h7.6l.4-2.5a8.6 8.6 0 0 0 2.2-1.3l2.4 1 2-3.4-2-1.5c.1-.4.1-.9.1-1.3Z"/>',
        'search' => '<path d="M10 3a7 7 0 1 0 4.2 12.6l4.6 4.6 1.4-1.4-4.6-4.6A7 7 0 0 0 10 3Zm0 2a5 5 0 1 1 0 10 5 5 0 0 1 0-10Z"/>',
        'plus' => '<path d="M11 5h2v6h6v2h-6v6h-2v-6H5v-2h6V5Z"/>',
        'logout' => '<path d="M10 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h5v-2H5V5h5V3Zm6.2 4.2-1.4 1.4L17.2 11H9v2h8.2l-2.4 2.4 1.4 1.4L21 12l-4.8-4.8Z"/>',
        'alert' => '<path d="M12 2 1 21h22L12 2Zm-1 7h2v6h-2V9Zm0 8h2v2h-2v-2Z"/>',
        'check' => '<path d="M9.6 16.2 4.8 11.4l-1.4 1.4 6.2 6.2L21 7.6l-1.4-1.4-10 10Z"/>',
        'clock' => '<path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm1 5v5.3l4.2 2.5-.9 1.5-5.3-3.2V7h2Z"/>',
        'brain' => '<path d="M9 2a3 3 0 0 0-3 3 3 3 0 0 0-2 5.2A3 3 0 0 0 5 16a3 3 0 0 0 3 3 2.5 2.5 0 0 0 3 2V2.6A2.5 2.5 0 0 0 9 2Zm6 0a2.5 2.5 0 0 0-2 .6V21a2.5 2.5 0 0 0 3-2 3 3 0 0 0 3-3 3 3 0 0 0 1-5.8A3 3 0 0 0 18 5a3 3 0 0 0-3-3Z"/>',
        'shield' => '<path d="M12 2 4 5.3v6.2c0 4.7 3.4 9.1 8 10.3 4.6-1.2 8-5.6 8-10.3V5.3L12 2Zm-1 13.4-3.2-3.2 1.4-1.4 1.8 1.8 4.4-4.4 1.4 1.4-5.8 5.8Z"/>',
        'edit' => '<path d="M3 17.2V21h3.8L18 9.8 14.2 6 3 17.2ZM20.7 7.1a1 1 0 0 0 0-1.4l-2.4-2.4a1 1 0 0 0-1.4 0l-1.8 1.8L19 8.9l1.7-1.8Z"/>',
        'trash' => '<path d="M8 2h8l1 2h4v2H3V4h4l1-2ZM5 8h14l-1 13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1L5 8Z"/>',
        'download' => '<path d="M11 3h2v9.2l3.4-3.4 1.4 1.4L12 16 6.2 10.2l1.4-1.4L11 12.2V3ZM4 18h16v2H4v-2Z"/>',
        'print' => '<path d="M7 2h10v4H7V2Zm-3 6h16a2 2 0 0 1 2 2v6h-4v4H7v-4H3v-6a2 2 0 0 1 1-2Zm5 8h6v4H9v-4Z"/>',
        'chevron-left' => '<path d="M15.4 5.6 9 12l6.4 6.4 1.4-1.4-5-5 5-5-1.4-1.4Z"/>',
        'chevron-right' => '<path d="m8.6 5.6-1.4 1.4 5 5-5 5 1.4 1.4L15 12 8.6 5.6Z"/>',
        'sun' => '<path d="M12 7a5 5 0 1 0 5 5 5 5 0 0 0-5-5Zm0-5h1.6v3H12V2Zm0 17h1.6v3H12v-3ZM2 11.2h3v1.6H2v-1.6Zm17 0h3v1.6h-3v-1.6ZM4.2 5.4l1.2-1.2 2.1 2.1-1.2 1.2-2.1-2.1Zm12.3 12.3 1.2-1.2 2.1 2.1-1.2 1.2-2.1-2.1Zm2.1-13.5 1.2 1.2-2.1 2.1-1.2-1.2 2.1-2.1ZM4.2 18.6l2.1-2.1 1.2 1.2-2.1 2.1-1.2-1.2Z"/>',
        'moon' => '<path d="M12.4 2A10 10 0 1 0 22 14.2a8 8 0 0 1-9.6-12.2Z"/>',
        'user' => '<path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm0 2c-3.9 0-8 1.9-8 4.5V21h16v-2.5c0-2.6-4.1-4.5-8-4.5Z"/>',
        'link' => '<path d="M10.6 13.4a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1.5 1.5 1.4 1.4L15 6.1a2 2 0 0 1 2.9 2.9l-3 3a2 2 0 0 1-2.9 0l-1.4 1.4Zm2.8-2.8a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1.5-1.5-1.4-1.4L9 17.9A2 2 0 0 1 6.1 15l3-3a2 2 0 0 1 2.9 0l1.4-1.4Z"/>',
        'flag' => '<path d="M5 2h2v20H5V2Zm3 1h11l-2.5 4L19 11H8V3Z"/>',
    ];

    public static function render(string $name, int $size = 20, string $class = ''): string
    {
        $path = self::PATHS[$name] ?? self::PATHS['document'];

        return sprintf(
            '<svg class="icon %s" width="%d" height="%d" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">%s</svg>',
            htmlspecialchars($class, ENT_QUOTES),
            $size,
            $size,
            $path
        );
    }
}
