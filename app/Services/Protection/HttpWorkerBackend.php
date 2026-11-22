<?php

namespace App\Services\Protection;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Talks to the Python GPU worker from the worker/ folder
 * (Hugging Face Space, Colab tunnel, RunPod, your own server...).
 */
class HttpWorkerBackend implements ProtectionBackend
{
    public function __construct(
        private readonly string $url,
        private readonly ?string $token,
        private readonly int $timeout,
    ) {
        if ($url === '') {
            throw new RuntimeException('PROTECTION_WORKER_URL is not set.');
        }
    }

    private function client(): PendingRequest
    {
        $client = Http::baseUrl($this->url)->timeout($this->timeout)->acceptJson();

        return $this->token ? $client->withToken($this->token) : $client;
    }

    public function submit(UploadedFile $audio, array $params): string
    {
        $response = $this->client()
            ->attach('file', fopen($audio->getRealPath(), 'r'), $audio->getClientOriginalName())
            ->post('/jobs', array_map('strval', $params))
            ->throw();

        return (string) $response->json('id');
    }

    public function status(string $jobId): array
    {
        $data = $this->client()->get('/jobs/'.rawurlencode($jobId))->throw()->json();

        return [
            'status' => $data['status'] ?? 'failed',
            'progress' => isset($data['progress']) ? (float) $data['progress'] : null,
            'message' => $data['message'] ?? null,
            'error' => $data['error'] ?? null,
        ];
    }

    public function download(string $jobId, string $filename): Response
    {
        $response = $this->client()->timeout(120)->get('/jobs/'.rawurlencode($jobId).'/result')->throw();

        return response($response->body(), 200, [
            'Content-Type' => 'audio/wav',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
