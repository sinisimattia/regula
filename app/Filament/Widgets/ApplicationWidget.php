<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class ApplicationWidget extends Widget
{
    protected string $view = 'filament.widgets.application';

    protected static ?int $sort = -9999;

    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'appName' => str(config('app.name'))
                ->headline(),
            'appEnvironment' => app()->environment(),
        ];
    }
}
