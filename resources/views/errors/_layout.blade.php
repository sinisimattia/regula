@php
    $accentColorName = $statusCode === 404 ? 'warning' : 'danger';
    $previousException = isset($exception) ? $exception->getPrevious() : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $statusCode }} - {{ $title }}</title>
    <x-theme />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: var(--theme-font-sans);
            background: radial-gradient(var(--theme-primary), var(--theme-ink)) fixed no-repeat center;
            background-color: var(--theme-ink);
            color: var(--theme-paper);
            min-height: 100vh;
            padding: 1.5rem;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            display: flex;
            align-items: baseline;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid color-mix(in srgb, var(--theme-paper) 20%, transparent);
        }
        .error-code {
            font-family: var(--theme-font-mono);
            font-size: 1.5rem;
            font-weight: 500;
            color: var(--theme-{{ $accentColorName }});
        }
        .error-title { font-size: 1rem; color: color-mix(in srgb, var(--theme-paper) 80%, transparent); }
        .section {
            background: color-mix(in srgb, var(--theme-paper) 6%, transparent);
            border: 1px solid color-mix(in srgb, var(--theme-paper) 15%, transparent);
            border-radius: 0.375rem;
            margin-bottom: 0.5rem;
            overflow: hidden;
        }
        .section-header {
            background: color-mix(in srgb, var(--theme-paper) 10%, transparent);
            padding: 0.5rem 0.75rem;
            font-weight: 600;
            font-size: 0.75rem;
            color: color-mix(in srgb, var(--theme-paper) 70%, transparent);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .section-content {
            padding: 0.75rem;
            font-family: var(--theme-font-mono);
            font-size: 0.8rem;
            line-height: 1.2;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .message { color: var(--theme-danger); font-size: 0.875rem; line-height: 1.2; }
        .file-info { color: var(--theme-warning); line-height: 1.2; }
        .muted { color: color-mix(in srgb, var(--theme-paper) 50%, transparent); line-height: 1.2; margin-top: 0.25rem; }
        .muted:first-child { margin-top: 0; }
        .trace { color: var(--theme-code-background); }
        .trace-line { padding: 0.1rem 0; border-bottom: 1px solid color-mix(in srgb, var(--theme-paper) 8%, transparent); line-height: 1.2; }
        .trace-line:last-child { border-bottom: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="error-code">{{ $statusCode }}</div>
            <div class="error-title">{{ $title }}</div>
        </div>

        @isset($exception)
            <div class="section">
                <div class="section-header">Message</div>
                <div class="section-content">
                    <div class="message">{{ $exception->getMessage() ?: 'An error occurred.' }}</div>
                </div>
            </div>

            @if(app()->hasDebugModeEnabled())
                <div class="section">
                    <div class="section-header">Debug Information</div>
                    <div class="section-content">
                        <div class="muted">Exception Class</div>
                        <div class="file-info">{{ get_class($exception) }}</div>

                        <div class="muted">Location</div>
                        <div class="file-info">{{ $exception->getFile() }}:{{ $exception->getLine() }}</div>

                        @isset($previousException)
                            <div class="muted">Previous Exception</div>
                            <div class="file-info">{{ get_class($previousException) }}</div>
                            <div class="message">{{ $previousException->getMessage() }}</div>
                            <div class="file-info">{{ $previousException->getFile() }}:{{ $previousException->getLine() }}</div>
                        @endisset
                    </div>
                </div>

                <div class="section">
                    <div class="section-header">Stack Trace</div>
                    <div class="section-content trace">
                        @foreach(array_slice($exception->getTrace(), 0, 20) as $index => $frame)
                            <div class="trace-line">#{{ $index }} {{ $frame['file'] ?? '[internal]' }}{{ isset($frame['line']) ? ':' . $frame['line'] : '' }} {{ $frame['class'] ?? '' }}{{ $frame['type'] ?? '' }}{{ $frame['function'] ?? '' }}()</div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endisset
    </div>
</body>
</html>
