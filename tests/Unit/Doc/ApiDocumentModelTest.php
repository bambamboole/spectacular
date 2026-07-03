<?php

declare(strict_types=1);

use Bambamboole\Spectacular\Doc\Model\ApiDocument;
use Bambamboole\Spectacular\Doc\Model\ApiGroup;
use Bambamboole\Spectacular\Doc\Model\Contract;
use Bambamboole\Spectacular\Doc\Model\HttpFacet;
use Bambamboole\Spectacular\Doc\Model\Operation;
use Bambamboole\Spectacular\Doc\Model\OperationKind;
use Bambamboole\Spectacular\Doc\Model\Param;
use Bambamboole\Spectacular\Doc\Model\ParamGroup;

it('composes a document from value objects', function (): void {
    $op = new Operation(
        id: 'get-users',
        kind: OperationKind::Http,
        title: 'List users',
        summary: null,
        description: null,
        tags: ['Users'],
        deprecated: false,
        paramGroups: [new ParamGroup('query', [
            new Param('page', 'query', false, false, null, ['type' => 'integer']),
        ])],
        responses: [new Contract('response', '200', 'application/json', ['type' => 'object'], null)],
        requests: [new Contract('request', null, 'application/json', ['type' => 'object'], null)],
        facet: new HttpFacet('GET', '/users', 'users.index'),
    );
    $doc = new ApiDocument('openapi', '3.1.0', ['title' => 'X'], [], [new ApiGroup('users', 'Users', null, ['get-users'])], [$op], ['schemas' => []]);

    $facet = $doc->operations[0]->facet;
    expect($facet)->toBeInstanceOf(HttpFacet::class);
    if ($facet instanceof HttpFacet) {
        expect($facet->method)->toBe('GET');
    }
    expect($doc->groups[0]->operationIds)->toBe(['get-users'])
        ->and($doc->operations[0]->requests[0]->role)->toBe('request');
});
