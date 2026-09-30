<?php

declare(strict_types=1);

// The only place a colour or a font is written (PHP-26). See docs/infrastructure/theme.md.
return [

    'colors' => [
        'primary' => '#290087',
        'ink' => '#000000',
        'paper' => '#ffffff',
        'code' => '#004d80',
        'code_background' => '#d8f5ff',
        'gray' => '#71717a',
        'border' => '#e4e4e7',
        'surface' => '#f4f4f5',
        'danger' => '#ef4444',
        'warning' => '#f59e0b',
        'success' => '#22c55e',
        'info' => '#3b82f6',
    ],

    'fonts' => [
        // One stylesheet loads both families; change it together with them.
        'url' => 'https://fonts.googleapis.com/css2?family=DM+Mono:ital,wght@0,300;0,400;0,500;1,300;1,400;1,500&family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap',
        'sans' => [
            'family' => 'DM Sans',
            'fallback' => 'system-ui, sans-serif',
        ],
        'mono' => [
            'family' => 'DM Mono',
            'fallback' => 'monospace',
        ],
    ],

];
