# iContent Question Engine Parity Checklist & Migration Sequence

Date: 2026-02-28

## Architecture Decision (2026-03-01)

Adopt an **engine-first** approach for all qtypes, aligned with Moodle Question API and `filter_embedquestion` patterns.

Decision:

- iContent should use `question_usage_by_activity` (`QUBA`) as the canonical interaction engine for every qtype.
- iContent should not maintain per-qtype custom grading/render logic except for iContent-specific presentation wrappers.
- iContent-specific behavior to keep: post-submit summary card (`RESULTS OF THE LAST ATTEMPT`) and activity-level reporting integration.

References reviewed:

- Moodle Question subsystem docs (5.2):
	- `question_engine` orchestration
	- `question_attempt` + `question_usage_by_activity`
	- `question_display_options` control of shown feedback elements
- `moodleou/filter_embedquestion` implementation patterns:
	- create/load usage and slot, render via `$quba->render_question(...)`
	- process actions via `$quba->process_all_actions(...)`
	- save via `question_engine::save_questions_usage_by_activity(...)`
	- restart/attempt reset by starting new attempt or discarding broken usage

---

## Goal

Move `mod_icontent` to **question-engine parity** with Quiz / embedded usage, so each supported qtype:

- renders with equivalent UI/behavior,
- grades consistently,
- stores attempts correctly,
- supports retry/reset correctly,
- reports results consistently (including manual review where applicable).

Non-goal:

- preserving legacy iContent question HTML/validation behavior when it differs from Moodle question engine behavior.

---

## Current Cycle Targets

Use this as the quick daily focus list before running full checks.

Sync source: `Section 5) Live Tracking Table` is the source of truth.

Target checkbox rule:

- Mark `[x]` only when status is `GO` in the Live Tracking Table and no new regression is logged in the latest cycle.
- Keep `[ ]` for `NO GO` and `NOT TESTED`.
- If status changes, update this section in the same edit as the Live Tracking Table and Cycle Log.

- [x] `truefalse`: submit UX updates immediately after clicking Send answers.
- [x] `multichoice`: all-correct selection returns correct fraction.
- [x] `match`: normal-width layout is usable and right-answer count is correct.
- [x] `essay`: manual review shows student response text reliably.
- [x] `shortanswer`: retry clears previous response value every time.
- [x] `numerical`: correct answer grading matches expected fraction.

## Next Wave Targets (More Question Types)

Prioritization basis:

- installed qtype inventory in this site (`35` plugins), and
- local question volume by qtype (highest first, excluding already validated core six).

Immediate Wave A (highest value, likely compatible with current AJAX transport):

1. `varnumeric` (350 questions)
2. `formulas` (268 questions)
3. `ddwtos` (145 questions)
4. `gapselect` (77 questions)
5. `stack` (62 questions)

Wave B (next high-value set):

6. `gapfill` (54 questions)
7. `wordselect` (18 questions)
8. `calculatedmulti` (13 questions)
9. `calculated` (10 questions)
10. `musictheory` (10 questions)

Transport compatibility note:

- Current iContent submit path serializes form fields (`application/x-www-form-urlencoded`).
- Qtypes requiring file uploads / rich binary payloads may need a multipart submit flow before parity can be fully validated.
- For such qtypes, treat first pass as “render + basic submit viability” and flag any upload/filemanager gaps.

Wave A execution baseline (2026-03-01):

- Site-wide availability:
	- `varnumeric`: 350
	- `formulas`: 268
	- `ddwtos`: 145
	- `gapselect`: 77
	- `stack`: 62
- Currently linked in iContent pages: none detected for Wave A qtypes.

Wave A unblock checklist:

- [ ] Add at least 1 slide question each for `varnumeric`, `formulas`, `ddwtos`, `gapselect`, `stack`.
- [ ] Run one student pass (correct + wrong + retry) per qtype.
- [ ] Log GO/NO GO per qtype in the Live Tracking Table.
- [ ] Add cycle log entry with any field-transport anomalies (especially file/upload-heavy qtypes).

Wave A early observation (2026-03-01):

- `ddwtos` smoke test: render and grading behavior reported as working in iContent (provisional GO pending full pass matrix).

---

## 1) Concrete Parity Checklist (per qtype)

Use this checklist for every qtype before enabling engine-first behavior in iContent.

### A. Render parity

- [ ] Question text, media, and formatting match Quiz output.
- [ ] Input controls match expected qtype UX (radios/select/text/editor/etc).
- [ ] Layout works in normal viewport widths (no overflow/hidden controls).
- [ ] Accessibility basics hold (focusable controls, labels, keyboard usable).

### B. Submission and grading parity

- [ ] Correct response is graded as correct.
- [ ] Incorrect response is graded as incorrect.
- [ ] Partial credit works (if qtype supports partial marks).
- [ ] Blank/incomplete response does not create false wrong attempt.
- [ ] Response summaries and right-answer summaries are stored sensibly.

