#!/usr/bin/env python3
"""
VoiceShield: add a near-inaudible adversarial perturbation to a recording so
that speech-recognition models stop transcribing it correctly.

Two modes:
  * target    (--text "phrase")  Whisper transcribes the chosen phrase instead of the speech.
  * untargeted (--text "")       Recognition is broken: Whisper outputs nothing and the
                                 original words become unlikely for every model in the ensemble.
                                 Transfers better to other (closed) ASR systems, but nothing
                                 guarantees it against them.

The perturbation is optimised against an ensemble of open models at once:
  --models        Whisper sizes, e.g. "large-v3,medium,small"
  --extra-models  Hugging Face CTC models (wav2vec2 / MMS / HuBERT), e.g.
                  "jonatasgrosman/wav2vec2-large-xlsr-53-russian"
Ensembles and random transformations during optimisation (--robust) make the
protection survive re-encoding and transfer to models that were not attacked.

Example:
  python whisper_protect.py --input voice.wav --output protected.wav \
      --text "" --lang ru --models large-v3,medium --eps 0.03 --steps 2000 --lr 0.0007
"""

import argparse
import math
import sys
import time

import numpy as np
import soundfile as sf
import torch
import torch.nn.functional as F
import whisper
from whisper.audio import N_SAMPLES, SAMPLE_RATE, log_mel_spectrogram
from whisper.tokenizer import get_tokenizer


def parse_args():
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("--input", required=True, help="input audio (16 kHz mono WAV is best; other formats are resampled)")
    p.add_argument("--output", required=True, help="output WAV")
    p.add_argument("--text", default="", help="target phrase; empty = untargeted mode (break recognition)")
    p.add_argument("--lang", default="ru", help="speech language code (ru, en, ...)")
    p.add_argument("--models", default="large-v3", help="comma-separated Whisper models")
    p.add_argument("--extra-models", default="", help="comma-separated Hugging Face CTC model ids")
    p.add_argument("--eps", type=float, default=0.03, help="max perturbation amplitude (L-inf, audio in [-1, 1])")
    p.add_argument("--steps", type=int, default=2000)
    p.add_argument("--lr", type=float, default=0.0007)
    p.add_argument("--robust", type=int, default=1, help="1 = random gain/noise/shift during optimisation")
    p.add_argument("--check-every", type=int, default=100, help="check transcripts every N steps (0 = never)")
    p.add_argument("--device", default="auto", help="auto, cuda, mps or cpu")
    return p.parse_args()


def pick_device(name: str) -> str:
    if name != "auto":
        return name
    if torch.cuda.is_available():
        return "cuda"
    if getattr(torch.backends, "mps", None) and torch.backends.mps.is_available():
        return "mps"
    return "cpu"


def load_audio(path: str) -> np.ndarray:
    try:
        audio, sr = sf.read(path, dtype="float32", always_2d=True)
        audio = audio.mean(axis=1)
        if sr != SAMPLE_RATE:
            raise ValueError("resample")
        return audio
    except Exception:  # noqa: BLE001 - fall back to ffmpeg for any format / sample rate
        return whisper.load_audio(path)


def log(msg: str) -> None:
    print(msg, flush=True)


# --------------------------------------------------------------------------- Whisper


class WhisperTarget:
    def __init__(self, name, device, lang, target_text):
        self.name = name
        self.model = whisper.load_model(name, device=device)
        self.model.eval()
        for prm in self.model.parameters():
            prm.requires_grad_(False)
        self.device = device
        self.n_mels = self.model.dims.n_mels
        self.lang = lang
        self.tok = get_tokenizer(self.model.is_multilingual, num_languages=self.model.num_languages, language=lang, task="transcribe")
        self.prefix = list(self.tok.sot_sequence_including_notimestamps)
        self.target = self.tok.encode(" " + target_text.strip()) if target_text.strip() else []
        self.original = {}  # chunk index -> token ids of the clean transcription

    def mel(self, chunk):
        return log_mel_spectrogram(whisper.pad_or_trim(chunk), n_mels=self.n_mels)

    def _decode(self, chunk):
        with torch.no_grad():
            opts = whisper.DecodingOptions(language=self.lang, without_timestamps=True, fp16=self.device == "cuda")
            return whisper.decode(self.model, self.mel(chunk), opts)

    def transcribe(self, chunk) -> str:
        return self._decode(chunk).text.strip()

    def remember_original(self, idx, chunk):
        result = self._decode(chunk)
        # keep only ordinary text tokens of what the model hears now
        self.original[idx] = [t for t in result.tokens if t < self.tok.eot][:200]
        return result.text.strip()

    def _ce(self, mel, tokens):
        seq = torch.tensor([self.prefix + tokens + [self.tok.eot]], device=self.device)
        logits = self.model(mel.unsqueeze(0), seq[:, :-1])
        n = len(tokens) + 1
        return F.cross_entropy(logits[0, -n:].float(), seq[0, -n:])

    def loss(self, idx, chunk, targeted: bool):
        mel = self.mel(chunk)
        if targeted:
            # target phrase only in the first chunk; following chunks should transcribe as nothing
            return self._ce(mel, self.target if idx == 0 else [])
        loss = self._ce(mel, [])  # pull towards "no speech"
        orig = self.original.get(idx) or []
        if orig:
            # push away from the real words; clamp so this term cannot explode
            loss = loss - 0.5 * torch.clamp(self._ce(mel, orig), max=10.0)
        return loss


# --------------------------------------------------------------------------- CTC (wav2vec2 & co)


