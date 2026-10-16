---
version: 1
slug: "templates-home-php"
primary_target: "templates/home.php"
related_targets: ["templates/layout.php","templates/partials/submission.php","templates/partials/programme.php"]
---

# Surface brief: home (landing page + submission form)

Scope: templates/home.php with layout, partials, and the pages that share them (submit, submitted, privacy, imprint, error, admin). Mode: Persuade (landing) with an Operate block (the form).

Audience and job: PhD students and postdocs (HSG and external) decide on a phone whether to submit, then submit one PDF on a laptop before the deadline. Proof: real programme format, real senior faculty, honest TBA placeholders. Constraints: strict CSP, self-hosted fonts, no imagery, WCAG 2.2 AA, IWI colours binding.

## Direction contract

THESIS: White paper, green deadline. The page reads like an IWI call for papers set with Anduril/Palantir discipline: one humanist face, scale instead of effects, hairline index rows instead of cards, colour only as large flat fields. It refuses the category default of a dark hero with glows, stat row and equal cards.

OWN-WORLD: White #FFFFFF ground, ink #111111, grey #6F706F, band #F5F5F5, HSG green #00802F for every action and the deadline tile, dark green #0A5E2D for faculty tiles, menu sheet and footer, mint #CFE6D6 for secondary text on dark green, beige #E1D7C3 only for the draft bar. Source Sans 3 300/400/600, light display, tabular figures. Square corners, 1px rules, IWI diagonal edge on the deadline tile and the submit band, green square arrow buttons, SVG icons.

STORY: Visitor learns what (paper development workshop on data studies, two tracks), when and where (Thu 3 Dec, St.Gallen), and that the deadline is 5 Nov 23:59 Zurich time; believes the feedback is serious (faculty, programme); submits through a calm, robust form and gets an ID.

FIRST VIEWPORT: Desktop 1440: HSG logo + nav + green "Submit by 5 Nov" button in a 72px white bar. Left 7 columns: date line 24px, H1 96px light, two-tone statement 28px, two text links. Right 5 columns: HSG-green deadline tile with slanted top edge, "5 Nov" at 144px light, time zone line, white "Submit your paper" button. Mobile 390: logo, Submit and Menu in a 60px bar; H1 44px; deadline tile with "5 Nov" at 80px and full-width button above the fold.

FORM: Pinned by the user and the chair (IWI colours, Anduril/Palantir coolness); no concept-seed roll. Chosen from three workflow directions: "IWI White, Statement Index" with grafts from "Cut & Tile". Seed key: none (pinned brief). Hero variant chosen by the user: all white with the green tile.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Unresolved

Logo use pending formal approval by HSG Communications (the user chose to use the HSG logo now). Final title, deadline time (23:59 assumed), rooms, presenters in the programme, CHAIS logo file (co-organiser shown as text until it arrives).
