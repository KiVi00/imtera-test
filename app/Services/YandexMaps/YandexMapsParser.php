<?php

namespace App\Services\YandexMaps;

use App\Services\YandexMaps\Dto\ParsedOrganization;
use App\Services\YandexMaps\Dto\ParsedReview;
use App\Services\YandexMaps\Exceptions\ParseException;
use DateTimeImmutable;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Exception\SessionNotCreatedException;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Facades\Log;
use Throwable;

class YandexMapsParser
{
    private const SELENIUM_HOST = 'http://selenium:4444';
    private const PAGE_BASE = 'https://yandex.ru/maps/org/x';
    private const LIMIT = 50;
    private const MAX_REVIEWS = 600;
    private const PAGE_WAIT_SECONDS = 3;
    private const BETWEEN_PAGES_SLEEP_MS = 800_000;

    public function parse(string $url): ParsedOrganization
    {
        $businessId = $this->extractBusinessId($url);
        $driver = $this->createDriver();

        try {
            $meta = null;
            $allReviews = [];
            $maxPages = (int) ceil(self::MAX_REVIEWS / self::LIMIT);
            $totalPages = null;

            for ($page = 1; $page <= $maxPages; $page++) {
                $pageUrl = self::PAGE_BASE."/{$businessId}/reviews/?page={$page}";

                $driver->get($pageUrl);
                sleep(self::PAGE_WAIT_SECONDS);

                $this->guardAgainstBlock($driver);

                $state = $this->readState($driver);
                $rr = $state['stack'][0]['results']['items'][0]['reviewResults'] ?? null;
                if (! is_array($rr)) {
                    // Первая страница без отзывов — это ошибка. Дальше — просто конец пагинации.
                    if ($page === 1) {
                        throw new ParseException('Не найден блок reviewResults в state-view', ParseException::CODE_LAYOUT_CHANGED);
                    }
                    break;
                }

                if ($page === 1) {
                    $meta = $this->extractMeta($state);
                    $totalPages = (int) ($rr['params']['totalPages'] ?? $maxPages);
                }

                $pageReviews = $rr['reviews'] ?? [];
                if (! is_array($pageReviews) || empty($pageReviews)) {
                    break;
                }

                foreach ($pageReviews as $r) {
                    $parsed = $this->mapReview($r);
                    if ($parsed) {
                        $allReviews[$parsed->externalId] = $parsed;
                    }
                }

                $collectedCount = count($allReviews);
                Log::info("Парсинг: страница {$page} собрана", ['total' => $collectedCount]);

                if ($collectedCount >= self::MAX_REVIEWS) {
                    break;
                }

                if ($totalPages !== null && $page >= $totalPages) {
                    break;
                }

                usleep(self::BETWEEN_PAGES_SLEEP_MS);
            }

            if (! $meta) {
                throw new ParseException('Не удалось прочитать мета-данные организации', ParseException::CODE_LAYOUT_CHANGED);
            }

            return new ParsedOrganization(
                yandexId: $businessId,
                title: $meta['title'],
                rating: $meta['rating'],
                ratingsCount: $meta['ratingsCount'],
                reviewsCount: $meta['reviewsCount'],
                reviews: array_values($allReviews),
            );
        } finally {
            try {
                $driver->quit();
            } catch (Throwable) {
                // ignore
            }
        }
    }

