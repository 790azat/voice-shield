#!/usr/bin/env bash
# VoiceShield: run protection on YOUR computer's GPU (Linux / macOS).
# Usage:  curl -fsSL https://raw.githubusercontent.com/790azat/voice-shield/main/worker/start.sh | bash
set -euo pipefail

DIR="${VOICESHIELD_DIR:-$HOME/voice-shield-worker}"
if [ ! -f "$DIR/app.py" ]; then
  echo "Скачиваем VoiceShield в $DIR"
  mkdir -p "$DIR"
  for f in app.py whisper_protect.py requirements.txt; do
    curl -fsSL "https://raw.githubusercontent.com/790azat/voice-shield/main/worker/$f" -o "$DIR/$f"
  done
fi
cd "$DIR"

command -v ffmpeg >/dev/null || { echo "Нужен ffmpeg: sudo apt install ffmpeg  /  brew install ffmpeg"; exit 1; }
PY=$(command -v python3 || command -v python)
[ -d .venv ] || "$PY" -m venv .venv
. .venv/bin/activate
if [ ! -f .venv/.installed ]; then
  echo "Устанавливаем зависимости (один раз, несколько минут)"
  pip install -q --upgrade pip
  pip install -q -r requirements.txt
  touch .venv/.installed
fi

echo
echo "  VoiceShield работает на http://127.0.0.1:7860"
echo "  Откройте https://voice-shield-amber.vercel.app и выберите «На моём компьютере»."
echo "  Остановить: Ctrl+C"
echo
exec uvicorn app:app --host 127.0.0.1 --port 7860
