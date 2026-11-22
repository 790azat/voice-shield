<?php

namespace App\Services\Protection;

use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

interface ProtectionBackend
{
    /**
     * Start processing and return the job id.
     *
     * @param  array{text: string, lang: string, models: string, eps: float, steps: int, lr: float, extra_models: string}  $params
     */
    public function submit(UploadedFile $audio, array $params): string;

    /**
     * @return array{status: string, progress: float|null, message: string|null, error: string|null}
     *                status is one of: queued, running, done, failed
     */
    public function status(string $jobId): array;

    /** Stream the protected WAV file back to the browser. */
    public function download(string $jobId, string $filename): Response;
}
