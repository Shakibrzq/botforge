<?php

declare(strict_types=1);

namespace Botforge\Entities;

/**
 * A point on the map.
 */
final readonly class Location
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            latitude: $data['latitude'],
            longitude: $data['longitude'],
        );
    }
}