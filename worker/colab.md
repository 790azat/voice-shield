# Запуск воркера в Google Colab

Подходит для тестов: бесплатный GPU, но сессия живёт несколько часов, а адрес туннеля меняется при каждом запуске.

1. Среда выполнения → Сменить среду → **GPU (T4 или лучше)**.
2. Ячейка 1 — код и зависимости:

```python
!git clone https://github.com/790azat/voice-shield.git
%cd voice-shield/worker
# положите whisper_protect.py в эту папку (загрузите через панель файлов или скопируйте из Drive)
!pip install -q -r requirements.txt
!apt-get -qq install -y ffmpeg
```

3. Ячейка 2 — запуск API и публичного туннеля Cloudflare:

```python
import os, subprocess, re, time
os.environ["WORKER_TOKEN"] = "придумайте-длинный-секрет"
subprocess.Popen(["uvicorn", "app:app", "--host", "0.0.0.0", "--port", "7860"])
!wget -q https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64 -O cloudflared && chmod +x cloudflared
tunnel = subprocess.Popen(["./cloudflared", "tunnel", "--url", "http://localhost:7860"], stderr=subprocess.PIPE, text=True)
for line in tunnel.stderr:
    if m := re.search(r"https://[a-z0-9-]+\.trycloudflare\.com", line):
        print("PROTECTION_WORKER_URL =", m.group(0)); break
```

4. В Vercel → Settings → Environment Variables задайте
   `PROTECTION_DRIVER=http`, `PROTECTION_WORKER_URL=<адрес из ячейки 2>`, `PROTECTION_WORKER_TOKEN=<тот же секрет>`
   и сделайте Redeploy.
