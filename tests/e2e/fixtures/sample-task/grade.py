"""Grader of the ExAutoScore test task: checks the functions in aufgabe.py"""

import html
import importlib.util
import json
import os
import shutil
import tarfile

CHECKS = [
    (2.0, "summe([1, 2, 3]) == 6", lambda m: m.summe([1, 2, 3]) == 6),
    (1.0, "summe([]) == 0", lambda m: m.summe([]) == 0),
    (2.0, "ist_primzahl(7) is True", lambda m: m.ist_primzahl(7) is True),
    (1.0, "ist_primzahl(1) is False", lambda m: m.ist_primzahl(1) is False),
    (2.0, "fakultaet(5) == 120", lambda m: m.fakultaet(5) == 120),
    (2.0, "fakultaet(0) == 1", lambda m: m.fakultaet(0) == 1),
]


def load_submission(work):
    required = os.getenv("REQUIREDDIR", "/user")
    tgz = os.path.join(required, "required.tgz")
    if os.path.isfile(tgz):
        with tarfile.open(tgz, "r:gz") as tar:
            tar.extractall(work, filter="data")
    else:
        shutil.copytree(required, work, dirs_exist_ok=True)
    spec = importlib.util.spec_from_file_location("aufgabe", os.path.join(work, "aufgabe.py"))
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def grade():
    try:
        module = load_submission(os.path.join(os.getenv("WORKDIR", "/work"), "student"))
    except Exception as e:
        message = f"aufgabe.py kann nicht geladen werden: {type(e).__name__}: {e}"
        return {"success": True, "instant_status": "failed", "instant_message": message, "points": 0,
                "protected_status": "failed", "protected_feedback_text": message,
                "protected_feedback_html": html.escape(message)}

    lines, points, possible, passed = [], 0.0, 0.0, 0
    for value, label, check in CHECKS:
        possible += value
        try:
            ok = bool(check(module))
        except Exception:
            ok = False
        points += value if ok else 0
        passed += ok
        lines.append(f"{'OK ' if ok else 'FEHLER'} [{value if ok else 0:.1f}/{value:.1f}] {label}")

    status = "passed" if passed == len(CHECKS) else ("failed" if passed == 0 else "partial")
    text = f"Punkte: {points:.1f} / {possible:.1f} ({passed}/{len(CHECKS)} Tests bestanden)\n" + "\n".join(lines)
    return {"success": True, "instant_status": status, "instant_message": text, "points": round(points, 2),
            "protected_status": status, "protected_feedback_text": text,
            "protected_feedback_html": "<br />".join(html.escape(l) for l in text.splitlines())}


if __name__ == "__main__":
    with open(os.path.join(os.getenv("RESULTDIR", "/result"), "results.json"), "w", encoding="utf-8") as f:
        json.dump(grade(), f, ensure_ascii=False, indent=2)
