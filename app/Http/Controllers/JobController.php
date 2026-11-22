<?php

namespace App\Http\Controllers;

use App\Services\Protection\ProtectionBackend;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class JobController extends Controller
{
    /**
     * Receives the audio and forwards it to the processing backend in the
     * same request, so nothing has to be stored on the (stateless) web server.
     */
    public function store(Request $request, ProtectionBackend $backend): JsonResponse
    {
        $config = config('voiceshield');

        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.$config['max_upload_kb'], 'mimes:wav,mp3,m4a,ogg,oga,opus,flac,webm,aac,mp4'],
            'text' => ['required', 'string', 'max:200'],
            'lang' => ['required', Rule::in(array_keys($config['languages']))],
            'models' => ['required', Rule::in($config['models'])],
            'eps' => ['required', 'numeric', 'between:0.001,0.2'],
            'steps' => ['required', 'integer', 'between:100,10000'],
            'lr' => ['required', 'numeric', 'between:0.00001,0.01'],
        ], [], [
            'file' => 'аудиофайл',
            'text' => 'фраза-приманка',
        ]);

        try {
            $id = $backend->submit($request->file('file'), [
                'text' => $data['text'],
                'lang' => $data['lang'],
                'models' => $data['models'],
                'eps' => (float) $data['eps'],
                'steps' => (int) $data['steps'],
                'lr' => (float) $data['lr'],
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Сервер обработки сейчас недоступен. Попробуйте позже.'], 503);
        }

        $name = pathinfo($request->file('file')->getClientOriginalName(), PATHINFO_FILENAME);
        $jobs = $request->session()->get('jobs', []);
        $jobs[$id] = Str::slug($name ?: 'audio').'-protected.wav';
        $request->session()->put('jobs', array_slice($jobs, -10, null, true));

        return response()->json(['id' => $id]);
    }

    public function download(Request $request, string $id, ProtectionBackend $backend): Response
    {
        $filename = $request->session()->get("jobs.$id");
        abort_unless($filename, 404);

        return $backend->download($id, $filename);
    }
}
