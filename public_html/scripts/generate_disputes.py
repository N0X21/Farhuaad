#!/usr/bin/env python3
import argparse
import json
import re
import sys
from datetime import datetime
from pathlib import Path
from urllib import error, request


ROOT = Path(__file__).resolve().parent.parent
ENV_PATH = ROOT / ".env"
CACHE_PATH = ROOT / "data" / "daily_disputes.json"


def load_env(path: Path) -> dict:
    env = {}
    if not path.exists():
        return env
    for raw_line in path.read_text(encoding="utf-8", errors="ignore").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        name, value = line.split("=", 1)
        env[name.strip()] = value.strip().strip("\"'")
    return env


def fallback_disputes() -> list:
    return [
        {"title": "Введут ли страны G7 новые санкции против ИИ-чипов до конца месяца?"},
        {"title": "Снизит ли ФРС ключевую ставку на ближайшем заседании?"},
        {"title": "Подпишут ли в этом месяце международное соглашение о прекращении огня в крупном конфликте?"},
    ]


def read_cache(path: Path) -> dict | None:
    if not path.exists():
        return None
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
        if isinstance(data, dict):
            return data
    except Exception:
        return None
    return None


def write_cache(path: Path, payload: dict) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")


def extract_json_array(text: str):
    text = (text or "").strip()
    try:
        parsed = json.loads(text)
        if isinstance(parsed, list):
            return parsed
    except Exception:
        pass

    m = re.search(r"```json\s*(\[.*\])\s*```", text, re.S)
    if m:
        try:
            parsed = json.loads(m.group(1))
            if isinstance(parsed, list):
                return parsed
        except Exception:
            pass

    m = re.search(r"(\[.*\])", text, re.S)
    if m:
        try:
            parsed = json.loads(m.group(1))
            if isinstance(parsed, list):
                return parsed
        except Exception:
            pass
    return None


def generate_with_claude(api_key: str) -> list:
    if not api_key:
        return fallback_disputes()

    prompt = (
        "Сгенерируй ровно 3 коротких спорных вопроса для рынка прогнозов на основе актуальных мировых новостей. "
        "Ответ верни строго JSON-массивом из 3 объектов формата "
        '[{"title":"..."}], без markdown и без лишнего текста. '
        "Каждый title на русском, не длиннее 140 символов. "
        "Каждый спор должен быть строго бинарным: ответ возможен только Да или Нет."
    )

    body = {
        "model": "claude-3-5-sonnet-20241022",
        "max_tokens": 700,
        "temperature": 0.4,
        "messages": [{"role": "user", "content": prompt}],
    }

    req = request.Request(
        "https://api.anthropic.com/v1/messages",
        data=json.dumps(body, ensure_ascii=False).encode("utf-8"),
        headers={
            "Content-Type": "application/json",
            "x-api-key": api_key,
            "anthropic-version": "2023-06-01",
        },
        method="POST",
    )

    try:
        with request.urlopen(req, timeout=25) as resp:
            raw = resp.read().decode("utf-8", errors="ignore")
            decoded = json.loads(raw)
    except (error.URLError, error.HTTPError, TimeoutError, json.JSONDecodeError):
        return fallback_disputes()

    content = decoded.get("content", [])
    text = ""
    if isinstance(content, list):
        for part in content:
            if isinstance(part, dict) and part.get("type") == "text":
                text += str(part.get("text", ""))

    items = extract_json_array(text)
    if not isinstance(items, list):
        return fallback_disputes()

    result = []
    for item in items:
        if not isinstance(item, dict):
            continue
        title = str(item.get("title", "")).strip()
        if not title:
            continue
        normalized = title[:140]
        # Enforce yes/no format for all generated disputes.
        # If model returns a non-question statement, skip it.
        if "?" not in normalized:
            continue
        # Basic Russian yes/no-question marker.
        if not re.search(r"\bли\b", normalized, flags=re.I):
            continue
        result.append({"title": normalized})
        if len(result) == 3:
            break

    if len(result) < 3:
        return fallback_disputes()
    return result


def get_daily_disputes(force_refresh: bool) -> dict:
    env = load_env(ENV_PATH)
    today = datetime.now().strftime("%Y-%m-%d")
    cache = read_cache(CACHE_PATH)

    if (
        not force_refresh
        and isinstance(cache, dict)
        and cache.get("date") == today
        and isinstance(cache.get("items"), list)
        and len(cache.get("items")) >= 3
    ):
        return cache

    key = (
        env.get("CLAUDE_API_KEY")
        or env.get("ANTHROPIC_API_KEY")
        or env.get("claude_API")
        or ""
    ).strip()
    items = generate_with_claude(key)

    payload = {
        "date": today,
        "generated_at": datetime.now().isoformat(),
        "items": items,
    }
    write_cache(CACHE_PATH, payload)
    return payload


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--refresh", action="store_true", help="Force refresh today data")
    args = parser.parse_args()

    payload = get_daily_disputes(args.refresh)
    sys.stdout.write(json.dumps(payload, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
