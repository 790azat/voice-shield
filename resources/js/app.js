// Client-side part of the protection form. The "on my computer" mode talks
// straight to the worker running on the visitor's machine (their own GPU).
document.addEventListener('alpine:init', () => {
    window.Alpine.data('voiceShield', (cfg) => ({
        where: 'local',
        file: null,
        dragging: false,
        uploading: false,
        errors: {},
        local: { status: 'checking', device: null, job: null },
        os: /Win/.test(navigator.userAgent) ? 'windows' : 'unix',
        copied: false,

        init() {
            this.checkLocal();
            setInterval(() => { if (this.where === 'local' && this.local.status !== 'online') this.checkLocal(); }, 5000);
        },

        pick(f) { if (f) { this.file = f; this.errors = {}; } },

        async checkLocal() {
            try {
                const res = await fetch(cfg.localUrl + '/', { signal: AbortSignal.timeout(2500) });
                const data = await res.json();
                if (data.app !== 'voice-shield-worker') throw new Error();
                this.local.status = 'online';
                this.local.device = data.device;
            } catch (e) {
                this.local.status = 'offline';
            }
        },

        copy(text) {
            navigator.clipboard?.writeText(text);
            this.copied = true;
            setTimeout(() => (this.copied = false), 1500);
        },

        fields() {
            const w = this.$wire;
            return {
                text: w.goal === 'target' ? w.text : '',
                lang: w.lang, models: w.models, eps: w.eps, steps: w.steps, lr: w.lr,
            };
        },

        async submit() {
            if (!this.file) { this.errors = { file: ['Выберите аудиофайл.'] }; return; }
            if (this.$wire.goal === 'target' && !String(this.$wire.text).trim()) {
                this.errors = { text: ['Введите фразу.'] }; return;
            }
            this.errors = {};
            this.uploading = true;
            try {
                this.where === 'local' ? await this.submitLocal() : await this.submitServer();
            } catch (e) {
                this.errors = { form: [this.where === 'local'
                    ? 'Не удалось связаться с программой на компьютере. Проверьте, что она запущена.'
                    : 'Ошибка сети. Проверьте подключение и попробуйте снова.'] };
            } finally {
                this.uploading = false;
            }
        },

        async submitServer() {
            if (this.file.size > cfg.maxBytes) { this.errors = { file: ['Для сервера файл должен быть меньше ' + cfg.maxMb + ' МБ. На своём компьютере ограничений нет.'] }; return; }
            const body = new FormData();
            body.append('file', this.file);
            for (const [k, v] of Object.entries(this.fields())) body.append(k, v);
            body.append('ensemble', this.$wire.ensemble ? 1 : 0);
            const res = await fetch(cfg.storeUrl, { method: 'POST', body, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf } });
            const data = await res.json().catch(() => ({}));
            if (res.status === 413) { this.errors = { file: ['Файл слишком большой.'] }; return; }
            if (!res.ok) { this.errors = data.errors ?? { form: [data.message ?? 'Не удалось загрузить файл.'] }; return; }
            await this.$wire.track(data.id);
        },

        async submitLocal() {
            await this.checkLocal();
            if (this.local.status !== 'online') { this.errors = { form: ['Сначала запустите программу VoiceShield на компьютере (инструкция выше).'] }; return; }
            const body = new FormData();
            body.append('file', this.file);
            for (const [k, v] of Object.entries(this.fields())) body.append(k, v);
            body.append('extra_models', this.$wire.ensemble ? (cfg.ctcModels[this.$wire.lang] ?? '') : '');
            const res = await fetch(cfg.localUrl + '/jobs', { method: 'POST', body });
            const data = await res.json();
            if (!res.ok) { this.errors = { form: [data.detail ?? 'Программа вернула ошибку.'] }; return; }
            const base = this.file.name.replace(/\.[^.]+$/, '') || 'audio';
            this.local.job = { id: data.id, status: 'queued', progress: 0, message: 'В очереди', error: null, url: null, name: base + '-protected.wav' };
            this.poll();
        },

        async poll() {
            const job = this.local.job;
            if (!job || ['done', 'failed'].includes(job.status)) return;
            try {
                const res = await fetch(cfg.localUrl + '/jobs/' + job.id);
                Object.assign(job, await res.json());
                if (job.status === 'done') {
                    const blob = await (await fetch(cfg.localUrl + '/jobs/' + job.id + '/result')).blob();
                    job.url = URL.createObjectURL(blob);
                    return;
                }
            } catch (e) {
                job.message = 'Нет связи с программой на компьютере, пробуем ещё раз…';
            }
            setTimeout(() => this.poll(), 2000);
        },

        reset() {
            if (this.local.job?.url) URL.revokeObjectURL(this.local.job.url);
            this.local.job = null;
            this.file = null;
        },
    }));
});
