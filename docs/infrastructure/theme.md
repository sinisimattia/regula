# Theme

## How do I change the application's colours or fonts?

Edit `config/theme.php`. It is the only place a colour or a font is written (PHP-26), and every
screen reads it: the welcome page, the error pages, the admin panel and the emails.

Say the brand moves from violet to teal and from DM Sans to Inter:

```php
'colors' => [
    'primary' => '#0f766e',
    // …
],
'fonts' => [
    'url' => 'https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Inter:wght@400;500;600;700&display=swap',
    'sans' => ['family' => 'Inter', 'fallback' => 'system-ui, sans-serif'],
    // …
],
```

After that one edit, the welcome hero fades from teal to black, the admin panel's buttons and links
turn teal, the email call-to-action button turns teal, and every page and email is set in Inter.

## What does each token paint?

| Token | Paints |
|---|---|
| `primary` | The welcome, error-page and admin sign-in gradient, the dashboard hero, the admin panel's buttons and links, email links and the call-to-action button |
| `ink` | Body text, dark surfaces, and the far end of each gradient |
| `paper` | Page and card backgrounds, and text on dark surfaces |
| `code`, `code_background` | Inline code, including the environment chip; `code_background` also colours the error pages' stack trace |
| `gray` | Muted text; the admin panel builds its whole grey palette from it |
| `border` | Email separators and the email card's shadow |
| `surface` | The email body behind the card |
| `danger`, `warning`, `success`, `info` | Status colours: the admin panel's notifications and badges, and the error pages' accents |

The admin panel turns each of `primary`, `gray` and the four statuses into a full palette of shades,
so one hex value is enough for each.

## Why is the font URL separate from the family names?

Every screen loads the same stylesheet, the one `fonts.url` points at, and then asks for the
families by name. The URL decides which families and weights exist; the names pick them. Change them
together: a family name the stylesheet doesn't load falls back to the `fallback` stack.

## How does a page get the tokens?

That depends on where the page is shown.

| Where | How |
|---|---|
| A browser page | `<x-theme />` in the `<head>` loads the fonts and declares one CSS variable per token: `primary` becomes `--theme-primary`, and `code_background` becomes `--theme-code-background`. The fonts are `--theme-font-sans` and `--theme-font-mono`. Styles then only write `var(--theme-…)` |
| The admin panel | `AdminPanelProvider` passes the tokens to Filament's `->colors()`, `->font()` and `->monoFont()`, and renders `<x-theme />` at the end of every panel page's `<head>`. The panel's own styles are in `resources/css/filament/admin/theme.css` |
| An email | Email clients ignore CSS variables, so the email views inline `config('theme.…')` at render. The font stack ends in the fallback, for the clients that block web fonts |

A translucent shade doesn't need a new token: `color-mix(in srgb, var(--theme-paper) 20%, transparent)`
mixes one from an existing token, the way the error pages draw their panels.

## I edited the admin panel's stylesheet and nothing changed

The panel loads a published copy of `resources/css/filament/admin/theme.css` from `public/css/app/`.
Publish it again with `php artisan filament:assets`, and commit the copy. `composer install` publishes
it too, through Filament's upgrade step.

## Why did `canon:check` fail on my colour?

A colour or font written in a view, a stylesheet or PHP under `app/` fails the build. That covers:

- a hex value, a colour function such as `rgb()`, or a named colour such as `white`, in any colour
  property, a custom property such as `--accent`, or a `var()` fallback;
- a `font-family`, or the `font` shorthand, that names a family;
- a Filament palette such as `Color::Blue`;
- any of those inside a PHP string, such as `'style' => 'color: red'`.

Add a token for it, or use an existing one.

Semantic names and CSS keywords pass. A Filament component's `color="danger"`, `var(--theme-gray)`,
`transparent` and `font-family: inherit` all take their colour or font from the theme, and so does
`font: 700 1rem/1.5 var(--theme-font-sans)`. The one exempt file is `resources/css/coverage.css`,
which PHPUnit copies verbatim into the coverage report, so it has no way to read config.

## Why not a CSS file, or environment variables?

- **A CSS file of custom properties** reaches browsers only. Emails and Filament's colour API would
  need their own copy.
- **Environment variables** would add about fifteen `.env` keys for what is a code decision. If a
  deployment ever needs its own colours, wrap the config values in `env()`, and this page and PHP-26
  still hold.
- **Filament's Vite theme** would compile Tailwind, which would bring Node into the Docker images and
  CI. The plain stylesheet covers what the panel needs. The trade-off is that Tailwind utility classes
  in our own views don't compile, so the panel's styles are written as plain CSS classes.
