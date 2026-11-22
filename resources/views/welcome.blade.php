<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VoiceShield — защита голоса от автоматической расшифровки</title>
    <meta name="description" content="Добавьте в запись почти неслышимую защиту, и Whisper вместо вашей речи распознает безобидную фразу. Для подкастов, голосовых сообщений и интервью.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header class="container flex items-center justify-between py-6">
        <a href="/" class="flex items-center gap-2 text-lg font-bold">
            <span class="logo">◖◗</span> VoiceShield
        </a>
        <nav class="hidden gap-8 text-sm text-muted sm:flex">
            <a href="#how">Как это работает</a>
            <a href="#who">Для кого</a>
            <a href="#faq">Вопросы</a>
        </nav>
        <a href="#tool" class="btn-small">Попробовать</a>
    </header>

    <main>
        {{-- Hero --}}
        <section class="container grid items-center gap-12 pb-24 pt-12 lg:grid-cols-[1.05fr_1fr]">
            <div>
                <p class="eyebrow">Приватность голоса</p>
                <h1 class="text-4xl font-extrabold leading-tight tracking-tight sm:text-6xl">
                    Защитите свой голос от <span class="text-gradient">автоматической расшифровки</span>
                </h1>
                <p class="mt-6 max-w-xl text-lg text-muted">
                    VoiceShield добавляет в запись почти неслышимое возмущение. Люди слышат вас как обычно,
                    а нейросеть Whisper вместо ваших слов «распознаёт» фразу, которую выберете вы.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="#tool" class="btn-primary">Защитить запись</a>
                    <a href="#how" class="btn-ghost">Как это работает</a>
                </div>

                <div class="transcript mt-12">
                    <div>
                        <small>Вы говорите</small>
                        <p>«Встречаемся в четверг у офиса, пароль от Wi‑Fi — тот же»</p>
                    </div>
                    <div class="arrow">→</div>
                    <div>
                        <small>Whisper слышит</small>
                        <p class="text-accent">«Спасибо за внимание»</p>
                    </div>
                </div>
            </div>

            <livewire:protect-audio />
        </section>

        {{-- How --}}
        <section id="how" class="section">
            <div class="container">
                <p class="eyebrow">Как это работает</p>
                <h2 class="section-title">Три шага, и запись защищена</h2>
                <div class="mt-12 grid gap-6 md:grid-cols-3">
                    <div class="feature">
                        <span class="num">01</span>
                        <h3>Загрузите аудио</h3>
                        <p>WAV, MP3, M4A или голосовое сообщение. Лучше всего работают фрагменты до 30 секунд.</p>
                    </div>
                    <div class="feature">
                        <span class="num">02</span>
                        <h3>Выберите защиту</h3>
                        <p>Задайте фразу-приманку и силу защиты. Мы оптимизируем возмущение под Whisper large‑v3 на GPU.</p>
                    </div>
                    <div class="feature">
                        <span class="num">03</span>
                        <h3>Скачайте результат</h3>
                        <p>Получите защищённый WAV. Для слушателей он звучит как оригинал, для автоматической расшифровки — нет.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- Who --}}
        <section id="who" class="section">
            <div class="container">
                <p class="eyebrow">Для кого</p>
                <h2 class="section-title">Ваш голос не должен становиться чужим текстом</h2>
                <div class="mt-12 grid gap-6 md:grid-cols-3">
                    <div class="feature">
                        <div class="icon">🎙️</div>
                        <h3>Подкастеры</h3>
                        <p>Мешайте автоматически превращать выпуски в тексты, которые потом копируют, перепродают и пускают на обучение моделей.</p>
                    </div>
                    <div class="feature">
                        <div class="icon">💬</div>
                        <h3>Голосовые сообщения</h3>
                        <p>Пересланное голосовое не так просто прогнать через расшифровку и найти по ключевым словам.</p>
                    </div>
                    <div class="feature">
                        <div class="icon">📰</div>
                        <h3>Интервью и журналисты</h3>
                        <p>Защитите записи источников и рабочие материалы от массовой автоматической обработки.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- FAQ --}}
        <section id="faq" class="section">
            <div class="container max-w-3xl">
                <p class="eyebrow">Вопросы</p>
                <h2 class="section-title">Частые вопросы</h2>
                <div class="mt-10 space-y-3">
                    <details class="faq" open>
                        <summary>Защиту будет слышно?</summary>
                        <p>Возмущение ограничено параметром eps, поэтому на стандартной силе оно почти незаметно. На сильной защите в тишине можно услышать лёгкий шум.</p>
                    </details>
                    <details class="faq">
                        <summary>От каких систем это защищает?</summary>
                        <p>Защита оптимизируется под модели OpenAI Whisper (по умолчанию large‑v3, можно добавить medium и small). Другие системы распознавания могут реагировать иначе, гарантий против них нет.</p>
                    </details>
                    <details class="faq">
                        <summary>Сколько длится обработка?</summary>
                        <p>Обычно от одной до нескольких минут на GPU. Это зависит от длины записи и числа шагов оптимизации.</p>
                    </details>
                    <details class="faq">
                        <summary>Вы храните мои записи?</summary>
                        <p>Файл нужен только на время обработки. Сайт не сохраняет аудио, а сервер обработки удаляет его после выдачи результата.</p>
                    </details>
                    <details class="faq">
                        <summary>Защита переживёт сжатие в мессенджере?</summary>
                        <p>Сильное перекодирование (например, в OGG с низким битрейтом) может ослабить эффект. Для лучшего результата публикуйте WAV или выбирайте сильную защиту.</p>
                    </details>
                </div>
            </div>
        </section>
    </main>

    <footer class="container border-t border-line py-10 text-sm text-muted">
        © {{ date('Y') }} VoiceShield · Защита голоса от автоматического распознавания речи
    </footer>
</body>
</html>
