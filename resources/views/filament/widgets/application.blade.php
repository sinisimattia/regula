<x-filament-widgets::widget>
    <section class="theme-hero">
        <h1 class="theme-hero-title">{{ $appName }}</h1>
        <p>Running in the <code>{{ str($appEnvironment)->headline() }}</code> environment.</p>
    </section>
</x-filament-widgets::widget>
