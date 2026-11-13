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

- Headings: **Estedad** (`--font-accent`), weights 700–900, self-hosted (OFL), on inner pages.
- Body: **YekanBakh** (`--font-main`), 16px minimum on inputs (no iOS focus zoom).
- **Home and the header bar use YekanBakh alone** (variable 100–900; hierarchy by weight: 800 headings, 700 names and buttons, 400 text). Two near-identical Persian sans faces side by side read as "off" (owner, 2026-11-13). The greeting is plain YekanBakh: AbarHigh is retired from it.
- Sizes on a phone: nothing under 12px; row titles 16px, meta 14px; every control 44px tall or more.
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
- One filled primary per screen, placed in thumb reach on mobile; secondaries are outlined (accent hairline) or plain text. Row actions are outlined, never filled.
- One container: every home section sits in `.h26-wrap` (`min(100% - 2 × gutter, 1120px)`; gutter 20/32/48px), so both edges line up all the way down.
- Dark mode: the shell (`--brand-navy-950` #0f302d) stays visibly lighter than the near-black ground; the phone status bar (`theme-color`) continues the shell in both themes.
- Focus on the shell (lead band, rail) is a white 2px ring; on paper it is `--focus-ring`.
- Header: one row, `[back | ۱۴۰۲] [section name over the site name] [search theme] [avatar]`. On mobile it is the teal band; on desktop it is a quiet bar beside the teal rail.
- No dead ends: every page has a way back (header back, bot «منوی اصلی»).

## Key surfaces

- **Exam start:** no mode-selection step. It shows the title, one meta line, the tape, the personal-practice disclosure, one primary (start, or continue from N) and the other mode as a direct action.
- **Exam session:** teal band (count, timer, map, isolated exit, save status, tape), the question within the first ~200px, options as rows, and previous/flag/next sticky under the thumb.
- **Home:** the lead band (greeting line, h1 of what is sold, one hard fact, then the newest set with its tape and the one filled buy, or the exam to continue), «نمونه‌سؤال‌های دیگر» rows, class plan, «درس‌ها، ترم به ترم» (full-width term tabs, the same rows), university sites, and «ربات کلاس» (Telegram and Bale) as the closing action. All lists share one row: name, one meta line, an outlined action. If the catalog fails, the lead says so and offers a retry. No reference shelves.
- **Motion** (Emil Kowalski's frequency rule: home is opened many times a day): the lead enters once per session (280ms ease-out, set in `<head>` only then), the tape draws once (420ms clip-path), buttons press to 0.97; rows never animate; reduced motion turns everything off.
- **Exams hub:** «بر اساس ترم» is the default (it opens on the term of the newest sample set); «آرشیو رفرنس‌ها» is second.
- **Buy, receipt, cart, collection pay:** receipt-style rows and amounts in toman.
- **Chat:** keeps its messenger structure; only its palette follows the tokens.

## Retired (bot-first, 2026-11-12)

The home quick-access row; grades, shop and notifications in the menus; and the `/notes/`, `/resources/` and `/dis-request/` pages (they now show a "moved to the bot" notice; the APIs remain).