class CTCTarget:
    def __init__(self, name, device):
        from transformers import AutoModelForCTC, AutoProcessor

        self.name = name
        self.processor = AutoProcessor.from_pretrained(name)
        self.model = AutoModelForCTC.from_pretrained(name).to(device).eval()
        for prm in self.model.parameters():
            prm.requires_grad_(False)
        self.device = device
        self.original = {}

    def _logits(self, chunk):
        x = (chunk - chunk.mean()) / torch.sqrt(chunk.var() + 1e-7)  # same normalisation as the feature extractor
        return self.model(x.unsqueeze(0)).logits

    def transcribe(self, chunk) -> str:
        with torch.no_grad():
            ids = self._logits(chunk).argmax(-1)
        return self.processor.batch_decode(ids)[0].strip()

    def remember_original(self, idx, chunk):
        with torch.no_grad():
            ids = self._logits(chunk).argmax(-1)[0]
        # collapse repeats and drop blanks -> label sequence the model currently "hears"
        blank = self.model.config.pad_token_id
        labels, prev = [], None
        for i in ids.tolist():
            if i != prev and i != blank:
                labels.append(i)
            prev = i
        self.original[idx] = labels
        return self.processor.batch_decode(ids.unsqueeze(0))[0].strip()

    def loss(self, idx, chunk, targeted: bool):
        labels = self.original.get(idx) or []
        if not labels:
            return torch.zeros((), device=self.device)
        logp = self._logits(chunk).log_softmax(-1).transpose(0, 1).float()
        ctc = F.ctc_loss(
            logp,
            torch.tensor([labels], device=self.device),
            torch.tensor([logp.shape[0]]),
            torch.tensor([len(labels)]),
            blank=self.model.config.pad_token_id,
            zero_infinity=True,
        )
        return -0.2 * torch.clamp(ctc / max(1, len(labels)), max=10.0)


# --------------------------------------------------------------------------- optimisation


def transform(x, robust: bool):
    """Expectation over transformation: random gain, noise and small time shift."""
    if not robust:
        return x
    gain = torch.empty((), device=x.device).uniform_(0.8, 1.2)
    x = x * gain + torch.randn_like(x) * 0.002
    shift = int(torch.randint(-160, 161, ()).item())
    return torch.roll(x, shift)


def protect_chunk(idx, clean, models, args, step_offset, total_steps, targeted, device):
    delta = torch.zeros_like(clean, requires_grad=True)
    opt = torch.optim.Adam([delta], lr=args.lr)
    use_amp = device == "cuda"
    best, best_loss = delta.detach().clone(), math.inf

    for step in range(1, args.steps + 1):
        x = torch.clamp(clean + delta, -1.0, 1.0)
        xt = transform(x, bool(args.robust))
        with torch.autocast("cuda", dtype=torch.float16, enabled=use_amp):
            loss = sum(m.loss(idx, xt, targeted) for m in models) / len(models)
        opt.zero_grad(set_to_none=True)
        loss.backward()
        opt.step()
        with torch.no_grad():
            delta.clamp_(-args.eps, args.eps)
        value = loss.item()
        if value < best_loss:
            best_loss, best = value, delta.detach().clone()

        if step % 20 == 0 or step == args.steps:
            log(f"step {step_offset + step}/{total_steps} loss={value:.4f}")

        if args.check_every and step % args.check_every == 0 and done(idx, clean + delta.detach(), models, targeted):
            log(f"chunk {idx}: goal reached at step {step}")
            best = delta.detach().clone()
            break

    return torch.clamp(clean + best, -1.0, 1.0)


def done(idx, x, models, targeted) -> bool:
    for m in models:
        if not isinstance(m, WhisperTarget):
            continue
        got = m.transcribe(x)
        want = m.tok.decode(m.target).strip() if targeted and idx == 0 else ""
        if normalise(got) != normalise(want):
            return False
    return True


def normalise(s: str) -> str:
    return "".join(ch for ch in s.lower() if ch.isalnum())


def main():
    args = parse_args()
    device = pick_device(args.device)
    targeted = bool(args.text.strip())
    log(f"device={device} mode={'target' if targeted else 'untargeted'}")
    torch.manual_seed(0)

    audio = torch.from_numpy(load_audio(args.input)).to(device)
    chunks = [audio[i : i + N_SAMPLES] for i in range(0, len(audio), N_SAMPLES)] or [audio]

    models = []
    for name in filter(None, (s.strip() for s in args.models.split(","))):
        log(f"loading whisper {name}")
        models.append(WhisperTarget(name, device, args.lang, args.text))
    for name in filter(None, (s.strip() for s in args.extra_models.split(","))):
        log(f"loading ctc {name}")
        models.append(CTCTarget(name, device))
    if not models:
        sys.exit("no models given")

    for idx, chunk in enumerate(chunks):
        for m in models:
            log(f"[before] chunk {idx} {m.name}: {m.remember_original(idx, chunk)[:200]}")

    total = args.steps * len(chunks)
    out = []
    t0 = time.time()
    for idx, chunk in enumerate(chunks):
        out.append(protect_chunk(idx, chunk, models, args, idx * args.steps, total, targeted, device))
    log(f"step {total}/{total} done in {time.time() - t0:.0f}s")

    protected = torch.cat(out)
    for idx, chunk in enumerate(protected.split(N_SAMPLES)):
        for m in models:
            log(f"[after]  chunk {idx} {m.name}: {m.transcribe(chunk)[:200]}")

    sf.write(args.output, protected.detach().cpu().numpy(), SAMPLE_RATE, subtype="PCM_16")
    log(f"saved {args.output}")


if __name__ == "__main__":
    main()
