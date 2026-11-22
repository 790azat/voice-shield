<?php

namespace App\Livewire;

use App\Services\Protection\ProtectionBackend;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

class ProtectAudio extends Component
{
    /** break = make recognition fail, target = make Whisper hear $text */
    public string $goal = 'break';

    public string $text = 'Спасибо за внимание';

    public bool $ensemble = true;

    public string $lang = 'ru';

    public string $models = 'large-v3';

    public string $preset = 'standard';

    public float $eps = 0.03;

    public int $steps = 2000;

    public float $lr = 0.0007;

    #[Locked]
    public ?string $jobId = null;

    #[Locked]
    public array $job = [];

    public function updatedPreset(string $value): void
    {
        $preset = config("voiceshield.presets.$value");

        if ($preset) {
            $this->eps = $preset['eps'];
            $this->steps = $preset['steps'];
            $this->lr = $preset['lr'];
        }
    }

    /** Called from the browser once the upload has been accepted. */
    public function track(string $id): void
    {
        abort_unless(session()->has("jobs.$id"), 403);

        $this->jobId = $id;
        $this->job = ['status' => 'queued', 'progress' => 0, 'message' => null, 'error' => null];
    }

    public function refresh(ProtectionBackend $backend): void
    {
        if (! $this->jobId || in_array($this->job['status'] ?? null, ['done', 'failed'], true)) {
            return;
        }

        try {
            $this->job = $backend->status($this->jobId);
        } catch (Throwable $e) {
            report($e);
            $this->job['message'] = 'Нет связи с сервером обработки, пробуем ещё раз…';
        }
    }

    public function startOver(): void
    {
        $this->reset('jobId', 'job');
    }

    public function render()
    {
        return view('livewire.protect-audio', [
            'languages' => config('voiceshield.languages'),
            'modelOptions' => config('voiceshield.models'),
            'presets' => config('voiceshield.presets'),
            'maxMb' => round(config('voiceshield.max_upload_kb') / 1024, 1),
            'demo' => config('voiceshield.driver') !== 'http',
            'localUrl' => config('voiceshield.local_worker_url'),
            'ctcModels' => config('voiceshield.ctc_models'),
        ]);
    }
}
