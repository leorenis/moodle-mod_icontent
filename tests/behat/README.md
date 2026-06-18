# iContent Behat tests

Last updated: 2026-03-19

This folder contains iContent-specific Behat coverage focused on plugin behavior, not core Moodle qtype internals.

## Scope

- Student access and empty/content states for iContent pages.
- Teacher manual-review dashboard access and messaging.
- Student result summary visibility of:
  - submitted answers,
  - pending manual-review state,
  - teacher reviewer comments.
- Basic default qtype coverage in iContent result summaries:
  - `essay` (pending review),
  - `multichoice`,
  - `shortanswer`,
  - `match`.
- Question-management coverage in edit mode:
  - per-question remove icon visibility,
  - remove action result for linked questions,
  - page delete from both toolbar and TOC with related cleanup verification.
- Installation-specific inventory coverage for this server:
  - additional non-core qtypes configured in `Questions Testing`,
  - full page/question mapping inventory for `Questions Testing`.

## Feature files

- `icontent_student_flow.feature`
- `icontent_teacher_grading.feature`
- `icontent_reviewer_comments.feature`
- `icontent_answers_and_comments.feature`
- `icontent_default_qtypes.feature`
- `icontent_default_qtypes_autograded.feature`
- `icontent_question_management.feature`
- `icontent_capabilities.feature`
- `icontent_local_additional_qtypes.feature` (`@local`)
- `icontent_local_questions_testing_inventory.feature` (`@local`)

## Default vs local split

- Keep default-only regression coverage in:
  - `icontent_default_qtypes.feature` (manual-review default qtypes)
  - `icontent_default_qtypes_autograded.feature` (auto-graded default qtypes)
- Keep installation-specific checks in `@local` feature files so other servers can skip them safely.

## Custom Behat context

Custom iContent setup/navigation steps are defined in:

- `behat_mod_icontent.php`

These steps seed minimal iContent pages and attempts directly so scenarios remain stable and fast.
They also cover seeded note/like data and page deletion assertions for cleanup-sensitive regressions.

## Running tests

### Run full iContent smoke pack

From Moodle root:

```bash
./admin/tool/behat/cli/run_icontent_smoke.sh
```

### Run local installation smoke pack (`@local`)

From Moodle root:

```bash
./admin/tool/behat/cli/run_icontent_local_smoke.sh
```

### Run from terminal with PHP commands only

If you prefer direct `php` commands (no `.sh` runner), use Moodle's Behat runner with explicit feature files.

Default/portable smoke scenarios:

```bash
php admin/tool/behat/cli/run.php --config /var/moodledata/behatmoodledatadev/behatrun/behat/behat.yml --format=progress /var/www/moodledev/mod/icontent/tests/behat/icontent_student_flow.feature /var/www/moodledev/mod/icontent/tests/behat/icontent_teacher_grading.feature /var/www/moodledev/mod/icontent/tests/behat/icontent_reviewer_comments.feature /var/www/moodledev/mod/icontent/tests/behat/icontent_answers_and_comments.feature /var/www/moodledev/mod/icontent/tests/behat/icontent_default_qtypes.feature /var/www/moodledev/mod/icontent/tests/behat/icontent_default_qtypes_autograded.feature /var/www/moodledev/mod/icontent/tests/behat/icontent_question_management.feature
```

Local installation scenarios only:

```bash
php admin/tool/behat/cli/run.php --config /var/moodledata/behatmoodledatadev/behatrun/behat/behat.yml --format=progress /var/www/moodledev/mod/icontent/tests/behat/icontent_local_additional_qtypes.feature /var/www/moodledev/mod/icontent/tests/behat/icontent_local_questions_testing_inventory.feature
```

Run one specific scenario by name:

```bash
php admin/tool/behat/cli/run.php --config /var/moodledata/behatmoodledatadev/behatrun/behat/behat.yml --format=progress --name="Student sees submitted answer for shortanswer in result summary" /var/www/moodledev/mod/icontent/tests/behat/icontent_default_qtypes_autograded.feature
```

### Run one feature file directly

```bash
vendor/bin/behat \
  --suite=default \
  --config /var/moodledata/behatmoodledatadev/behatrun/behat/behat.yml \
  --format=progress \
  /var/www/moodledev/mod/icontent/tests/behat/icontent_default_qtypes_autograded.feature
```

## Prerequisites

- Behat environment initialized/enabled (`admin/tool/behat/cli/util_single_run.php --enable`).
- Valid Behat config at:
  - `/var/moodledata/behatmoodledatadev/behatrun/behat/behat.yml`
- Selenium/WebDriver available for JS scenarios (the current iContent smoke pack is non-JS).

## Update checklist

When changing iContent Behat coverage, update the following in the same PR:

- Bump the `Last updated` date in this file.
- Add/remove renamed `.feature` files in the **Feature files** list.
- Keep `admin/tool/behat/cli/run_icontent_smoke.sh` aligned with the intended smoke scenarios.
- If new custom setup/navigation steps were added, ensure they are documented under **Custom Behat context**.
- Run the smoke pack once and include pass/fail summary in your ticket/notes.
