<?php

namespace App\Services\YandexMaps\Dto;

class ParsedOrganization
{
    /**
     * @param  ParsedReview[]  $reviews
     */
    public function __construct(
        public readonly string $yandexId,
        public readonly string $title,
        public readonly ?float $rating,
        public readonly int $ratingsCount,
        public readonly int $reviewsCount,
        public readonly array $reviews,
    ) {}
}
