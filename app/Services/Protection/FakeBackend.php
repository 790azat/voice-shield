<?php

namespace App\Services\Protection;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Demo backend: no GPU, no changes to the audio. It only simulates the
 * progress so the whole site can be tried before the worker is deployed.
 */
class FakeBackend implements ProtectionBackend
{
    public function __construct(
        private readonly string $path,
        private readonly int $duration,
    ) {}

    public function submit(UploadedFile $audio, array $params): string
    {
        $id = (string) Str::uuid();
        @mkdir($this->path, 0775, true);
        $audio->move($this->path, $id.'.audio');
        file_put_contents($this->path.'/'.$id.'.json', json_encode(['started' => time()]));

        return $id;
    }

    public function status(string $jobId): array
    {
        $meta = $this->path.'/'.basename($jobId).'.json';

        if (! is_file($meta)) {
            return ['status' => 'failed', 'progress' => null, 'message' => null, 'error' => 'Задача не найдена (демо-режим хранит файлы временно).'];
        }

        $started = json_decode((string) file_get_contents($meta), true)['started'] ?? time();
        $progress = min(1, (time() - $started) / max(1, $this->duration));

        return $progress >= 1
            ? ['status' => 'done', 'progress' => 1.0, 'message' => 'Демо-режим: файл возвращён без изменений.', 'error' => null]
            : ['status' => 'running', 'progress' => $progress, 'message' => 'Демо-режим: имитация обработки', 'error' => null];
    }

    public function download(string $jobId, string $filename): Response
    {
        $file = $this->path.'/'.basename($jobId).'.audio';
        abort_unless(is_file($file), 404);

        return response()->download($file, $filename);
    }
}
