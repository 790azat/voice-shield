<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProtectTest extends TestCase
{
    public function test_landing_page_renders(): void
    {
        $this->get('/')->assertOk()->assertSee('Защитите свой голос')->assertSeeLivewire('protect-audio');
    }

    public function test_upload_creates_job_and_download_works_in_demo_mode(): void
    {
        config(['voiceshield.driver' => 'fake', 'voiceshield.fake.duration' => 0]);

        $response = $this->postJson('/jobs', [
            'file' => UploadedFile::fake()->create('voice.wav', 100, 'audio/wav'),
            'text' => 'Спасибо за внимание',
            'lang' => 'ru',
            'models' => 'large-v3',
            'eps' => 0.03,
            'steps' => 2000,
            'lr' => 0.0007,
        ])->assertOk();

        $id = $response->json('id');
        $this->get("/jobs/$id/download")->assertOk()->assertDownload('voice-protected.wav');
    }

    public function test_validation_rejects_missing_language(): void
    {
        $this->postJson('/jobs', ['file' => UploadedFile::fake()->create('voice.wav', 10)])
            ->assertStatus(422)->assertJsonValidationErrors(['lang']);
    }

    public function test_local_worker_launchers_download(): void
    {
        $this->get('/download/windows')->assertOk()->assertDownload('voice-shield-start.bat');
        $this->get('/download/unix')->assertOk()->assertDownload('voice-shield-start.sh');
    }

    public function test_foreign_job_cannot_be_downloaded(): void
    {
        $this->get('/jobs/someone-else/download')->assertNotFound();
    }
}