### C. Retry/reset parity

- [ ] “Try again” clears previous response values.
- [ ] “Try again” clears cached engine state for that page/user.
- [ ] New submission after retry behaves as first attempt.

### D. Reporting parity

- [ ] iContent page summary (answers/right answers/result) is correct.
- [ ] Gradebook impact is correct and stable across repeat attempts.
- [ ] Teacher result views show expected response content.
- [ ] Manual review paths (if applicable) display student response text.

### E. Regression checks

- [ ] Existing legacy qtypes on older activities still function.
- [ ] Mixed pages (multiple qtypes together) submit without hangs.
- [ ] AJAX save returns update immediately (no stale button/blocked UI).

### F. Engine contract checks

- [ ] Render path uses engine output (`QUBA` + `question_display_options`) for this qtype.
- [ ] Submit path uses engine action processing (`process_all_actions`) for this qtype.
- [ ] Stored fraction/status for reporting is derived from `question_attempt` state/fraction, not custom evaluator logic.
- [ ] Retry path resets both persisted attempts and cached usage identity for user/page.

---

## 2) Test Case Matrix (minimum)

For each qtype under test, prepare **at least two questions**:

1. **Simple baseline** (single obvious right answer).
2. **Representative real-world** item from archived teaching content.

For each question, run these attempts:

- Attempt 1: fully correct
- Attempt 2: fully wrong
- Attempt 3: blank/incomplete (if possible)
- Attempt 4: retry after clear/reset

Record:

- expected fraction,
- actual stored fraction,
- displayed right answers,
- summary row values,
- manual review visibility (essay-like qtypes).

---

## 3) Migration Sequence (qtype-by-qtype)

Use progressive rollout with explicit gates.

Execution rule for all phases:

- For each qtype, remove custom grading/render switch branches once parity is confirmed.
- Keep only thin iContent adapter code that maps engine state into iContent summary/report rows.

## Phase 0 — Stabilize baseline

Scope: keep current stable behavior and confirm test matrix is ready.

Exit gate:

- [ ] Test questions created for target qtypes.
- [ ] Baseline behavior documented (current GO/NO GO state).

## Phase 1 — Objective qtypes (low ambiguity)

Order:

1. `truefalse`
2. `multichoice`
3. `shortanswer`

Why first: deterministic grading and easy parity checks.

Exit gate per qtype:

- [ ] All checklist sections A–E pass.
- [ ] No regressions on already-passed qtypes.

## Phase 2 — Structured mapping qtypes

Order:

4. `match`
5. `numerical` (if used)

Why next: increased UI and response-mapping complexity.

Exit gate:

- [ ] Layout parity at standard viewport widths.
- [ ] Correct mapping/partial scoring validated.

## Phase 3 — Manual-review and rich input qtypes

Order:

6. `essay`
7. Any editor/file/manual-graded qtypes you use.

Why later: requires teacher review/report parity, not just auto-grade parity.

Exit gate:

- [ ] Teacher sees submitted response content in manual review.
- [ ] Manual score writeback reflects in student summary and grades.

## Phase 4 — Extended/custom qtypes

Scope: optional/site-specific plugins (archived types you revive).

Exit gate:

- [ ] qtype-specific exceptions documented.
- [ ] Feature-flag fallback retained for unsupported edge cases.

---

## 4) Rollout Rules

- Enable engine-first for a qtype **only after** that qtype passes checklist.
- Keep qtype-level fallback switch while migrating adjacent qtypes.
- If any NO GO appears, revert only the affected qtype path, not the whole rollout.

Additional implementation rules:

- Prefer engine field naming (`$qa->get_qt_field_name(...)`) and submitted data extraction patterns used by core/tests.
- Prefer `question_display_options` to control what is shown, instead of modifying qtype renderer internals.
- Avoid interpreting qtype internals in iContent whenever engine state APIs already expose needed result data.

---

## 5) Live Tracking Table

Update one row per testing cycle. Keep newest notes concise and dated.

| Qtype | Current status | Last observed issue | Last update | Next verification focus |
|---|---|---|---|---|
| `multichoice` | GO | Engine-first submit/grading now passes checks for students, teachers, and admin. | 2026-03-01 | Keep regression checks in mixed-qtype pages and repeat-attempt flows. |
| `truefalse` | GO | Engine-first submit/render now updates correctly for students, teachers, and admin. | 2026-03-01 | Keep regression checks in mixed-qtype pages and repeat-attempt flows. |
| `match` | GO | Engine-rendered layout now fits normal widths and fully-correct responses score correctly. | 2026-03-01 | Keep regression checks in mixed-qtype pages and repeat-attempt flows. |
| `essay` | GO | Manual review now shows response text and grading flow works; validated with manager roles. | 2026-03-01 | Keep regression checks for grade/manual-review switching and writeback. |
| `shortanswer` | GO | Correct grading and retry behavior verified in student walkthrough. | 2026-03-01 | Keep regression checks for repeat-attempt state reset. |
| `numerical` | GO | Correct answer grading verified after engine mapping fix. | 2026-03-01 | Keep regression checks for unit/format variants. |

