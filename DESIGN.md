# DESIGN.md — «کلینیک» (Clinic)

Owner-approved visual system for dentistry1402tums.ir, adopted 2026-11-12. It replaces «سرمه‌ای و برنج» (Navy & Brass). Product truth lives in `PRODUCT.md`, and the binding implementation rules live in `AGENTS.md` (§10). This file records *how the site looks and why*.

## Thesis

The site is a bot-first shortcut: students arrive from Telegram/Bale links to **buy and take exams** (midterms and sample-question sets first). Every screen gets them to the question or the payment in one tap. The exam is a clinical instrument you pick up, not a form you fill.

## World

| Material | Source in the students' world | Token(s) |
|---|---|---|
| Shell (rail, mobile header, session band, continue card) | teal of the dental unit | `--brand-navy-950/900/800/600` (names kept for compatibility) |
| Ground | pale pouch paper of a sterile pack | `--bg-body`, `--surface*` |
| Signature | autoclave indicator tape: raw cells hatched rose, processed cells dark | `--tape-raw(-line)`, `--tape-done(-line)`, `--tape-flag` |
| Marks on the shell | the indicator rose | `--shell-mark` |
| Ink on the shell | | `--on-shell`, `--on-shell-strong`, `--on-shell-muted`, `--shell-tape-*` |
| Accent / selection | teal (light) and mint (dark) | `--accent-color`, `--accent-soft`, `--brand-brass*` |

Every value is defined for light and dark in `public_html/assets/site/styles/brand-2026.css`, the last layer of `theme.css`. Page layers use tokens only; no hard-coded colours.

## Type

- Headings: **Estedad** (`--font-accent`), weights 700–900, self-hosted (OFL).
- Body: **YekanBakh** (`--font-main`), 16px minimum on inputs (no iOS focus zoom).
- **AbarHigh** (`--font-special`) is used only for the home greeting.
- Persian digits by default; `data-latin-digits` for codes.
- Separators: use the Persian comma «، ». YekanBakh draws `·` like a Persian zero («· ۱» reads «۱۰»).

## The signature: indicator tape

One cell per question (grouped evenly past 60 cells so every cell stays visible). Raw cells are hatched rose; answered cells turn dark; a teal underline marks flagged; an outlined cell marks the current question. It appears on:

- the exam start screen (progress in this set)
- the session band (progress, current question)
- home «ادامهٔ آزمون» and the hub resume strip
- the course sales page (the size of the set)
- grades (20 cells: the grade, with the class average ticked)

## Composition rules

- Flat: hairline rules, not cards; 8px controls; no glass, no gradients, no thick coloured side bars.
- **No eyebrow/kicker labels above headings.** A breadcrumb link (the course name above an exam title) is navigation, not a label.
- One filled primary per screen, placed in thumb reach on mobile; secondaries are outlined or plain text.
- Header: one row, `[back | ۱۴۰۲] [section name over the site name] [search theme] [avatar]`. On mobile it is the teal band; on desktop it is a quiet bar beside the teal rail.
- No dead ends: every page has a way back (header back, bot «منوی اصلی»).

## Key surfaces

- **Exam start:** no mode-selection step. It shows the title, one meta line, the tape, the personal-practice disclosure, one primary (start, or continue from N) and the other mode as a direct action.
- **Exam session:** teal band (count, timer, map, isolated exit, save status, tape), the question within the first ~200px, options as rows, and previous/flag/next sticky under the thumb.
- **Home:** greeting, continue card, class plan, sample-question sets, «درس‌ها، ترم به ترم», new items, university links. No reference shelves.
- **Exams hub:** «بر اساس ترم» is the default (it opens on the term of the newest sample set); «آرشیو رفرنس‌ها» is second.
- **Buy, receipt, cart, collection pay:** receipt-style rows and amounts in toman.
- **Chat:** keeps its messenger structure; only its palette follows the tokens.

## Retired (bot-first, 2026-11-12)

The home quick-access row; grades, shop and notifications in the menus; and the `/notes/`, `/resources/` and `/dis-request/` pages (they now show a "moved to the bot" notice; the APIs remain).
