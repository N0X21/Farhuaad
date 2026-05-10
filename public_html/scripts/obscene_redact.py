#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Универсальная маскировка нежелательных слов/фраз звёздочками (длина фрагмента сохраняется).

Алгоритм (тот же класс идей, что в PHP-чате):
  1) Нормализация: нижний регистр + замена латинских «двойников» на кириллицу (обход x→х, p→р и т.д.).
  2) Поиск по списку regex на нормализованной строке (индексы совпадают с оригиналом по кодпоинтам).
  3) Слияние пересекающихся интервалов.
  4) Замена символов в ИСХОДНОЙ строке на '*' (регистр и смешанная латиница сохраняются снаружи маски).

Запуск:
  python obscene_redact.py "какие наxyй фильтpы"
  echo "текст" | python obscene_redact.py
"""

from __future__ import annotations

import io
import json
import re
import sys
from pathlib import Path
from typing import Iterable, List, Sequence, Tuple

# Латиница, визуально похожая на кириллицу (1 кодпоинт → 1 кодпоинт).
HOMOGLYPH_FOLD: dict[str, str] = {
    "a": "а",
    "c": "с",
    "e": "е",
    "f": "ф",
    "h": "н",
    "k": "к",
    "m": "м",
    "n": "н",
    "o": "о",
    "p": "р",
    "t": "т",
    "x": "х",
    "y": "у",
}


def fold_for_match(text: str) -> str:
    """Строка той же длины (в кодпоинтах), что и text, для устойчивого поиска."""
    return "".join(HOMOGLYPH_FOLD.get(ch, ch) for ch in text.lower())


def default_patterns() -> List[str]:
    """Базовый набор regex (рус + англ.); дополняйте свой или грузите JSON — см. load_patterns_file()."""
    return [
        r"наху[йеяию]",
        r"на\s+хуй",
        r"на\s+хуя",
        r"поху[йеяию]",
        r"по\s+хуй",
        r"ниху[йеяию]",
        r"хуй(сос|соси|ло|ня|ище|егол|епл[её]т|нут|ство)|хуесос|хуепл[её]т",
        r"(?<![\w])хуй(?=[\w])",  # \w с флагом UNICODE — буквы
        r"хуяч",
        r"(?<![\w])(пизд|пёзд)(?=[\w])",
        r"(?<![\w])(еб|ёб)(?!ырь)(?=[\w])",
        r"пид(ор|арас|ор|оры)|пёдор|пидарас",
        r"долбо[её]б|долба[её]б|долбоеб|долбаеб",
        r"еблан|ёблан|мудак|мудач|гандон|бляд|блять|сука",
        r"дроч",
        r"дрюч",
        r"пенис",
        r"фаллос",
        r"мастурб",
        r"сперм",
        r"эякул",
        r"минет",
        r"(?<![\w])(хуй|хуя|хуе|хуи|хую|пизд|ебан|ёбан|еб[её]т|ебать|ебал|бляд|блять|сука|мудак|гандон|срать|дерьмо)(?![\w])",
        r"\b(fuck|shit|cunt|dick|cock|pussy|whore|fucker|motherfucker|penis|jizz|cumshot)\b",
        r"\bjerk[\s-]*off\b",
        r"masturbat",
        r"(?<![\w])секс(?![\w])",
        r"\bsex\b",
        r"s[\W_]*e[\W_]*x\b",
    ]


def load_patterns_file(path: Path) -> List[str]:
    """JSON: { "patterns": [ "regex1", ... ] } или простой список строк."""
    raw = path.read_text(encoding="utf-8")
    data = json.loads(raw)
    if isinstance(data, list):
        return [str(x) for x in data]
    if isinstance(data, dict) and "patterns" in data:
        return [str(x) for x in data["patterns"]]
    raise ValueError("Ожидался JSON-массив или объект с ключом 'patterns'")


def merge_spans(spans: Sequence[Tuple[int, int]]) -> List[Tuple[int, int]]:
    if not spans:
        return []
    srt = sorted(spans, key=lambda x: x[0])
    out: List[Tuple[int, int]] = []
    cs, ce = srt[0]
    for a, b in srt[1:]:
        if a <= ce:
            ce = max(ce, b)
        else:
            out.append((cs, ce))
            cs, ce = a, b
    out.append((cs, ce))
    return out


def redact_obscene(
    text: str,
    patterns: Iterable[str] | None = None,
    flags: int = re.IGNORECASE | re.UNICODE,
) -> str:
    """
    Маскирует совпадения звёздочками в оригинальном тексте.
    Пустой текст / несовпадение длин после fold — возврат без изменений.
    """
    if not text:
        return text
    pats = list(patterns) if patterns is not None else default_patterns()
    folded = fold_for_match(text)
    if len(folded) != len(text):
        return text

    spans: List[Tuple[int, int]] = []
    for raw in pats:
        try:
            rx = re.compile(raw, flags)
        except re.error:
            continue
        for m in rx.finditer(folded):
            spans.append(m.span())

    if not spans:
        return text

    merged = merge_spans(spans)
    chars = list(text)
    for a, b in merged:
        for i in range(a, min(b, len(chars))):
            chars[i] = "*"
    return "".join(chars)


def _ensure_utf8_stdout() -> None:
    """Чтобы PHP и Windows-консоль получали предсказуемый UTF-8 без PYTHONIOENCODING."""
    buf = getattr(sys.stdout, "buffer", None)
    if buf is None:
        return

    sys.stdout = io.TextIOWrapper(
        buf, encoding="utf-8", errors="replace", newline="\n", line_buffering=True
    )


def main(argv: List[str]) -> int:
    _ensure_utf8_stdout()
    patterns = default_patterns()
    args = argv[1:]
    if args and args[0] == "--patterns":
        if len(args) < 2:
            print("Использование: obscene_redact.py --patterns path.json [текст]", file=sys.stderr)
            return 2
        patterns = load_patterns_file(Path(args[1]))
        args = args[2:]

    if args:
        line = " ".join(args)
    else:
        line = sys.stdin.read()

    out = redact_obscene(line.rstrip("\n"))
    sys.stdout.write(out)
    return 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv))
