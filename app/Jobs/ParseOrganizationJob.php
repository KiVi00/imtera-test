<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Services\YandexMaps\Exceptions\ParseException;
use App\Services\YandexMaps\OrganizationPersister;
use App\Services\YandexMaps\YandexMapsParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ParseOrganizationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 300;

    public function __construct(public int $organizationId)
    {
    }

    public function handle(YandexMapsParser $parser, OrganizationPersister $persister): void
    {
        $organization = Organization::find($this->organizationId);
        if (!$organization) {
            return;
        }

        $organization->update(['parse_status' => 'running', 'parse_error' => null]);

        try {
            $parsed = $parser->parse($organization->url);
            $persister->persist($organization, $parsed);
        } catch (ParseException $e) {
            $organization->update([
                'parse_status' => 'failed',
                'parse_error' => $e->reason . ': ' . $e->getMessage(),
            ]);
            Log::warning('Парсинг не удался', [
                'organization_id' => $organization->id,
                'reason' => $e->reason,
                'message' => $e->getMessage(),
            ]);

            if (in_array($e->reason, [ParseException::CODE_BLOCKED, ParseException::CODE_HTTP_ERROR], true)) {
                throw $e; // даём очереди повторить
            }
        } catch (Throwable $e) {
            $organization->update([
                'parse_status' => 'failed',
                'parse_error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
