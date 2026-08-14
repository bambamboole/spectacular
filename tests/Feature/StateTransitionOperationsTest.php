<?php
declare(strict_types=1);

use Bambamboole\Spectacular\Tests\Fixtures\StateTransitions\Ticket;
use Bambamboole\Spectacular\Tests\Fixtures\StateTransitions\TicketResource;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create('tickets', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('status');
        $table->string('resolution')->nullable();
        $table->timestamps();
    });
});

it('fans the templated route out into one operation per reachable target state', function (): void {
    $document = generatedTicketTransitionsDocument();

    expect(array_keys($document['paths']))
        ->toContain('/tickets/{ticket}/transition-to/closed')
        ->toContain('/tickets/{ticket}/transition-to/open')
        ->toContain('/tickets/{ticket}/transition-to/resolved')
        ->not->toContain('/tickets/{ticket}/transition-to/{state}');
});

it('documents the transition payload as a required request body', function (): void {
    $document = generatedTicketTransitionsDocument();

    $resolve = $document['paths']['/tickets/{ticket}/transition-to/resolved']['patch'];
    $close = $document['paths']['/tickets/{ticket}/transition-to/closed']['patch'];

    expect($resolve['requestBody'])->toBe([
        'required' => true,
        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ResolveTicketData']]],
    ])
        ->and($close)->not->toHaveKey('requestBody')
        ->and($document['components']['schemas']['ResolveTicketData'])->toMatchArray([
            'type' => 'object',
            'required' => ['reason'],
        ])
        ->and($document['components']['schemas']['ResolveTicketData']['properties']['reason'])->toMatchArray([
            'type' => 'string',
            'description' => 'How the ticket was resolved.',
            'maxLength' => 500,
        ]);
});

it('names and describes each operation after its target state', function (): void {
    $document = generatedTicketTransitionsDocument();

    $resolve = $document['paths']['/tickets/{ticket}/transition-to/resolved']['patch'];
    $operationIds = array_map(
        fn (string $target): string => $document['paths']["/tickets/{ticket}/transition-to/{$target}"]['patch']['operationId'],
        ['closed', 'open', 'resolved'],
    );

    expect($resolve['operationId'])->toEndWith('-to.resolved')
        ->and($resolve['summary'])->toBe('Transition ticket to resolved')
        ->and($resolve['description'])->toContain('Allowed while the ticket is `open`')
        ->and(array_unique($operationIds))->toHaveCount(3);
});

it('drops the state path parameter from the fanned-out operations', function (): void {
    $document = generatedTicketTransitionsDocument();

    $parameters = $document['paths']['/tickets/{ticket}/transition-to/resolved']['patch']['parameters'] ?? [];

    expect(array_column($parameters, 'name'))->toBe(['ticket']);
});

it('documents a shared 409 response referencing the state enum', function (): void {
    $document = generatedTicketTransitionsDocument();

    foreach (['closed', 'open', 'resolved'] as $target) {
        expect($document['paths']["/tickets/{ticket}/transition-to/{$target}"]['patch']['responses']['409'] ?? null)
            ->toBe(['$ref' => '#/components/responses/TicketTransitionDenied']);
    }

    $schema = $document['components']['responses']['TicketTransitionDenied']['content']['application/json']['schema'];

    expect($schema['required'])->toBe(['message', 'current_state', 'requested_state', 'allowed_states'])
        ->and($schema['properties']['current_state']['$ref'])->toBe('#/components/schemas/TicketState')
        ->and($schema['properties']['allowed_states']['items']['$ref'])->toBe('#/components/schemas/TicketState')
        ->and($document['components']['schemas']['TicketState']['enum'])->toBe(['closed', 'open', 'resolved']);
});

it('leaves state routes without an annotated state class untouched', function (): void {
    RouteFacade::patch('api/plain/{state}', fn (string $state): array => ['state' => $state]);

    Scramble::routes(fn (Route $route): bool => $route->uri() === 'api/plain/{state}');
    $document = app(Generator::class)();

    expect(array_keys(is_array($document) ? $document['paths'] : []))->toBe(['/plain/{state}']);
});

/**
 * @return array<string, mixed>
 */
function generatedTicketTransitionsDocument(): array
{
    RouteFacade::patch('api/tickets/{ticket}/transition-to/{state}', TicketTransitionDocsController::class);

    Scramble::routes(fn (Route $route): bool => str_starts_with($route->uri(), 'api/tickets'));

    $document = app(Generator::class)();

    return is_array($document) ? $document : [];
}

final class TicketTransitionDocsController
{
    public function __invoke(Ticket $ticket, string $state): TicketResource
    {
        return new TicketResource($ticket);
    }
}
