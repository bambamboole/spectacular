<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\RateLimiting;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Str;

/**
 * Documents the rate limit a throttled route enforces: the budget headers it returns
 * while the limit holds, and the response a client gets once it is exhausted.
 *
 * Throttling happens in middleware, which no controller body reveals — without this
 * an endpoint reads as if a client could call it as often as it likes.
 */
final readonly class RateLimitResponses implements OperationTransformer
{
    public function __construct(private OpenApiContext $context) {}

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $headers = RateLimitConfig::headers();
        $patterns = RateLimitConfig::middleware();

        if ($headers === [] || $patterns === [] || ! $this->isThrottled($routeInfo, $patterns)) {
            return;
        }

        foreach ($operation->responses ?? [] as $response) {
            if ($response instanceof Response && $this->isSuccessful($response)) {
                foreach ($this->headers($headers) as $name => $header) {
                    $response->addHeader($name, $header);
                }
            }
        }

        $this->addExhaustedResponse($operation, $headers);
    }

    /**
     * @param  list<string>  $patterns
     */
    private function isThrottled(RouteInfo $routeInfo, array $patterns): bool
    {
        return collect($routeInfo->route->gatherMiddleware())
            ->some(fn (string $middleware): bool => Str::is($patterns, $middleware));
    }

    private function isSuccessful(Response $response): bool
    {
        return is_numeric($response->code) && (int) $response->code >= 200 && (int) $response->code < 300;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function addExhaustedResponse(Operation $operation, array $headers): void
    {
        $components = $this->context->openApi->components;
        $reference = new Reference('responses', '\\'.ThrottleRequestsException::class, $components);

        if (! $components->has($reference)) {
            $components->add($reference, $this->exhaustedResponse($headers));
        }

        foreach ($operation->responses ?? [] as $response) {
            $documented = $response instanceof Reference
                ? $response->fullName === $reference->fullName
                : $response->code === 429;

            if ($documented) {
                return;
            }
        }

        $operation->addResponse($reference);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function exhaustedResponse(array $headers): Response
    {
        $body = new ObjectType()
            ->addProperty('message', new StringType()->setDescription('Error overview.'))
            ->setRequired(['message']);

        return Response::make(429)
            ->setDescription('Rate limit exceeded')
            ->setHeaders($this->headers([...$headers, ...RateLimitConfig::exhaustedHeaders()]))
            ->setContent('application/json', Schema::fromType($body));
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, Header>
     */
    private function headers(array $headers): array
    {
        return array_map(
            fn (string $description): Header => new Header(
                description: $description,
                schema: Schema::fromType(new IntegerType),
            ),
            $headers,
        );
    }
}
