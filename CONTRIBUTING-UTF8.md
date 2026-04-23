# UTF-8 And Persian Text Rules

This project is Persian-first. Keep all user-facing text UTF-8 safe end-to-end.

## Required rules

1. Save all text files as UTF-8.
2. Never replace Persian text with placeholders like `????`.
3. Keep user-facing numbers Persian by default.
4. If a field must stay Latin digits (technical IDs, machine-only values), mark its container with:
   - `data-digit-locale="latin"` or `data-latin-digits="true"`
5. Do not use `unicode-bidi: plaintext` in UI styles unless there is a documented hard requirement.
   - Preferred safe default for Persian UI text blocks: `direction: rtl` + `unicode-bidi: isolate`.
6. After text or UI-copy edits, run:

```bash
python scripts/check_text_integrity.py
```

The command fails on suspicious corruption patterns (`????`, mojibake, invalid UTF-8) in `public_html`.

It also fails on known Persian text-break patterns (for example broken tokens like `Ú¯ÙØªÚ¯`, `Ø§Ø±Ø³Ø§`, `Ø¯Ø± Ø­Ø§`) so malformed UI copy cannot pass CI.
It also fails on unsafe bidi usage like `unicode-bidi: plaintext` (unless explicitly allow-marked).
