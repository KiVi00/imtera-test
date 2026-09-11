<?php

namespace App\Services\YandexMaps\Dto;

use DateTimeImmutable;

class ParsedReview
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $author,
        public readonly DateTimeImmutable $date,
        public readonly string $text,
        public readonly int $rating,
    ) {}
}
