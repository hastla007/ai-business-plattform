import concurrent.futures
import os
import time
import requests

PLATFORM = os.environ["PLATFORM_URL"].rstrip("/")
TOKEN = os.environ["NODE_TOKEN"]
if not PLATFORM.startswith("https://") and not PLATFORM.startswith("http://localhost") and not PLATFORM.startswith("http://127.0.0.1"):
    raise ValueError("Use HTTPS for the platform URL outside local development.")
MODEL_URL = os.environ["MODEL_BASE_URL"].rstrip("/")
MAX_PARALLEL = max(1, min(int(os.environ.get("MAX_PARALLEL", "1")), 64))
ALLOWED_PATHS = {"/v1/chat/completions", "/v1/embeddings"}
session = requests.Session()
session.headers.update({"X-Node-Token": TOKEN})


def heartbeat():
    session.post(f"{PLATFORM}/api/v1/nodes/heartbeat", json={
        "version": os.environ.get("NODE_VERSION", "0.1.0"),
        "capabilities": {"models": [m.strip() for m in os.environ.get("NODE_MODELS", "").split(",") if m.strip()]},
        "hardware_profile": {"label": os.environ.get("HARDWARE_LABEL", "unspecified")},
    }, timeout=10).raise_for_status()


def execute(command):
    command_id = command["id"]
    try:
        payload = command["payload"]
        path = payload.get("path")
        method = payload.get("method")
        if path not in ALLOWED_PATHS or method != 'POST':
            raise ValueError("Unsupported model operation")
        response = requests.post(MODEL_URL + path, json=payload["body"], timeout=55)
        response.raise_for_status()
        result = response.json()
        failed = False
    except Exception as exc:
        result = {"error": type(exc).__name__}
        failed = True
    session.post(f"{PLATFORM}/api/v1/nodes/tasks/{command_id}/result",
        json={"result": result, "failed": failed}, timeout=10).raise_for_status()


def main():
    pending = set()
    with concurrent.futures.ThreadPoolExecutor(max_workers=MAX_PARALLEL) as pool:
        while True:
            try:
                pending = {future for future in pending if not future.done()}
                heartbeat()
                if len(pending) < MAX_PARALLEL:
                    response = session.get(f"{PLATFORM}/api/v1/nodes/tasks", timeout=10)
                    response.raise_for_status()
                    for command in response.json().get("commands", []):
                        pending.add(pool.submit(execute, command))
                time.sleep(3)
            except Exception:
                time.sleep(5)


if __name__ == "__main__":
    main()
