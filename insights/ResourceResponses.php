<?php

declare(strict_types=1);

namespace Insights;

use NunoMaduro\PhpInsights\Domain\Contracts\HasDetails;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Return_;

/**
 * HTTP-13 — a controller's response is an API Resource, not an array assembled on the spot.
 *
 * Flags a public action declared to return `array`, and one returning a literal array directly or
 * through `response()->json([...])`, `Response::json([...])` or `new JsonResponse([...])`.
 */
final class ResourceResponses extends DomainInsight implements HasDetails
{
    use InspectsHttpLayer;

    /**
     * Endpoints that are not API responses. A liveness probe answers a load balancer, not a
     * client, and has no entity to shape.
     */
    private const EXEMPT = [
        'app/Http/Controllers/HealthController.php',
    ];

    private const BASE_CONTROLLER = 'app/Http/Controllers/Controller.php';

    private const JSON_BUILDERS = ['Illuminate\Support\Facades\Response', 'Response'];

    private const JSON_RESPONSES = ['Illuminate\Http\JsonResponse', 'Symfony\Component\HttpFoundation\JsonResponse'];

    public function getTitle(): string
    {
        return 'Responses are built with API Resources (HTTP-13)';
    }

    /**
     * {@inheritdoc}
     */
    public function getDetails(): array
    {
        $details = [];

        foreach ($this->analysedFiles() as $path) {
            $relative = $this->relative($path);

            if ($this->isGrandfathered($path) || ! $this->isController($path) || ! $this->isHttpGoverned($path)
                || in_array($relative, [...self::EXEMPT, self::BASE_CONTROLLER], true)) {
                continue;
            }

            foreach ($this->classesIn($path) as $class) {
                foreach ($this->publicActions($class) as $action) {
                    if ($action->returnType instanceof Identifier && $action->returnType->toLowerString() === 'array') {
                        $details[] = $this->detail($path, sprintf(
                            'Line %d: %s() is declared to return an array. Return an API Resource (HTTP-13).',
                            $action->getStartLine(),
                            $action->name,
                        ));

                        continue;
                    }

                    foreach ($this->findInBody($action->stmts ?? [], fn (Node $node): bool => $node instanceof Return_
                        && $node->expr !== null && $this->isHandBuilt($node->expr)) as $return) {
                        $details[] = $this->detail($path, sprintf(
                            'Line %d: %s() returns a hand-assembled array. Build the response with an API Resource (HTTP-13).',
                            $return->getStartLine(),
                            $action->name,
                        ));
                    }
                }
            }
        }

        return $details;
    }

    private function isHandBuilt(Expr $expression): bool
    {
        if ($expression instanceof Array_) {
            return true;
        }

        $first = null;

        // response()->json([...])
        if ($expression instanceof MethodCall && $expression->name instanceof Identifier && $expression->name->toLowerString() === 'json'
            && $expression->var instanceof FuncCall && $expression->var->name instanceof Name
            && strtolower($expression->var->name->getLast()) === 'response') {
            $first = $expression->getArgs()[0] ?? null;
        }

        // Response::json([...])
        if ($expression instanceof StaticCall && $expression->class instanceof Name && $expression->name instanceof Identifier
            && in_array($expression->class->toString(), self::JSON_BUILDERS, true) && $expression->name->toLowerString() === 'json') {
            $first = $expression->getArgs()[0] ?? null;
        }

        // new JsonResponse([...])
        if ($expression instanceof New_ && $expression->class instanceof Name
            && in_array($expression->class->toString(), self::JSON_RESPONSES, true)) {
            $first = $expression->getArgs()[0] ?? null;
        }

        return $first !== null && $first->value instanceof Array_;
    }
}