    private function createDriver(): RemoteWebDriver
    {
        $options = new ChromeOptions();
        $options->addArguments([
            '--headless=new',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--window-size=1280,1024',
            '--lang=ru-RU',
            '--disable-blink-features=AutomationControlled',
            '--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        ]);

        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

        try {
            return RemoteWebDriver::create(
                self::SELENIUM_HOST.'/wd/hub',
                $capabilities,
                120_000,
                180_000,
            );
        } catch (SessionNotCreatedException $e) {
            throw new ParseException(
                'Не удалось запустить браузер: '.$e->getMessage(),
                ParseException::CODE_HTTP_ERROR,
                $e,
            );
        } catch (Throwable $e) {
            throw new ParseException(
                'Ошибка подключения к Selenium: '.$e->getMessage(),
                ParseException::CODE_HTTP_ERROR,
                $e,
            );
        }
    }

    private function guardAgainstBlock(RemoteWebDriver $driver): void
    {
        $html = $driver->getPageSource();

        if (strlen($html) < 5_000) {
            // Пустая/сокращённая страница — скорее всего антибот
            throw new ParseException(
                'Страница вернулась слишком короткой ('.strlen($html).' байт) — возможен антибот',
                ParseException::CODE_BLOCKED,
            );
        }

        if (stripos($html, '<pre') !== false && stripos($html, 'limited') !== false) {
            throw new ParseException('Яндекс вернул заглушку "limited"', ParseException::CODE_BLOCKED);
        }

        $title = $driver->getTitle();
        if (stripos($title, 'Подтвердите') !== false || stripos($title, 'captcha') !== false) {
            throw new ParseException('Яндекс показал капчу', ParseException::CODE_BLOCKED);
        }
    }

    private function readState(RemoteWebDriver $driver): array
    {
        $json = $driver->executeScript(<<<'JS'
            try {
                const el = document.querySelector('script.state-view');
                if (!el) return null;
                return el.textContent;
            } catch(e) { return null; }
        JS);

        if (! is_string($json) || $json === '') {
            throw new ParseException('Не найден state-view на странице', ParseException::CODE_LAYOUT_CHANGED);
        }

        $data = json_decode($json, true);
        if (! is_array($data)) {
            throw new ParseException('Не удалось разобрать JSON state-view', ParseException::CODE_LAYOUT_CHANGED);
        }

        return $data;
    }

    /**
     * @return array{title: string, rating: ?float, ratingsCount: int, reviewsCount: int}
     */
    private function extractMeta(array $state): array
    {
        $item = $state['stack'][0]['results']['items'][0] ?? null;
        if (! $item) {
            throw new ParseException('В state-view нет объекта организации', ParseException::CODE_LAYOUT_CHANGED);
        }

        $ratingData = $item['ratingData'] ?? null;
        if (! $ratingData) {
            throw new ParseException('В state-view нет блока ratingData', ParseException::CODE_LAYOUT_CHANGED);
        }

        return [
            'title' => (string) ($item['title'] ?? 'Без названия'),
            'rating' => isset($ratingData['ratingValue']) ? (float) $ratingData['ratingValue'] : null,
            'ratingsCount' => (int) ($ratingData['ratingCount'] ?? 0),
            'reviewsCount' => (int) ($ratingData['reviewCount'] ?? 0),
        ];
    }

    private function extractBusinessId(string $url): string
    {
        if (! preg_match('~/org/[^/]+/(\d+)~', $url, $m)) {
            throw new ParseException(
                'Не удалось извлечь ID организации из ссылки',
                ParseException::CODE_INVALID_URL,
            );
        }

        return $m[1];
    }

    private function mapReview(array $r): ?ParsedReview
    {
        $id = $r['reviewId'] ?? null;
        $author = $r['author']['name'] ?? null;
        $text = $r['text'] ?? null;
        $rating = $r['rating'] ?? null;
        $time = $r['updatedTime'] ?? null;

        if (! $id || ! $rating || ! $time) {
            Log::warning('Пропущен отзыв без ключевых полей', ['review' => $r['reviewId'] ?? 'unknown']);
            return null;
        }

        try {
            $date = new DateTimeImmutable($time);
        } catch (Throwable) {
            return null;
        }

        return new ParsedReview(
            externalId: (string) $id,
            author: (string) ($author ?: 'Аноним'),
            date: $date,
            text: (string) ($text ?? ''),
            rating: (int) $rating,
        );
    }
}