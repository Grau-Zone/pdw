# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Doctoral students in information systems and data studies, from the University of St.Gallen (HSG) and from other universities. They arrive from a call for papers, decide whether the workshop fits their paper, and then submit an extended abstract as a PDF before the deadline. Many will read on a phone first and submit later from a laptop.

Secondary audiences: senior faculty checking the programme, and the organisers handling the submissions (by e-mail; in form mode in the admin area).

## Product Purpose

Landing page and paper submission for a one-day paper development workshop (PDW) for doctoral students at HSG, Thursday 3 December 2026, St.Gallen, in two tracks: Data Platforms and Ecosystems (chairs Jan Marco Leimeister, Andreas Hein) and Data Governance (chairs Christine Legner, Aleksi Aaltonen). Submissions are extended abstracts structured with the Emerald template (Purpose, Design/methodology/approach, Findings, Limitations/implications, Practical implications, Originality); deadline 5 November 2026, notification 12 November 2026.

Success means enough fitting submissions to fill the paper slots in the two parallel tracks, each submitted correctly and before the deadline, with no lost or duplicated papers.

## Positioning

A small, in-person workshop where every accepted paper gets focused feedback from senior faculty in a small group. Digitally sovereign: hosted in Switzerland, no US clouds, CDNs, trackers, cookies, external fonts or captchas.

## Operating Context

- Content lives in one file, `config/event.php`; still TBA: final title, CfP text and track scope, exact agenda and times, rooms.
- Programme follows a fixed format: two parallel paper tracks with three paper sessions (two in the morning, one after lunch; 30-minute slots), then a 45-minute panel with all four senior faculty (moderated by Andreas Hein), final remarks and apéro. No keynote and no 1:1 coaching sessions.
- Submission (since 14 Oct 2026): authors send the extended abstract as a PDF by e-mail to the address in `submission.email`; the page shows instructions instead of an upload form (`submission.method = 'email'`). The upload form with versions, confirmation IDs and admin export stays in the code (`method = 'form'`).
- Staged go-live: draft banner while `draft: true`; the form stays closed until the privacy office approves (`privacy.approved`).
- A static preview is shared for feedback (in form mode without a working form).

## Capabilities and Constraints

- PHP 8.2+, SQLite, PHPMailer, no framework; plain CSS and a small progressive-enhancement script.
- Strict Content-Security-Policy: no inline styles or scripts, no third-party domains.
- Deadline is enforced by the server in Europe/Zurich time, not the browser.
- PDF only (form mode: max. 10 MB, checked by the server).
- The repository is public: no internal or personal information in tracked files.
- Extended abstracts are max. 2 pages. Undecided: time of the deadline (23:59 assumed), listener-only registration. The preferred track is asked for in the submission e-mail.

## Brand Commitments

- Colours and institutional spirit follow the host institute's site, iwi.unisg.ch (IWI-HSG): white ground, HSG green `#00802F`, dark green `#0A5E2D`, light grey `#F5F5F5`, grey `#6F706F`. Binding (decided by the chair, 12.10.2026).
- The page must be clearly cooler than iwi.unisg.ch, at the level of anduril.com and palantir.com (their design discipline, not their subject matter or tone).
- The earlier Sovereignty Radar look (dark ground, glows, glass cards, pill buttons) is no longer binding.
- The HSG logo is in use by decision of the project team (12.10.2026); formal CI/logo approval by HSG Communications is pending.
- Fonts are self-hosted (SIL OFL); IWI's Gill Sans MT Pro is licensed and not available.
- Site language: English.

## Evidence on Hand

- Senior faculty list (names, affiliations) in `config/event.php`; no bios or photos yet, and photos only with consent.
- No testimonials, past-edition numbers or photos exist. Do not invent them.
- The CHAIS PDW Bern programme template in `material/` contains third-party personal data and must never be published.

## Product Principles

1. The deadline and the way to submit must be unmistakable at every point of the page.
2. Never imply content is final while it is TBA; placeholders stay honest and visible as placeholders.
3. Sovereignty is a feature, not a footnote: nothing loads from third parties.
4. Submitting must feel safe: clear rules, clear confirmation, no surprises about versions.
5. Small and calm over impressive: one page, one task.

## Accessibility & Inclusion

WCAG 2.2 AA. Body text at least 4.5:1 contrast, large text at least 3:1, keyboard-operable form. Exception decided by the project team (12.10.2026): animations always run, the reduced-motion setting is ignored; self-running motion can be paused with a keyboard-focus pause control (WCAG 2.2.2).
