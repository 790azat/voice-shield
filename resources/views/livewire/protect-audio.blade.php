<div class="card" id="tool">
    @if (! $jobId)
        <form
            x-data="{
                file: null,
                dragging: false,
                uploading: false,
                errors: {},
                pick(f) { if (f) { this.file = f; this.errors = {}; } },
                async submit() {
                    if (!this.file) { this.errors = { file: ['Выберите аудиофайл.'] }; return; }
                    this.uploading = true; this.errors = {};
                    const body = new FormData();
                    body.append('file', this.file);
                    for (const key of ['text', 'lang', 'models', 'eps', 'steps', 'lr']) body.append(key, $wire[key]);
                    try {
                        const res = await fetch('{{ route('jobs.store') }}', {
                            method: 'POST', body,
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        });
                        const data = await res.json().catch(() => ({}));
                        if (res.status === 413) { this.errors = { file: ['Файл слишком большой.'] }; return; }
                        if (!res.ok) { this.errors = data.errors ?? { form: [data.message ?? 'Не удалось загрузить файл.'] }; return; }
                        await $wire.track(data.id);
                    } catch (e) {
                        this.errors = { form: ['Ошибка сети. Проверьте подключение и попробуйте снова.'] };
                    } finally { this.uploading = false; }
                },
            }"
            @submit.prevent="submit"
            novalidate
            class="space-y-6"
        >
            {{-- Step 1: file --}}
            <div>
                <div class="step-label"><span>1</span> Загрузите запись</div>
                <label
                    class="dropzone"
                    :class="{ 'is-dragging': dragging, 'has-file': file }"
                    @dragover.prevent="dragging = true"
                    @dragleave.prevent="dragging = false"
                    @drop.prevent="dragging = false; pick($event.dataTransfer.files[0])"
                >
                    <input type="file" class="sr-only" accept="audio/*,.wav,.mp3,.m4a,.ogg,.opus,.flac" @change="pick($event.target.files[0])">
                    <svg class="h-8 w-8 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V4.5m0 0L7.5 9M12 4.5 16.5 9M4.5 15v3.75A1.5 1.5 0 0 0 6 20.25h12a1.5 1.5 0 0 0 1.5-1.5V15"/></svg>
                    <template x-if="!file">
                        <div><b>Перетащите файл</b> или нажмите, чтобы выбрать<br><small>WAV, MP3, M4A, OGG · до {{ $maxMb }} МБ · лучше всего до 30 секунд</small></div>
                    </template>
                    <template x-if="file">
                        <div><b x-text="file.name"></b><br><small x-text="(file.size / 1024 / 1024).toFixed(2) + ' МБ · нажмите, чтобы заменить'"></small></div>
                    </template>
                </label>
                <template x-if="errors.file"><p class="error" x-text="errors.file[0]"></p></template>
            </div>

            {{-- Step 2: decoy text --}}
            <div>
                <div class="step-label"><span>2</span> Что должен «услышать» распознаватель</div>
                <input type="text" wire:model="text" maxlength="200" class="input" placeholder="Например: Спасибо за внимание">
                <p class="hint">Вместо вашей речи система расшифровки выдаст эту фразу. Люди по-прежнему слышат оригинал.</p>
                <template x-if="errors.text"><p class="error" x-text="errors.text[0]"></p></template>
            </div>

            {{-- Step 3: strength --}}
            <div>
                <div class="step-label"><span>3</span> Сила защиты</div>
                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach ($presets as $key => $p)
                        <label class="preset" wire:key="preset-{{ $key }}">
                            <input type="radio" wire:model.live="preset" value="{{ $key }}" class="sr-only">
                            <b>{{ $p['label'] }}</b>
                            <small>{{ $p['hint'] }}</small>
                        </label>
                    @endforeach
                </div>
            </div>

            <details class="advanced">
                <summary>Дополнительные настройки</summary>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="field">Язык речи
                        <select wire:model="lang" class="input">
                            @foreach ($languages as $code => $name)
                                <option value="{{ $code }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">Модели Whisper
                        <select wire:model="models" class="input">
                            @foreach ($modelOptions as $m)
                                <option value="{{ $m }}">{{ $m }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">eps (громкость возмущения)
                        <input type="number" step="any" min="0.001" max="0.2" wire:model="eps" class="input">
                    </label>
                    <label class="field">Шаги оптимизации
                        <input type="number" step="100" min="100" max="10000" wire:model="steps" class="input">
                    </label>
                    <label class="field">Learning rate
                        <input type="number" step="any" min="0.00001" max="0.01" wire:model="lr" class="input">
                    </label>
                </div>
                <template x-if="errors.eps || errors.steps || errors.lr || errors.lang || errors.models">
                    <p class="error">Проверьте дополнительные настройки.</p>
                </template>
            </details>

            <template x-if="errors.form"><p class="error" x-text="errors.form[0]"></p></template>

            <button type="submit" class="btn-primary w-full" :disabled="uploading">
                <span x-show="!uploading">Защитить запись</span>
                <span x-show="uploading" x-cloak>Загружаем…</span>
            </button>

            @if ($demo)
                <p class="hint text-center">Сейчас сайт работает в демо-режиме: GPU-сервер не подключён, и файл вернётся без изменений.</p>
            @endif
        </form>
    @else
        @php($status = $job['status'] ?? 'queued')
        <div class="space-y-6 text-center" @if (! in_array($status, ['done', 'failed'])) wire:poll.2s="refresh" @endif>
            @if ($status === 'done')
                <div class="status-icon bg-emerald-500/15 text-emerald-400">✓</div>
                <h3 class="text-2xl font-semibold">Запись защищена</h3>
                <p class="text-muted">{{ $job['message'] ?? 'Скачайте файл и публикуйте его вместо оригинала.' }}</p>
                <a href="{{ route('jobs.download', $jobId) }}" class="btn-primary inline-flex">Скачать защищённый WAV</a>
            @elseif ($status === 'failed')
                <div class="status-icon bg-red-500/15 text-red-400">!</div>
                <h3 class="text-2xl font-semibold">Не получилось</h3>
                <p class="text-muted">{{ $job['error'] ?? 'Обработка завершилась с ошибкой.' }}</p>
            @else
                <div class="wave-loader" aria-hidden="true">@for ($i = 0; $i < 12; $i++)<i style="animation-delay: {{ $i * 0.08 }}s"></i>@endfor</div>
                <h3 class="text-2xl font-semibold">{{ $status === 'queued' ? 'В очереди' : 'Защищаем запись' }}</h3>
                @php($pct = (int) round(($job['progress'] ?? 0) * 100))
                <div class="progress"><div style="width: {{ $pct }}%"></div></div>
                <p class="text-muted">{{ $job['message'] ?? 'Подбираем возмущение под Whisper. Это может занять несколько минут.' }} · {{ $pct }}%</p>
            @endif
            <button type="button" wire:click="startOver" class="btn-ghost">Обработать другую запись</button>
        </div>
    @endif
</div>
