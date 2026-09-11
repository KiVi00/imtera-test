Тестовое задание: интеграция с Яндекс.Картами
Сервис для работы с отзывами и данными организаций на Яндекс.Картах. Пользователь логинится, добавляет ссылку на карточку, приложение парсит отзывы и показывает их с пагинацией.

Стек
Backend: Laravel 13, PHP 8.5, MySQL, Redis, Sanctum

Frontend: Vue 3 (Composition API), Vue Router, Pinia, Axios, Vite, Tailwind CSS

Парсинг: Selenium (headless Chromium), php-webdriver

Инфраструктура: Docker, Laravel Sail

Установка и запуск
Требования: Docker, WSL2 (для Windows), Composer, Node.js.

bash
git clone https://github.com/KiVi00/imtera-test.git
cd imtera-test
composer install
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
Приложение доступно по адресу http://localhost. Сид-пользователь: kirill@gmail.com / password123.

Для парсинга нужен воркер очереди — запускается в отдельном терминале:

bash
./vendor/bin/sail artisan queue:work --tries=3 --timeout=300
Про переменные окружения. Всё нужное уже есть в .env.example. Для SPA-аутентификации через Sanctum обязательно должны быть заданы SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1 и SESSION_DOMAIN=localhost. QUEUE_CONNECTION=redis.

Что реализовано
Авторизация через Sanctum (SPA-режим, cookie-based).

Страница настроек: поле для ссылки, валидация URL, сохранение в БД.

Парсинг организации в фоне через очередь.

Страница организации: рейтинг, число оценок, число отзывов, список отзывов с пагинацией по 50 без перезагрузки.

Парсинг Яндекс.Карт
Это центральная часть задания, и на неё ушло больше всего времени. Расскажу, как я к ней подходил.

Что я пробовал сначала
Открыл карточку в браузере, посмотрел Network и нашёл эндпоинт, который Яндекс использует для подгрузки отзывов:

text
https://yandex.ru/maps/api/business/fetchReviews?ajax=1&businessId=...&csrfToken=...&offset=0&limit=50
Наивно попробовал повторить этот запрос через Http::get() — с тем csrfToken, который лежит в state-view. Яндекс вернул новый токен вместо данных. Тогда я добавил CookieJar, потом retry с обновлённым токеном — но получал новый токен снова и снова. Как я понял позже, правильный csrfToken (формата число:число-balancer-...-BAL) генерируется в JS-коде страницы и привязан к сессии; достать его снаружи, не исполняя JS, невозможно.

Что выбрал в итоге
Переключился на headless-браузер (Selenium). Реальный Chromium сам получает все cookie, сессию и токены. Мы просто открываем страницу и читаем данные, которые уже есть в HTML.

Как это работает:

Открываем страницу https://yandex.ru/maps/org/x/{businessId}/reviews/?page=N в headless-браузере.

Из HTML вытаскиваем <script type="application/json" class="state-view"> — это SSR-состояние, где лежат первые 50 отзывов, рейтинг и счётчики.

Для остальных страниц используем пагинацию через URL: ?page=1, ?page=2 и так далее до min(600, totalPages).

Отзывы дедуплицируем по reviewId.

Плюсы: не нужно разбираться с токенами, устойчиво к изменениям в JS-логике.
Минусы: медленно (~40–60 секунд на организацию), каждый парсинг запускает отдельный Chromium.

Про устойчивость к смене разметки
Парсер не молчит, если что-то пошло не так. Он явно проверяет:

Наличие state-view в HTML → иначе CODE_LAYOUT_CHANGED.

Наличие stack[0].results.items[0].reviewResults → иначе CODE_LAYOUT_CHANGED.

Наличие ratingData → иначе CODE_LAYOUT_CHANGED.

Длина HTML < 5000 байт → CODE_BLOCKED (признак антибот-заглушки).

Title содержит «Подтвердите» или «captcha» → CODE_BLOCKED.

