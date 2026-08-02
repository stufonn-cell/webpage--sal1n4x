<?php

/**
 * PsiClinic - sistema de historia clinica para psicologia.
 * Hecho por Salinas | github.com/stufonn-cell
 * Copyright (c) 2026. Todos los derechos reservados. Ver LICENSE.
 */

declare(strict_types=1);

namespace PsiClinic\Domain;

final class ConsentTemplates
{
    public static function all(): array
    {
        return [
            'general' => [
                'title' => 'Consentimiento informado para atencion psicologica',
                'body' => "Declaro que he sido informado sobre la naturaleza del proceso psicoterapeutico, sus objetivos, duracion estimada y metodologia de trabajo.\n\nEntiendo que la informacion compartida durante las sesiones esta protegida por el secreto profesional, y que este puede levantarse unicamente cuando exista riesgo vital para mi o para terceros, o por requerimiento de autoridad judicial competente.\n\nSe me ha informado que puedo suspender el proceso en cualquier momento, solicitar copia de mi historia clinica y formular preguntas sobre cualquier intervencion.\n\nAcepto de forma libre y voluntaria iniciar el proceso de atencion psicologica.",
            ],
            'teleconsulta' => [
                'title' => 'Consentimiento para atencion por medios virtuales',
                'body' => "Autorizo la realizacion de sesiones mediante videollamada o telefono.\n\nComprendo que la modalidad virtual tiene limitaciones tecnicas, que la calidad de la conexion puede afectar la sesion y que es mi responsabilidad ubicarme en un espacio privado durante la consulta.\n\nEntiendo que ante una situacion de crisis el profesional podra contactar a la persona designada como contacto de emergencia o a los servicios de atencion correspondientes.\n\nAcepto la modalidad virtual bajo estas condiciones.",
            ],
            'menores' => [
                'title' => 'Consentimiento de representante legal para atencion de menores',
                'body' => "En calidad de representante legal autorizo la atencion psicologica del menor identificado en esta historia clinica.\n\nEntiendo que el profesional compartira conmigo la informacion necesaria para el acompanamiento del proceso, preservando los espacios de confidencialidad que resulten terapeuticamente necesarios para el menor.\n\nAcepto participar en las sesiones de orientacion a familia cuando el profesional lo considere pertinente.",
            ],
            'datos' => [
                'title' => 'Autorizacion de tratamiento de datos personales',
                'body' => "Autorizo el tratamiento de mis datos personales y de mis datos sensibles de salud con la finalidad exclusiva de la prestacion del servicio de atencion psicologica, su facturacion y el cumplimiento de obligaciones legales.\n\nConozco que puedo ejercer los derechos de acceso, correccion, actualizacion y supresion sobre mis datos, y que estos se conservaran durante el tiempo que exija la normativa aplicable a las historias clinicas.",
            ],
            'grabacion' => [
                'title' => 'Autorizacion de registro audiovisual con fines de supervision',
                'body' => "Autorizo el registro en audio o video de las sesiones con fines exclusivos de supervision clinica y formacion profesional.\n\nEntiendo que el material sera custodiado de forma segura, no sera divulgado a terceros ajenos al proceso de supervision y sera eliminado una vez cumplida su finalidad.\n\nConozco que puedo revocar esta autorizacion en cualquier momento sin que ello afecte mi atencion.",
            ],
        ];
    }

    public static function get(string $code): ?array
    {
        return self::all()[$code] ?? null;
    }
}