Legend: `GO` = passes current cycle, `NO GO` = blocking defect exists, `NOT TESTED` = no validation run yet.

---

## 6) Cycle Log

Append a new cycle at the top each time you complete a test pass.

Cycle update order (always apply in this order):

1. Add new cycle entry at top of this section.
2. Update affected rows in `Section 5) Live Tracking Table`.
3. Sync the checkboxes in `Current Cycle Targets` based on updated statuses.

### Cycle Template

```
### Cycle YYYY-MM-DD (Name/Initials)
- Scope:
- Environment/build:
- Qtypes tested:
- New GO:
- New NO GO:
- Regressions detected:
- Code changes in this cycle:
- Next cycle priority:
```

### Cycle 2026-03-01 (ALR, Wave A in progress)
- Scope: Imported additional questions; beginning Wave A execution while reorganizing categories/question banks for efficient page assembly.
- Environment/build: `moodledev`.
- Qtypes tested: `ddwtos` (initial smoke).
- New GO: `ddwtos` provisional GO (render and result behavior observed as correct).
- New NO GO: none reported in this mini-cycle.
- Regressions detected: none reported in this mini-cycle.
- Code changes in this cycle: none (observation-only cycle).
- Next cycle priority: assemble dedicated iContent test pages for remaining Wave A qtypes (`varnumeric`, `formulas`, `gapselect`, `stack`) and run full matrix.

### Cycle 2026-03-01 (ALR, student full-run)
- Scope: Full new-student walkthrough across all six qtypes, plus replay on older iContent test activity.
- Environment/build: `moodledev`, post-cache-purge after latest match/numerical fixes.
- Qtypes tested: `truefalse`, `multichoice`, `match`, `essay`, `shortanswer`, `numerical`.
- New GO: `match`, `shortanswer`, `numerical` (all six qtypes now GO in this cycle).
- New NO GO: none.
- Regressions detected: none blocking for qtype parity.
- Code changes in this cycle: engine-first enablement and adapter fixes for match/numerical; latest-attempt summary alignment; match layout responsiveness.
- Notes: intermittent TinyMCE toolbar visibility observed on some essay pages (non-blocking, monitor in next runs).
- Deferred anomaly: grade column in [mod/icontent/grade.php](mod/icontent/grade.php) showed unexpected total (e.g. `54.55` on a `0-10` scale) despite correct answers; deferred by request while expanding qtype coverage.
- Next cycle priority: expand to additional qtypes and revisit deferred grade-scale anomaly.

### Cycle 2026-03-01 (ALR)
- Scope: Phase 1 engine-first validation after routing `truefalse` and `multichoice` back to question engine path.
- Environment/build: `moodledev`, caches purged after code changes.
- Qtypes tested: `truefalse`, `multichoice`, `essay`.
- New GO: `truefalse`, `multichoice`, `essay` (validated by student, teacher, admin/manager roles).
- New NO GO: none in this cycle for tested qtypes.
- Regressions detected: none reported for tested qtypes.
- Code changes in this cycle: enabled engine support for `truefalse`/`multichoice`/`essay`; hardened engine submit mapping using expected-data extraction and engine grading/summary APIs; ensured essay response text capture for manual review.
- Next cycle priority: resolve `match` (layout + right-answer counting), then confirm shortanswer retry-clearing consistency.

### Cycle 2026-02-28 (ALR)
- Scope: Initial parity tracking baseline and regression triage.
- Environment/build: `moodledev`, iContent question-engine migration in progress.
- Qtypes tested: `multichoice`, `truefalse`, `match`, `essay`, `shortanswer`.
- New GO: `shortanswer` provisional GO.
- New NO GO: `multichoice`, `truefalse`, `match`, `essay`.
- Regressions detected: Legacy parity regressions and retry/state persistence mismatch.
- Code changes in this cycle: feature-flag enablement, shortanswer/truefalse grading fixes, temporary qtype routing rollback for legacy types, matching layout adjustment, tracking plan setup.
- Next cycle priority: stabilize `truefalse` immediate submit UX and `multichoice`/`match` scoring parity; restore essay manual-review response visibility.

---

## 7) Suggested Tracking Format (copy/paste)

Use one block per test run:

```
Date:
Qtype:
Question ID(s):
Scenario: correct / wrong / blank / retry / manual-review
Expected:
Actual:
Status: GO | NO GO
Notes:
```

---

## 8) Definition of Done (overall)

Migration is complete when:

- [ ] All commonly used qtypes pass parity checklist.
- [ ] Retry/reset is clean (no stale answer reappearance).
- [ ] Teacher result and manual review flows are reliable.
- [ ] No known regressions in mixed-qtype pages.
