<?php

namespace App\Services\YandexMaps;

use App\Models\Organization;
use App\Services\YandexMaps\Dto\ParsedOrganization;
use Illuminate\Support\Facades\DB;

class OrganizationPersister
{
    public function persist(Organization $organization, ParsedOrganization $parsed): void
    {
        DB::transaction(function () use ($organization, $parsed) {
            $organization->update([
                'yandex_id' => $parsed->yandexId,
                'title' => $parsed->title,
                'rating' => $parsed->rating,
                'ratings_count' => $parsed->ratingsCount,
                'reviews_count' => $parsed->reviewsCount,
                'parse_status' => 'ok',
                'parse_error' => null,
                'last_parsed_at' => now(),
            ]);

            foreach ($parsed->reviews as $r) {
                $organization->reviews()->updateOrCreate(
                    ['external_id' => $r->externalId],
                    [
                        'author' => $r->author,
                        'date' => $r->date,
                        'text' => $r->text,
                        'rating' => $r->rating,
                    ],
                );
            }
        });
    }
}