Все ошибки пишутся в parse_error организации и в лог. Так сразу видно, что источник изменился, а не просто «0 отзывов».

Про антибан
Пауза 800 мс между страницами.

Подставляется реальный User-Agent Chrome.

В аргументы Chrome добавлен --disable-blink-features=AutomationControlled.

При 403/429 или заглушке limited выбрасывается CODE_BLOCKED, и джоба повторяется.

Для продакшена пригодилась бы ротация прокси, но в рамках тестового я её не делал.

Архитектура
Логика парсинга вынесена из контроллеров:

App\Services\YandexMaps\YandexMapsParser — только получает данные из внешнего источника и возвращает DTO.

App\Services\YandexMaps\OrganizationPersister — только сохраняет в БД.

App\Jobs\ParseOrganizationJob — оркестратор: запускает парсер, обрабатывает ошибки, сохраняет результат.

DTO (ParsedOrganization, ParsedReview) и ParseException с типами ошибок — в App\Services\YandexMaps\Dto и App\Services\YandexMaps\Exceptions.

Фоновая обработка и идемпотентность
Очередь. Парсинг вынесен в ParseOrganizationJob (Redis). Параметры: $tries = 3, $backoff = 30, $timeout = 300. В config/queue.php параметр retry_after увеличен до 350, чтобы джоба не перезапускалась раньше времени. Для 50 филиалов можно поднять несколько воркеров.

Идемпотентность. При повторном парсинге дубли не создаются: updateOrCreate по ключу (organization_id, external_id). В миграции reviews есть уникальный индекс на эти два поля.

История изменений (не реализована, только описана). Для отслеживания «было → стало» я бы добавил таблицу review_snapshots:

text
review_snapshots
- id
- review_id (FK на reviews.id)
- parsing_run_id (FK на parsing_runs.id)
- old_data (JSON)
- new_data (JSON)
- changed_at (timestamp)
При каждом парсинге создаётся запись parsing_run, и для каждого изменившегося отзыва — запись в review_snapshots. Модель данных продумана, но реализовывать её я не стал в рамках тестового.

Чему я научился в процессе
Пишу честно, потому что это важно: Vue 3 и Selenium я до этого задания не использовал. Laravel знал на базовом уровне.

Что пришлось освоить:

Vue 3 Composition API, Vue Router, Pinia — на первом этапе не понимал, как связать SPA-роутер с Sanctum, разбирался в router.beforeEach и meta.auth.

Sanctum в SPA-режиме — долго не мог понять, почему логин через curl работает, а через браузер нет. Оказалось, дело в Origin/Referer и CSRF-токене, который axios подставляет сам.

Selenium и headless-браузер — понял, как работает WebDriver, executeScript, executeAsyncScript, почему важно подменять User-Agent (без этого Яндекс отдаёт заглушку limited).

Антибот-защита Яндекса — узнал, что нельзя доверять токену из state-view, что правильный токен генерируется в JS, и что пагинация через URL — самый надёжный обход.

Очереди и отладка фоновых джоб — столкнулся с Job timed out, узнал про $timeout и retry_after.

Idempotency через updateOrCreate — осознал, насколько это важно для повторных парсингов.

Что доделал бы, имея больше времени
review_snapshots + UI для просмотра «было → стало».

Прокси-ротация и ротация User-Agent для парсинга 50 филиалов.

Прогресс парсинга на фронте через polling.

Мониторинг и алерт при parse_status = failed.

Unit-тесты на парсер, мокая Selenium.

Известные ограничения
Парсер медленный (~40–60 секунд на организацию) — это осознанный trade-off в пользу надёжности.

Пагинация завязана на URL ?page=N. Если Яндекс её поменяет — потребуется адаптация.

Прокси-ротации нет, поэтому при частом парсинге возможна блокировка.

Комментарий
Это моё первое задание, где я столкнулся с антибот-защитой и с Vue. Я потратил на это больше времени, чем ожидал, но разобрался. Буду рад обратной связи — особенно по тому, что можно было сделать правильнее.