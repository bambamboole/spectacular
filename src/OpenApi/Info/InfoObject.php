<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Info;

use Dedoc\Scramble\Support\Generator\InfoObject as ScrambleInfoObject;
use Override;

/**
 * Scramble's info object carries a title, a version and a description; OpenAPI also
 * lets a document name who to contact, under which licence it is published and where
 * its terms of service live.
 */
final class InfoObject extends ScrambleInfoObject
{
    public ?string $termsOfService = null;

    /** @var array<string, string> */
    public array $contact = [];

    /** @var array<string, string> */
    public array $license = [];

    public static function from(ScrambleInfoObject $info): self
    {
        $extended = new self($info->title, $info->version);
        $extended->description = $info->description;

        return $extended;
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            ...array_filter([
                'termsOfService' => $this->termsOfService,
                'contact' => $this->contact,
                'license' => $this->license,
            ]),
        ];
    }
}
