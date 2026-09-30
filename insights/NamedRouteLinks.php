<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;

/**
 * HTTP-17 — a link to this application is built with `route()`, never a hand-written path.
 *
 * Flags `url()`, `redirect()`, `URL::to()`, `Redirect::to()` and `->to()` given a literal path,
 * and a string assembled from `app.url` / `APP_URL`. Absolute URLs to another origin — the
 * frontend, an allowed redirect origin — are not paths into this application and are not flagged.
 */
final class NamedRouteLinks extends DomainInsight implements HasDetails
{
    use InspectsHttpLayer;

    private const PATH_TAKERS = ['url', 'redirect'];

    private const TO_FACADES = ['Illuminate\Support\Facades\URL', 'Illuminate\Support\Facades\Redirect', 'URL', 'Redirect'];

    public function getTitle(): string
    {
        return 'Links are built with route() and a named route (HTTP-17)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->analysedFiles() as $path) {
            if ($this->isGrandfathered($path) || ! $this->isHttpGoverned($path)) {
                continue;
            }

            foreach ($this->findIn($path, fn (Node $node): bool => $this->takesLiteralPath($node)) as $node) {
                $details[] = $this->detail($path, sprintf(
                    'Line %d links to a hand-written path. Name the route and use route() (HTTP-17).',
                    $node->getStartLine(),
                ));
            }

            // A chain of concatenations nests one node per operator; report each line once.
            $lines = array_unique(array_map(
                fn (Node $node): int => $node->getStartLine(),
                $this->findIn($path, fn (Node $node): bool => $this->assemblesFromAppUrl($node)),
            ));

            foreach ($lines as $line) {
                $details[] = $this->detail($path, sprintf(
                    'Line %d builds a URL from app.url. Name the route and use route() (HTTP-17).',
                    $line,
                ));
            }
        }

        return $details;
    }

    private function takesLiteralPath(Node $node): bool
    {
        $first = null;

        if ($node instanceof FuncCall && $node->name instanceof Name && in_array(strtolower($node->name->getLast()), self::PATH_TAKERS, true)) {
            $first = $node->getArgs()[0]->value ?? null;
        } elseif ($node instanceof StaticCall && $node->class instanceof Name && $node->name instanceof Identifier
            && in_array($node->class->toString(), self::TO_FACADES, true) && $node->name->toLowerString() === 'to') {
            $first = $node->getArgs()[0]->value ?? null;
        } elseif ($node instanceof MethodCall && $node->name instanceof Identifier && $node->name->toLowerString() === 'to'
            && $node->var instanceof FuncCall && $node->var->name instanceof Name
            && in_array(strtolower($node->var->name->getLast()), self::PATH_TAKERS, true)) {
            $first = $node->getArgs()[0]->value ?? null;
        }

        return $first !== null && $this->isLiteralPath($first);
    }

    /**
     * A path written into the code — `'/login'`, `"/users/{$id}"`, `'/users/' . $id` — rather than
     * an absolute URL or a value from elsewhere.
     */
    private function isLiteralPath(Expr $value): bool
    {
        while ($value instanceof Concat) {
            $value = $value->left;
        }

        $start = match (true) {
            $value instanceof String_ => $value->value,
            $value instanceof InterpolatedString && $value->parts[0] instanceof Node\InterpolatedStringPart => $value->parts[0]->value,
            default => null,
        };

        return $start !== null && preg_match('#^[a-z][a-z0-9+.-]*://#i', $start) !== 1;
    }

    private function assemblesFromAppUrl(Node $node): bool
    {
        $assembles = $node instanceof InterpolatedString
            || $node instanceof Concat
            || ($node instanceof FuncCall && $node->name instanceof Name && in_array(strtolower($node->name->getLast()), ['sprintf', 'implode'], true));

        if (! $assembles) {
            return false;
        }

        return (new NodeFinder())->findFirst($node, fn (Node $inner): bool => $this->readsAppUrl($inner)) !== null;
    }

    private function readsAppUrl(Node $node): bool
    {
        $key = null;

        if ($node instanceof FuncCall && $node->name instanceof Name && in_array(strtolower($node->name->getLast()), ['config', 'env'], true)) {
            $key = $node->getArgs()[0]->value ?? null;
        } elseif (($node instanceof StaticCall || $node instanceof MethodCall) && $node->name instanceof Identifier && $node->name->toLowerString() === 'get') {
            $key = $node->getArgs()[0]->value ?? null;
        }

        return $key instanceof String_ && in_array($key->value, ['app.url', 'APP_URL'], true);
    }
}
