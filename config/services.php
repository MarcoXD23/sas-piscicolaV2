<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Asistente IA. Proveedor: claude (por defecto) o gemini. Sin clave, el widget se muestra como "no configurado".
    'asistente' => [
        'proveedor' => env('ASISTENTE_PROVEEDOR') ?: 'claude',
        // Cada pregunta cuesta dinero: además del límite por minuto hay topes diarios
        'limite_diario_persona' => (int) (env('ASISTENTE_LIMITE_DIARIO_PERSONA') ?: 100),
        'limite_diario_finca' => (int) (env('ASISTENTE_LIMITE_DIARIO_FINCA') ?: 500),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        // Por defecto el más económico: las preguntas de finca son cortas. Se cambia con ANTHROPIC_MODEL.
        'modelo' => env('ANTHROPIC_MODEL') ?: 'claude-haiku-5-5',
        // Modelos que admiten el respaldo del servidor (fallbacks): solo con estos se envía. Los demás
        // (por ejemplo Haiku) no lo admiten: si el modelo declina, se muestra el aviso de pregunta bloqueada.
        'modelos_con_respaldo' => ['claude-opus-5-5', 'claude-opus-5', 'claude-fable-5-1', 'claude-sonnet-5-5'],
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'modelo' => env('GEMINI_MODEL') ?: 'gemini-3.5-flash-lite',
        // Se prueba una vez si el principal sigue saturado (500 o 503) tras los reintentos. Vacío = sin respaldo.
        'modelo_respaldo' => env('GEMINI_MODEL_RESPALDO') ?: null,
    ],

    // Canal de WhatsApp para alertas críticas. Sin proveedor implementado todavía: queda inactivo (CanalNulo).
    'whatsapp' => [
        'proveedor' => env('WHATSAPP_PROVEEDOR'),
        'token' => env('WHATSAPP_TOKEN'),
        'numero_id' => env('WHATSAPP_NUMERO_ID'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
