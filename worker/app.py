"""
VoiceShield GPU worker.

A small HTTP API around whisper_protect.py. The Laravel site sends audio here,
polls the job status and downloads the protected WAV.

    POST /jobs              multipart: file, text, lang, models, eps, steps, lr  -> {"id": ...}
    GET  /jobs/{id}         -> {"status": queued|running|done|failed, "progress": 0..1, "message", "error"}
    GET  /jobs/{id}/result  -> audio/wav

Auth: send "Authorization: Bearer <WORKER_TOKEN>" if WORKER_TOKEN is set.
Jobs run one at a time (one GPU). Files are deleted RESULT_TTL seconds after finishing.
"""

import os
import queue
import re
import shutil
import subprocess
import sys
import tempfile
import threading
import time
import uuid
from pathlib import Path

from fastapi import Depends, FastAPI, File, Form, Header, HTTPException, UploadFile
from fastapi.responses import FileResponse

HERE = Path(__file__).resolve().parent
SCRIPT = Path(os.environ.get("PROTECT_SCRIPT", HERE / "whisper_protect.py"))
TOKEN = os.environ.get("WORKER_TOKEN", "")
RESULT_TTL = int(os.environ.get("RESULT_TTL", 3600))
WORKDIR = Path(os.environ.get("WORKDIR", tempfile.gettempdir())) / "voice-shield-jobs"
WORKDIR.mkdir(parents=True, exist_ok=True)

# Matches progress lines such as "step 120/2000", "120/2000" or tqdm's "6%|".
STEP_RE = re.compile(r"(\d+)\s*/\s*(\d+)")
PCT_RE = re.compile(r"(\d{1,3})%\|")

app = FastAPI(title="VoiceShield worker")
jobs: dict[str, dict] = {}
pending: "queue.Queue[str]" = queue.Queue()


def auth(authorization: str = Header(default="")):
    if TOKEN and authorization != f"Bearer {TOKEN}":
        raise HTTPException(status_code=401, detail="Unauthorized")


def to_wav(src: Path, dst: Path) -> None:
    """Convert any audio to 16 kHz mono WAV, the format Whisper works with."""
    subprocess.run(
        ["ffmpeg", "-y", "-loglevel", "error", "-i", str(src), "-ac", "1", "-ar", "16000", str(dst)],
        check=True,
    )


def run(job_id: str) -> None:
    job = jobs[job_id]
    folder = WORKDIR / job_id
    job.update(status="running", message="Готовим аудио", progress=0.0)
    try:
        wav = folder / "input.wav"
        to_wav(job["source"], wav)
        out = folder / "protected.wav"
        p = job["params"]
        cmd = [
            sys.executable, "-u", str(SCRIPT),
            "--input", str(wav), "--output", str(out),
            "--text", p["text"], "--lang", p["lang"], "--models", p["models"],
            "--eps", str(p["eps"]), "--steps", str(p["steps"]), "--lr", str(p["lr"]),
        ]
        job["message"] = "Подбираем защиту под Whisper"
        proc = subprocess.Popen(cmd, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True, bufsize=1)
        tail: list[str] = []
        for line in proc.stdout:  # type: ignore[union-attr]
            for chunk in line.replace("\r", "\n").splitlines():
                tail = (tail + [chunk])[-20:]
                if m := STEP_RE.search(chunk):
                    done, total = int(m.group(1)), int(m.group(2))
                    if total == int(p["steps"]) and total > 0:
                        job["progress"] = min(0.99, done / total)
                elif m := PCT_RE.search(chunk):
                    job["progress"] = min(0.99, int(m.group(1)) / 100)
        if proc.wait() != 0 or not out.exists():
            raise RuntimeError("\n".join(tail[-5:]) or "whisper_protect.py failed")
        job.update(status="done", progress=1.0, message="Готово", result=out)
    except Exception as e:  # noqa: BLE001
        job.update(status="failed", error=f"Ошибка обработки: {e}"[:500])
    finally:
        job["finished"] = time.time()


def worker_loop() -> None:
    while True:
        run(pending.get())


def cleanup_loop() -> None:
    while True:
        time.sleep(300)
        now = time.time()
        for job_id, job in list(jobs.items()):
            if job.get("finished") and now - job["finished"] > RESULT_TTL:
                shutil.rmtree(WORKDIR / job_id, ignore_errors=True)
                jobs.pop(job_id, None)


threading.Thread(target=worker_loop, daemon=True).start()
threading.Thread(target=cleanup_loop, daemon=True).start()


@app.get("/")
def health():
    return {"ok": True, "script": SCRIPT.exists(), "queue": pending.qsize()}


@app.post("/jobs", dependencies=[Depends(auth)])
async def create_job(
    file: UploadFile = File(...),
    text: str = Form(...),
    lang: str = Form("ru"),
    models: str = Form("large-v3"),
    eps: float = Form(0.03),
    steps: int = Form(2000),
    lr: float = Form(0.0007),
):
    if not SCRIPT.exists():
        raise HTTPException(status_code=500, detail=f"{SCRIPT.name} not found next to app.py")
    job_id = uuid.uuid4().hex
    folder = WORKDIR / job_id
    folder.mkdir(parents=True)
    source = folder / ("upload" + Path(file.filename or "audio").suffix.lower())
    with source.open("wb") as fh:
        shutil.copyfileobj(file.file, fh)
    jobs[job_id] = {
        "status": "queued", "progress": 0.0, "message": f"В очереди: {pending.qsize()} перед вами",
        "error": None, "source": source,
        "params": {"text": text[:200], "lang": lang, "models": models, "eps": eps, "steps": steps, "lr": lr},
    }
    pending.put(job_id)
    return {"id": job_id}


@app.get("/jobs/{job_id}", dependencies=[Depends(auth)])
def job_status(job_id: str):
    job = jobs.get(job_id)
    if not job:
        raise HTTPException(status_code=404, detail="Job not found")
    return {k: job.get(k) for k in ("status", "progress", "message", "error")}


@app.get("/jobs/{job_id}/result", dependencies=[Depends(auth)])
def job_result(job_id: str):
    job = jobs.get(job_id)
    if not job or job["status"] != "done":
        raise HTTPException(status_code=404, detail="Result not ready")
    return FileResponse(job["result"], media_type="audio/wav", filename="protected.wav")
