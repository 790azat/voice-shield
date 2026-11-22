<div class="card" id="tool"
     x-data="voiceShield(@js([
        'localUrl' => $localUrl,
        'ctcModels' => $ctcModels,
        'storeUrl' => route('jobs.store'),
        'csrf' => csrf_token(),
        'maxBytes' => (int) ($maxMb * 1024 * 1024),
        'maxMb' => $maxMb,
     ]))">
    @if (! $jobId)
        {{-- ===================== Form ===================== --}}
        <form x-show="!local.job" @submit.prevent="submit" novalidate class="space-y-6">

            {{-- Where to process --}}
            <div>
                <div class="step-label"><span>0</span> Где обрабатывать</div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="preset" :class="where === 'local' && 'is-active'">
                        <input type="radio" name="where" x-model="where" value="local" class="sr-only">
                        <b>На моём компьютере</b>
                        <small>ваша видеокарта, файл никуда не уходит</small>
                    </label>
                    <label class="preset" :class="where === 'server' && 'is-active'">
                        <input type="radio" name="where" x-model="where" value="server" class="sr-only">
                        <b>На сервере VoiceShield @if ($demo)<em class="badge">демо</em>@endif</b>
                        <small>ничего не нужно устанавливать</small>
                    </label>
                </div>

                <div x-show="where === 'local'" class="local-box" x-cloak>
                    <template x-if="local.status === 'online'">
                        <p class="text-emerald-400">● Программа подключена: <span x-text="local.device"></span></p>
                    </template>
                    <template x-if="local.status === 'checking'">
                        <p class="text-muted">Ищем программу VoiceShield на компьютере…</p>
                    </template>
                    <div x-show="local.status === 'offline'" class="space-y-3">
                        <p><b>Запустите программу VoiceShield</b> — один раз установить, дальше просто открывать. Сайт подключится сам.</p>
                        <div x-show="os === 'windows'" class="space-y-2">
                            <a href="{{ route('launcher', 'windows') }}" class="btn-small inline-block">Скачать для Windows (.bat)</a>
                            <p class="hint">Нужны NVIDIA‑видеокарта, <a class="underline" href="https://www.python.org/downloads/" target="_blank" rel="noopener">Python 3.10+</a> и ffmpeg (<code>winget install ffmpeg</code>).</p>
                        </div>
                        <div x-show="os === 'unix'" class="space-y-2">
                            <p class="hint">macOS / Linux — вставьте в Терминал:</p>
                            <div class="cmd">
                                <code>curl -fsSL https://raw.githubusercontent.com/790azat/voice-shield/main/worker/start.sh | bash</code>
                                <button type="button" @click="copy('curl -fsSL https://raw.githubusercontent.com/790azat/voice-shield/main/worker/start.sh | bash')" x-text="copied ? 'Скопировано' : 'Копировать'"></button>
                            </div>
                        </div>
                        <button type="button" class="hint underline" @click="os = os === 'windows' ? 'unix' : 'windows'"
                                x-text="os === 'windows' ? 'У меня macOS или Linux' : 'У меня Windows'"></button>
                    </div>
                </div>
            </div>

            {{-- Step 1: file --}}
            <div>
                <div class="step-label"><span>1</span> Загрузите запись</div>
                <label class="dropzone" :class="{ 'is-dragging': dragging, 'has-file': file }"
                       @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false"
                       @drop.prevent="dragging = false; pick($event.dataTransfer.files[0])">
                    <input type="file" class="sr-only" accept="audio/*,.wav,.mp3,.m4a,.ogg,.opus,.flac" @change="pick($event.target.files[0])">
                    <svg class="h-8 w-8 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V4.5m0 0L7.5 9M12 4.5 16.5 9M4.5 15v3.75A1.5 1.5 0 0 0 6 20.25h12a1.5 1.5 0 0 0 1.5-1.5V15"/></svg>
                    <template x-if="!file">
                        <div><b>Перетащите файл</b> или нажмите, чтобы выбрать<br><small>WAV, MP3, M4A, OGG · каждые 30 секунд обрабатываются отдельно</small></div>
                    </template>
                    <template x-if="file">
                        <div><b x-text="file.name"></b><br><small x-text="(file.size / 1024 / 1024).toFixed(2) + ' МБ · нажмите, чтобы заменить'"></small></div>
                    </template>
                </label>
                <template x-if="errors.file"><p class="error" x-text="errors.file[0]"></p></template>
            </div>

            {{-- Step 2: goal --}}
            <div>
                <div class="step-label"><span>2</span> Что должен получить распознаватель</div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="preset">
                        <input type="radio" name="goal" wire:model.live="goal" value="break" class="sr-only">
                        <b>Ничего</b>
                        <small>ломаем распознавание: лучше работает против разных ИИ</small>
                    </label>
                    <label class="preset">
                        <input type="radio" name="goal" wire:model.live="goal" value="target" class="sr-only">
                        <b>Подменную фразу</b>
                        <small>Whisper «услышит» ваш текст вместо речи</small>
                    </label>
                </div>
                @if ($goal === 'target')
                    <input type="text" wire:model="text" maxlength="200" class="input mt-3" placeholder="Например: Спасибо за внимание">
                @endif
                <template x-if="errors.text"><p class="error" x-text="errors.text[0]"></p></template>
            </div>

            {{-- Step 3: strength --}}
            <div>
                <div class="step-label"><span>3</span> Сила защиты</div>
                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach ($presets as $key => $p)
                        <label class="preset" wire:key="preset-{{ $key }}">
                            <input type="radio" name="preset" wire:model.live="preset" value="{{ $key }}" class="sr-only">
                            <b>{{ $p['label'] }}</b>
                            <small>{{ $p['hint'] }}</small>
                        </label>
                    @endforeach
                </div>
                <label class="mt-3 flex items-start gap-3 text-sm">
                    <input type="checkbox" wire:model="ensemble" class="mt-1 accent-[var(--color-accent)]">
                    <span>Против других ИИ (Gemini, Google и т. п.): добавить в обучение модель wav2vec2.
                        <span class="text-muted">Медленнее, а переносимость на закрытые сервисы не гарантирована.</span></span>
                </label>
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
                        <small>large‑v3 требует ~12 ГБ видеопамяти; для слабых карт берите medium или small</small>
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
                <span x-show="uploading" x-cloak>Отправляем…</span>
            </button>

            @if ($demo)
                <p class="hint text-center" x-show="where === 'server'" x-cloak>Серверный GPU пока не подключён: в этом режиме файл вернётся без изменений. Для настоящей защиты выберите «На моём компьютере».</p>
            @endif
        </form>

        {{-- ===================== Local job progress ===================== --}}
        <div x-show="local.job" x-cloak class="space-y-6 text-center">
            <template x-if="local.job && local.job.status === 'done'">
                <div class="space-y-6">
                    <div class="status-icon bg-emerald-500/15 text-emerald-400">✓</div>
                    <h3 class="text-2xl font-semibold">Запись защищена</h3>
                    <audio controls class="mx-auto w-full" :src="local.job.url"></audio>
                    <a :href="local.job.url" :download="local.job.name" class="btn-primary inline-flex" x-show="local.job.url">Скачать защищённый WAV</a>
                </div>
            </template>
            <template x-if="local.job && local.job.status === 'failed'">
                <div class="space-y-4">
                    <div class="status-icon bg-red-500/15 text-red-400">!</div>
                    <h3 class="text-2xl font-semibold">Не получилось</h3>
                    <p class="text-muted whitespace-pre-line text-left text-sm" x-text="local.job.error"></p>
                </div>
            </template>
            <template x-if="local.job && !['done', 'failed'].includes(local.job.status)">
                <div class="space-y-6">
                    <div class="wave-loader" aria-hidden="true">@for ($i = 0; $i < 12; $i++)<i style="animation-delay: {{ $i * 0.08 }}s"></i>@endfor</div>
                    <h3 class="text-2xl font-semibold">Защищаем запись на вашем компьютере</h3>
                    <div class="progress"><div :style="'width:' + Math.round((local.job.progress || 0) * 100) + '%'"></div></div>
                    <p class="text-muted"><span x-text="local.job.message || 'Работаем'"></span> · <span x-text="Math.round((local.job.progress || 0) * 100) + '%'"></span></p>
                </div>
            </template>
            <button type="button" @click="reset()" class="btn-ghost">Обработать другую запись</button>
        </div>
    @else
        {{-- ===================== Server job progress ===================== --}}
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
                <p class="text-muted">{{ $job['message'] ?? 'Подбираем возмущение. Это может занять несколько минут.' }} · {{ $pct }}%</p>
            @endif
            <button type="button" wire:click="startOver" class="btn-ghost">Обработать другую запись</button>
        </div>
    @endif
</div>
