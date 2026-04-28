# UTF-8 And Persian Text Rules

## File Identity
- What: Persian-first text integrity contract.
- Where: Repo root.
- Role: Prevents mojibake, bidi regressions, and locale inconsistency.
- Controls: encoding, numeral/date/time locale behavior, bidi safety, integrity validation.
- Dependencies:
  - `scripts/check_text_integrity.py`
  - `public_html/assets/site/styles/`
- Read when:
  - editing user-facing text
  - changing RTL/CSS text behavior
  - touching locale formatting logic

## Required Rules
1. Save all text files as UTF-8.
2. Never replace Persian text with placeholders like `????`.
3. Keep user-facing numbers Persian by default.
4. Keep user-facing date/time Persian by default (`fa-IR`) unless product requirements explicitly say otherwise.
5. If a field must stay Latin digits (technical IDs, machine-only values), mark container with:
   - `data-digit-locale="latin"` or `data-latin-digits="true"`
6. Do not use `unicode-bidi: plaintext` in UI styles unless there is a documented hard requirement.
7. Preferred safe default for Persian UI text blocks:
   - `direction: rtl`
   - `unicode-bidi: isolate`
8. After text or UI-copy edits, run:

```bash
python scripts/check_text_integrity.py
```

## Validation Expectations
- The integrity script fails on suspicious corruption patterns (`????`, mojibake, invalid UTF-8) in `public_html`.
- It also fails on known Persian break patterns (for example `Ú¯ÙØªÚ¯`, `Ø§Ø±Ø³Ø§`, `Ø¯Ø± Ø­Ø§`).
- It also fails on unsafe bidi usage such as `unicode-bidi: plaintext` unless explicitly allow-marked.
