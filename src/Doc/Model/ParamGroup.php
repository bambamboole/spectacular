<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\Doc\Model;

final readonly class ParamGroup
{
    /**
     * @param  list<Param>  $params
     */
    public function __construct(
        public string $location,
        public array $params,
    ) {}
}
