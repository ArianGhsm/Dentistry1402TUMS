from __future__ import annotations

import argparse
import importlib.util
import json
import re
import sys
from collections import defaultdict
from pathlib import Path
from typing import Iterable


def load_builder_module(repo_root: Path):
    builder_path = repo_root / "scripts" / "build_term6_reference_courses.py"
    spec = importlib.util.spec_from_file_location("build_term6_reference_courses", builder_path)
    if spec is None or spec.loader is None:
        raise RuntimeError(f"Could not load builder module from {builder_path}")
    module = importlib.util.module_from_spec(spec)
    sys.modules[spec.name] = module
    spec.loader.exec_module(module)
    return module


def normalize_text(value: str) -> str:
    collapsed = re.sub(r"\s+", " ", value or "")
    return collapsed.strip()


def question_indexes(bucket: Iterable[tuple[int, str]]) -> str:
    return ",".join(str(index) for index, _ in bucket)


def preview_text(value: str, limit: int = 160) -> str:
    text = normalize_text(value)
    if len(text) <= limit:
        return text
    return text[: limit - 1].rstrip() + "…"


def audit_course_map(
    course_map: dict[str, dict],
    *,
    course_prefix: str = "",
    min_option_duplicates: int = 3,
    min_question_duplicates: int = 2,
    min_explanation_duplicates: int = 2,
) -> list[str]:
    findings: list[str] = []

    for course_slug, course in sorted(course_map.items()):
        if course_prefix and not course_slug.startswith(course_prefix):
            continue
        exams = course.get("exams")
        if not isinstance(exams, list):
            continue

        for exam in exams:
            questions = exam.get("questions")
            if not isinstance(questions, list) or not questions:
                continue

            exam_slug = str(exam.get("slug") or "")
            option_buckets: dict[str, list[tuple[int, str]]] = defaultdict(list)
            question_buckets: dict[str, list[tuple[int, str]]] = defaultdict(list)
            explanation_buckets: dict[str, list[tuple[int, str]]] = defaultdict(list)

            for index, question in enumerate(questions, start=1):
                text = normalize_text(str(question.get("question") or ""))
                options = question.get("options")
                explanation = normalize_text(str(question.get("explanation") or ""))
                if isinstance(options, list) and options:
                    option_key = json.dumps([normalize_text(str(item)) for item in options], ensure_ascii=False)
                    option_buckets[option_key].append((index, text))
                if text:
                    question_buckets[text].append((index, text))
                if explanation:
                    explanation_buckets[explanation].append((index, text))

            for option_key, bucket in sorted(option_buckets.items(), key=lambda item: (-len(item[1]), item[1][0][0])):
                if len(bucket) < min_option_duplicates:
                    continue
                findings.append(
                    f"[option-set x{len(bucket)}] {course_slug}/{exam_slug} q{question_indexes(bucket)} | {preview_text(option_key)}"
                )

            for question_text, bucket in sorted(question_buckets.items(), key=lambda item: (-len(item[1]), item[1][0][0])):
                if len(bucket) < min_question_duplicates:
                    continue
                findings.append(
                    f"[question x{len(bucket)}] {course_slug}/{exam_slug} q{question_indexes(bucket)} | {preview_text(question_text)}"
                )

            for explanation_text, bucket in sorted(explanation_buckets.items(), key=lambda item: (-len(item[1]), item[1][0][0])):
                if len(bucket) < min_explanation_duplicates:
                    continue
                findings.append(
                    f"[explanation x{len(bucket)}] {course_slug}/{exam_slug} q{question_indexes(bucket)} | {preview_text(explanation_text)}"
                )

    return findings


def main() -> None:
    parser = argparse.ArgumentParser(description="Audit term-6 reference exam data for suspicious duplicate patterns.")
    parser.add_argument(
        "--repo-root",
        type=Path,
        default=Path(__file__).resolve().parents[1],
        help="Repository root.",
    )
    parser.add_argument(
        "--course-prefix",
        default="",
        help="Only audit courses whose slug starts with this prefix.",
    )
    parser.add_argument(
        "--min-option-duplicates",
        type=int,
        default=3,
        help="Minimum identical option-set repeats inside one exam to report.",
    )
    parser.add_argument(
        "--min-question-duplicates",
        type=int,
        default=2,
        help="Minimum identical question-text repeats inside one exam to report.",
    )
    parser.add_argument(
        "--min-explanation-duplicates",
        type=int,
        default=2,
        help="Minimum identical explanation repeats inside one exam to report.",
    )
    parser.add_argument(
        "--fail-on-findings",
        action="store_true",
        help="Exit with code 1 when any finding is detected.",
    )
    args = parser.parse_args()

    repo_root = args.repo_root.resolve()
    builder = load_builder_module(repo_root)
    course_map = builder.load_existing_course_map(repo_root)
    findings = audit_course_map(
        course_map,
        course_prefix=args.course_prefix,
        min_option_duplicates=args.min_option_duplicates,
        min_question_duplicates=args.min_question_duplicates,
        min_explanation_duplicates=args.min_explanation_duplicates,
    )

    if not findings:
        print("No suspicious duplicate patterns found.")
        return

    for finding in findings:
        print(finding)

    if args.fail_on_findings:
        raise SystemExit(1)


if __name__ == "__main__":
    main()
