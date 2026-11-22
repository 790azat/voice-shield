@echo off
REM VoiceShield: run protection on YOUR computer's GPU (Windows, NVIDIA).
REM Needs Python 3.10+ (python.org) and ffmpeg (winget install ffmpeg).
setlocal
set DIR=%USERPROFILE%\voice-shield-worker
if not exist "%DIR%\app.py" (
  echo Downloading VoiceShield to %DIR%
  mkdir "%DIR%" 2>nul
  for %%f in (app.py whisper_protect.py requirements.txt) do (
    curl -fsSL https://raw.githubusercontent.com/790azat/voice-shield/main/worker/%%f -o "%DIR%\%%f" || goto :error
  )
)
cd /d "%DIR%"
where ffmpeg >nul 2>nul || (echo Install ffmpeg first: winget install ffmpeg & pause & exit /b 1)
if not exist .venv (python -m venv .venv || goto :error)
call .venv\Scripts\activate.bat
if not exist .venv\.installed (
  echo Installing dependencies, one time, several minutes...
  python -m pip install -q --upgrade pip
  REM CUDA build of PyTorch for NVIDIA cards
  pip install -q torch torchaudio --index-url https://download.pytorch.org/whl/cu124 || goto :error
  pip install -q -r requirements.txt || goto :error
  type nul > .venv\.installed
)
echo.
echo   VoiceShield is running on http://127.0.0.1:7860
echo   Open https://voice-shield-amber.vercel.app and choose "On my computer".
echo   Stop: close this window.
echo.
uvicorn app:app --host 127.0.0.1 --port 7860
goto :eof
:error
echo Something went wrong. See the messages above.
pause
