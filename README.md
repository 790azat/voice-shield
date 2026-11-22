# VoiceShield

Сайт сервиса, который защищает голосовые записи от автоматической расшифровки.
Пользователь загружает аудио, выбирает фразу-приманку и силу защиты, а получает WAV,
который для людей звучит как оригинал, а Whisper (large‑v3) распознаёт как выбранную фразу.

**Стек:** Laravel 13 · Livewire 4 · Tailwind 4 · деплой на Vercel (`vercel-php`) · Python GPU‑воркер.

## Архитектура

```
Браузер ──► Laravel + Livewire (Vercel)  ──HTTP──►  GPU-воркер (worker/, FastAPI)
              лендинг, форма, статус                 whisper_protect.py на GPU
```

Vercel не даёт GPU и не хранит файлы между запросами, поэтому:

* сайт принимает файл и **в том же запросе** пересылает его воркеру (ничего не хранит у себя);
* статус обработки Livewire опрашивает раз в 2 секунды (`wire:poll`);
* скачивание идёт через сайт (`/jobs/{id}/download`), так что токен воркера не виден браузеру;
* лимит загрузки 4 МБ — это ограничение тела запроса на Vercel (4,5 МБ). Для Whisper этого хватает: модель работает окнами по 30 секунд.

Бэкенд обработки подключаемый (`app/Services/Protection`):

| `PROTECTION_DRIVER` | Что делает |
|---|---|
| `fake` (по умолчанию) | Демо без GPU: имитирует прогресс и возвращает исходный файл |
| `http` | Отправляет аудио воркеру по `PROTECTION_WORKER_URL` с токеном `PROTECTION_WORKER_TOKEN` |

Чтобы добавить другой бэкенд (Replicate, Modal, RunPod Serverless…), реализуйте интерфейс
`ProtectionBackend` и зарегистрируйте его в `AppServiceProvider`.

## GPU‑воркер (`worker/`)

FastAPI‑обёртка вокруг `whisper_protect.py`: очередь задач (по одной на GPU), конвертация любого аудио
в WAV 16 кГц через ffmpeg, прогресс по строкам вида `step 120/2000` или по tqdm, автоудаление файлов через час.

1. Положите `whisper_protect.py` в папку `worker/` и при необходимости поправьте `worker/requirements.txt`.
2. Запустите воркер одним из способов:
   * **Hugging Face Space** (Docker, GPU‑железо) — загрузите содержимое `worker/`, добавьте секрет `WORKER_TOKEN`;
   * **Google Colab** для тестов — см. [`worker/colab.md`](worker/colab.md);
   * любой сервер с NVIDIA GPU: `docker build -t voiceshield-worker worker && docker run --gpus all -p 7860:7860 -e WORKER_TOKEN=... voiceshield-worker`.
3. На Vercel задайте `PROTECTION_DRIVER=http`, `PROTECTION_WORKER_URL`, `PROTECTION_WORKER_TOKEN` и сделайте Redeploy.

API воркера: `POST /jobs` (multipart: `file, text, lang, models, eps, steps, lr`), `GET /jobs/{id}`, `GET /jobs/{id}/result`.

## Деплой на Vercel

1. Импортируйте репозиторий в Vercel (Framework Preset: **Other**). Всё остальное уже в `vercel.json`.
2. Добавьте переменную окружения `APP_KEY` (сгенерируйте: `php artisan key:generate --show`).
3. Для реальной обработки добавьте переменные воркера (см. выше). Без них сайт работает в демо‑режиме.

Собранные стили лежат в `public/build` и закоммичены, поэтому Vercel не обязан собирать фронтенд.
После правок стилей выполните `npm run build` и закоммитьте результат.

## Локальная разработка

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
npm run build
php artisan serve
```

Тесты: `php artisan test`.
