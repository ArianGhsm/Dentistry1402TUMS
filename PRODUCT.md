# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users
Dental students of Tehran University of Medical Sciences (TUMS), across all entry cohorts (ورودی‌ها). Today the live cohorts are «ورودی ۱۴۰۲ دندانپزشکی» and «۱۴۰۲ پروتز», and more cohorts are planned. They use the site on phones and on desktop: between classes, in clinic rotations, and in the days before midterms and finals. The owner (a classmate, the site admin) also runs admin, content and payment tools.

## Product Purpose
A class hub for TUMS dental students. Its two most important jobs, confirmed by the owner:
1. **Practice exams** (آزمون تمرینی): reference-based exams (by textbook: ترابی‌نژاد, برکت, فالاس, کریگ, ون نورت, زارب, پیترسون, مالامد, White & Pharoah, استردوانت, سامیت …) and term-based exams (by term and course, e.g. «نمونه‌سؤالات میان‌ترم مبانی پروتز پارسیل ۱», 41 questions). Exams have sections or sessions, a focused question-taking mode, resume, flags and review.
2. **Buying** (خرید): one-time access bundles for exam sets (currently «با یک بار پرداخت ۴۵ هزار تومان» per course or reference), DentNote booklets and flashcards, and endodontic tools such as burs (e.g. «Crown Cutter — FG — 010», Belgium or Taiwan, about ۲۰۵–۲۴۵ هزار تومان). Payment goes through an Iranian gateway (Zibal) with a cart.
Secondary: study resources and booklets by term (ترم ۴ … ۸), grades (نمرات), forms, class chat, notifications, class operations and schedule, and links to university systems (سپیاد، تغذیه، همیاد). Success means a student opens the site, reaches the right exam or purchase in seconds on a phone, and trusts it with money.

## Positioning
Built by and for the class itself: the course list, references, schedules and exam banks follow the actual TUMS dental curriculum term by term, and access ties into the class's own Telegram and Bale bots. A generic study site cannot claim the cohort-specific curriculum map or the class identity.

## Operating Context
- Mostly mobile, often on Iranian mobile networks. Fonts and assets must be self-hosted; Google Fonts and CDNs can be blocked or slow.
- Installable PWA with a bottom navigation (خانه، آزمون‌ها، چت/منابع) and an account menu in the header.
- Persian (fa-IR), RTL, Persian digits and the Jalali calendar for user-facing dates and times.
- Exams are taken in long focused sessions, sometimes timed; students review mistakes afterwards.
- Purchases unlock access per account; login is via the site's own account plus OTP.

## Capabilities and Constraints
- Stack: vanilla PHP + HTML/CSS/JS with no build step, served by Nginx. Shared shell (`shell.js`, `core.css`, `theme.css`), many catalog-generated pages, and APIs under `public_html/api/`. The redesign changes look and UX only; APIs, data, auth, sessions, payments and bots stay as they are.
- Multi-cohort: differences must be config or role driven, with no per-cohort forks.
- Released section by section to production through the existing release gate.
- The legal and payment flow (gateway, cart, receipts) must stay clear and trustworthy.

## Brand Commitments
- Keep the «۱۴۰۲» logo mark (the owner made this binding). Fonts, palette and every other visual are open to change; YekanBakh and AbarHigh are licensed and already self-hosted, so they are allowed but not required.
- Name in use: «ورودی ۱۴۰۲ دندانپزشکی تهران». Footer credit: «ساخته‌شده با عشق، آرین قاسم‌پور» (the words are binding; «،» replaced «·», which YekanBakh draws like a Persian zero).

## Evidence on Hand
- Real exam catalog: `/api/exams_api.php?action=catalog` (terms, courses, references, badges such as «۶ بخش» and «۴۱ سؤال»).
- Real shop data: exam access collections at 45,000 toman; about 388 active endodontic tool items.
- No testimonials, ratings, user counts or press. Do not invent any.

## Product Principles
1. Exam first: practice and review must be the fastest path from any screen.
2. Money needs trust: prices, what you get and access status are always explicit before payment.
3. Curriculum is the map: organise by term, course and reference the way TUMS students think.
4. Phone in one hand: primary actions within thumb reach, readable on small screens and slow networks.
5. One system for every cohort, configured rather than forked.

## Accessibility & Inclusion
Long reading sessions call for strong contrast and readable Persian type at 16px or more for body and inputs, with no forced zoom lock. Respect `prefers-reduced-motion`, and design for both light and dark viewing in evening study.
