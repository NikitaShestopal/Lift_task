# User Management API (Symfony + MongoDB + Redis)

Бекенд-додаток на базі **Symfony 8**, розроблений для ефективного управління користувачами з використанням **MongoDB (Doctrine ODM)** + **Redis**. Проєкт реалізує асинхронну обробку даних через черги повідомлень, має вбудований механізм ідемпотентності для захисту від дублювання запитів та повністю покритий функціональними тестами.

---

## Технологічний стек

*   **Framework:** Symfony 8 (PHP 8.4)
*   **Database:** MongoDB via Doctrine ODM
*   **Queue Manager:** Symfony Messenger Bus
*   **Documentation:** OpenAPI / Swagger (via NelmioApiDocBundle)
*   **Testing:** PHPUnit 13
*   **Environment:** Docker & Docker Compose
*   **Broker Notifier:** Redis

---

## Ключові фічі та архітектура

1. **Асинхронність на POST запитах:** При створенні користувача дані валідуються та миттєво відправляються в чергу повідомлень (`MessageBusInterface`). Клієнт миттєво отримує відповідь `202 Прийнято`, що розвантажує HTTP-потік.
2. **Захист від дублів (Idempotency Blocker):** Система блокує повторні запити за першим номером телефону за допомогою унікальних локів де затримка на блокування на відправку в чергу стоїть 10 сеекунд але можна змінити. Якщо запит уже обробляється, повертається статус `429 За багато надходжень`.
3. **Гнучке сортування (GET запити):** Можливість динамічного сортування користувачів за білим списком полів (`firstName`, `lastName`, `phoneNumbers`, `ipAddress`, `country`) у напрямках `asc` та `desc`.
4. **Чиста документація:** Контролери очищені від OpenAPI-шуму — вся Swagger-документація винесена в ізольовану YAML-конфігурацію.

---

## Швидкий старт (Docker)

Переконайтеся, що у вас встановлені **Docker** та **Docker Compose**.

### 1. Клонування та запуск контейнерів
Підніміть Docker-оточення у фоновому режимі:
```bash
docker compose up -d --build
```
При наступних включеннях:
```bash
docker compose up -d
```

2. Встановлення залежностей Composer
Встановіть усі необхідні PHP-пакети всередині контейнера:

```bash
docker compose exec php composer install
```

API Документація (Swagger UI)
Після запуску контейнерів інтерактивна документація API доступна за адресою:

http://localhost/api/doc (якщо ваш веб-сервер слухає 80-й порт).
Запуск воркера з можливість моніторити що відбувається з чергами редіс
```bash
php bin/console messenger:consume async -vv 
```
Для запуск тестів
```bash
docker compose exec php vendor/bin/phpunit tests/Controller/UserControllerTest.php
```

Доступні Endpoints:
POST /api/users — Створення нового користувача (Валідація ->️ Лок ідемпотентності -> Черга).

GET /api/users — Отримання списку користувачів із MongoDB з підтримкою сортування.

Тестування
Проєкт покритий автоматичними функціональними тестами за допомогою WebTestCase. Тести повністю ізольовані від реальної бази даних за допомогою моків (Mocking), тому виконуються миттєво.

Запуск тестів всередині Docker-контейнера:

```bash
docker compose exec php vendor/bin/phpunit tests/Controller/UserControllerTest.php
```

Структура проєкту
```bash
Lift_task
├── config/                  # Конфігурація Symfony
│   └── packages/
│       └── nelmio_api_doc.yaml # API-документація (YAML)
├── src/
│   ├── Controller/          # Чисті HTTP-контролери
│   ├── Document/            # MongoDB Документи (User)
│   ├── DTO/                 # Data Transfer Objects (UserDTO)
│   └── Services/            # Бізнес-логіка (Валідація, Ідемпотентність)
├── tests/                   # Автотести (PHPUnit)
├── docker-compose.yml       # Конфігурація Docker-сервісів
└── README.md                # Цей файл
```
 
