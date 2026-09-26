# Changelog

## [2.0.0] - 2026-09-26

### Added
- Redesigned LMS screen under LW Plugins: Overview, Enrollments, Quiz results and the settings in one place, with a side menu, Save / Discard in the top bar, Ctrl/Cmd+S and an unsaved-changes warning.
- Enrollments: every enrollment with filters, each learner's progress, enrolling a learner by hand with an optional end date, and revoking access.
- Quiz results: all attempts with filters, one attempt's answers (only administrators see which were right), deleting attempts, CSV export without email addresses, per-question statistics.
- Personal data export and erasure for enrollments, progress and quiz attempts; LMS records are removed when a user is deleted (multisite-aware).

### Security
- The file download endpoint serves only files that belong to a course or lesson the visitor may see and access. Before, anyone could download any Media Library file by guessing its ID.
- Download links are signed and expire after one hour (lw_lms_download_link_ttl); the access check still runs on download, so revoking access stops handed-out links.
- Lessons are not readable when their course is deleted, a draft, private or not a course; draft lessons can't be opened or completed by guessing their ID.
- Draft and private courses and paid lesson content are no longer readable by Contributors, Authors or shop managers through the LMS REST API, /wp/v2/lesson or the Site Manager abilities.
- The Site Manager progress abilities need manage_lms or administrator rights and check that the lesson belongs to the course.
- Quiz answers are never given away after a submission; submissions are limited (15 seconds apart, 20 per day, filterable) and can't be raced in parallel; open answers are capped at 5,000 characters.
- Learners' email addresses in the LMS screen are shown and searchable only for users who may list users.
- Deleting the plugin keeps all data unless "Delete all data when the plugin is deleted" is on; on multisite, shared per-user data is deleted only when every site opted in.

### Fixed
- WooCommerce purchases now give access (orders never created an enrollment before, so time-limited courses gave no access at all). Access is granted while processing or completed, and revoked when the order is fully refunded, cancelled or failed, or when a course's order line is fully refunded.
- Enrolling twice no longer duplicates the enrollment, and Revoke really ends access.
- The Enable preview lessons, Default access type, Courses per page and Enable WooCommerce integration settings now take effect; preview checkboxes show their saved state.
- The lesson's section list follows the chosen course, and section IDs with capital letters keep working.
- Invalid quiz JSON in the block editor shows an error instead of being dropped silently.
- Course and lesson settings saved through the REST API or the block editor get the same validation as the edit screen.
- Time-limited access ends at the right moment regardless of the site time zone (existing profile expiry dates are not migrated).
- Browsing the course list no longer enrolls the visitor in every free course.
- Multisite: removing a user from one site removes only that site's LMS records.

### Changed
- LW LMS is documented as backend-only (headless): no frontend output; build the learner UI on the lms/v1 REST API.
- Requires PHP 8.0 (was 8.2) and WordPress 6.6.
- The settings stay administrator-only; LMS managers (manage_lms) get Overview, Enrollments and Quiz results. The old Quiz Results page redirects to the new screen.
- Removed the Show progress bar setting (no effect), the lw_lms_settings_tabs filter and SettingsPage::get_settings_group() (the settings screen is a React app), and unused capabilities and rewrite rules.

## [1.9.2] - 2026-09-25

### Fixed
- Notices from themes and other plugins (for example a theme's purchase-code or recommended-plugins notice) could show on the LW LMS screen. They are now kept off every LW Plugins screen, whatever their markup

## [1.9.1] - 2026-09-22

### Fixed
- With LW Cookie's content blocking on, a lesson video the visitor had not consented to showed as an empty black box, with no message and no way to accept the cookies it needs (issue #18). LW LMS renders no player itself. Frontends built one from the bare `video.embed` URL, and LW Cookie's placeholder cannot reach such players. When the player is built with the Vimeo Player SDK, LW Cookie blocks the SDK script, so no iframe is ever created. In a padding-ratio box with `overflow:hidden`, the placeholder is inserted but clipped. `GET /lms/v1/lessons/{id}` now returns `video.html`, a ready, self-sizing 16:9 player: an iframe for YouTube, Vimeo and Wistia, a `<video>` for self-hosted files. With LW Cookie 1.7.1+ active, its content blocking on, and the video host's cookie category not yet accepted, the player comes in LW Cookie's own blocked form. That is its `.lw-cookie-embed-block` placeholder, with "To watch this video, accept the required cookies." and an "Accept & play video" button (Hungarian translation included), followed by an iframe without `src` that carries `data-lw-blocked`, `data-lw-category` and `data-lw-original-src`. The button grants just that category through `LWCookie.acceptCategory()`, and LW Cookie's guard loads the video in place without a page reload. Consent from the banner loads it too. Nothing is requested from the video host before consent. Without LW Cookie, `video.html` is the plain player. Frontends should insert `video.html` instead of building the iframe from `video.embed`.

### Added
- `lw_lms_video_html` filter (`string $html, array $video`) for the lesson video markup.

## [1.9.0] - 2026-09-19

### Added
- Drip and linear progression (issue #16). A course can be switched from free progression to **linear**, where lessons open one after the other in the order the course builder shows, and where a schedule can hold each of them back:
  - **Course:** "opens N hours/days/weeks/months after enrollment".
  - **Section (module):** the same, or "N after the previous section is completed".
  - **Lesson:** the same, or "N after the previous lesson is completed".
  - Schedules only take effect in linear mode: with free progression a learner can open anything, so there is nothing to pace.
- The clock every "after enrollment" delay is measured from is stored per learner and course (user meta `_lw_lms_course_start_{course_id}`), written on the first grant whatever the source. A re-grant, a renewal or a second source never restarts it, and learners enrolled before a course started dripping keep their real enrollment date (recovered from their earliest access row). Runtime-only access (subscription, membership) leaves no row, so such a learner's clock starts at their first visit.
- `GET /lms/v1/courses/{id}` carries `progression` (`free` or `linear`), and every lesson in the outline carries `locked_reason` (`sequence`, `schedule` or null) and `available_at` (ISO 8601 with the site's UTC offset, or null). `accessible` is false while a lesson is held back.
- `GET /lms/v1/lessons/{id}`, `POST /lms/v1/progress`, `POST /lms/v1/lessons/{id}/quiz` and `GET /lms/v1/download/{id}` answer 403 `lesson_locked` for a lesson the learner is entitled to but cannot open yet, with `locked_reason` and `available_at` in the error data — so a frontend can tell "not yours" from "not yet".
- Admin: a "Progression & Drip" box on the course (progression plus the course delay), a "Drip" box on the lesson, and a schedule per section in the course builder with a plain-language summary next to the section title.
- WP-CLI: `wp lw-lms course set-drip`, `wp lw-lms lesson set-drip`, `wp lw-lms drip status <user> <course>` (per-lesson state, reason and unlock time — the answer to "why is this still locked for them?") and `wp lw-lms drip set-start` to move or clear a learner's clock.
- `lw_lms_lesson_locks` filter over the locked lessons of a course for one learner, so a companion plugin can open or hold back a lesson.
- Never held back: a lesson the learner already completed, a preview lesson, every lesson of a course they have finished, users covered by Staff Access, and open courses (they are readable without logging in, so pacing the logged-in half of the audience would be theatre). `wp lw-lms force-complete` and LW Site Manager stay admin overrides.

### Fixed
- The course builder now sanitizes the section list it receives instead of storing the decoded JSON as it came: only `id`, `title`, `description`, `order` and the drip rule survive, and a section id keeps its characters and its case (so lessons stay attached to it).

## [1.8.3] - 2026-09-19

### Fixed
- Security: every protected attachment was downloadable by anyone. `GET /lms/v1/download/{id}` finds the course or lesson a file belongs to by matching the attachment id inside the `_lw_lms_attachments` meta, but searched for a JSON fragment (`"id":8`) while WordPress stores the meta serialized (`s:2:"id";i:8;`). The lookup never matched, so `find_attachment_parent()` returned null and the controller took the file for one that has nothing to do with the LMS — the branch that serves it without any check. Verified against 1.8.2: an anonymous request for a paid lesson's attachment answered 200 with the file. The id is now matched in its serialized shape (integer and legacy string form), so the existing course/lesson access check actually runs. Files that belong to no course or lesson are still served, as before.

## [1.8.2] - 2026-09-19

### Added
- Payload filters for the `lms/v1` REST API, so a companion plugin (certificates, badges, extra resources, a per-course CTA) can put its data into the responses frontends already fetch instead of needing a second request or `rest_post_dispatch` route matching (issue #27):
  - `lw_lms_rest_course_list_item( array $data, WP_Post $post, int $user_id )` — each item of `GET /lms/v1/courses`.
  - `lw_lms_rest_course( array $data, WP_Post $post, int $user_id, bool $has_access )` — `GET /lms/v1/courses/{id}`.
  - `lw_lms_rest_lesson( array $data, WP_Post $post, int $user_id )` — `GET /lms/v1/lessons/{id}`, only after the lesson access check passed.
- Callbacks may only add top-level keys. The keys core wrote (`access`, `accessible`, `quiz`, `progress`, …) are enforced: an override or removal is dropped, and a callback that returns a non-array leaves the core payload intact.

## [1.8.1] - 2026-09-14

### Added
- Quiz attempt history: every submission is stored as a row in the new `{prefix}lms_quiz_attempts` table (user, lesson, course, score, percentage, passed, submitted_at) together with a self-contained answer snapshot — question prompt, the option texts given and expected — so an attempt stays readable after the quiz itself is reworded, and a disputed result can be answered with evidence. The user meta summary stays as the fast path for the lesson payload and the completion gate, and now also carries `attempts` and `best_percentage`. Attempts recorded by 1.8.0 are lifted into the table on update (with `answers` NULL: they were never stored), and their summary is seeded with the counters it never had, so the next submission continues the count instead of restarting it.
- `review` in the `last_attempt` object of `GET /lms/v1/lessons/{id}` — the stored snapshot of the learner's most recent attempt, so a reload can still show what they answered and what was right.
- Quiz metabox on the lesson editor: a readable listing of the stored quiz (questions, types, options with the correct one marked) plus a JSON editor validated by the same `QuizNormalizer` the CLI uses, so an editor gets the exact error path (`quiz.questions[3].options must mark exactly one option as correct`) and keeps their input when a save is rejected. A `Quiz` column on the All Lessons list shows question count and pass threshold.
- "Quiz Results" page under LW Plugins (`manage_lms`): per-learner attempts, best and last percentage, whether they ever passed and when, with a pager — plus per-question statistics (answered, correct, wrong, correct ratio) over the most recent 500 attempts of the lesson, which is what shows a question the lesson never actually taught.
- Optional stable `id` on single-choice options. Answers may be submitted as an option id instead of a positional index, so inserting or reordering options no longer silently changes the meaning of previously stored answers. Wrong answers now also return `correct_option_id` alongside the existing `correct_option` index.

### Fixed
- `shuffle_options` was validated and passed to the client but never implemented anywhere in the plugin, while the scorer expects an index into the *stored* order — so a client that shuffled on its own and submitted the displayed index scored silently wrong. The server now shuffles the options it sends when the flag is on, and every option carries the `id` to answer with.
- Uninstall now drops the course completion snapshot table (`{prefix}lms_completion_snapshots`) as well; it was left behind.

### Changed
- `GET /lms/v1/lessons/{id}` option objects now contain `id` in addition to `text`. Index-based answers from 1.8.0 clients keep working against the stored order.

## [1.8.0] - 2026-09-14

### Added
- Lesson quizzes stored as one `_lw_lms_quiz` JSON document per lesson — no new post types or tables. Three question types: `single` (exactly one correct option), `boolean` (true/false) and `open` (free text with an optional `sample`, never scored). Per-lesson `pass_percentage`, falling back to the new "Default Pass Percentage" setting (80). The quiz meta is deliberately not exposed through `/wp/v2`.
- `GET /lms/v1/lessons/{id}` includes a `quiz` object (or `null`) behind the existing lesson access gate. It never contains a `correct` key, and carries the user's `last_attempt` (`percentage`, `passed`, `submitted_at`, `passed_at`).
- `POST /lms/v1/lessons/{id}/quiz` (logged-in, same access gate) scores answers server-side (`single` → 0-based option index in the order `GET` returns, so a client that shuffles must submit the original index; `boolean` → `true`/`false`; `open` → any text) and returns `score`, `scored_questions`, `percentage`, `passed`, `pass_percentage` and per-question `results`. A wrong answer reveals `correct_option` (single) or `correct_answer` (boolean); open questions return `scored: false` plus their `sample`. Missing answers count as wrong; a quiz with only open questions passes at 100%.
- `lw_lms_quiz_submitted` action after every submission. Args: `lesson_id`, `user_id`, `percentage`, `passed` (4 args, callers must register with `$accepted_args = 4`).
- `lw_lms_quiz_passed` action on every passing submission. Args: `lesson_id`, `user_id`, `percentage` (3 args).
- "Graded Quizzes" setting (`require_quiz_pass`, off by default). When on, passing a lesson's quiz marks the lesson completed through `ProgressRepository::upsert()` (so `lw_lms_lesson_completed` / `lw_lms_course_completed` fire), and `POST /lms/v1/progress` refuses `status=completed` for that lesson with 403 `quiz_not_passed` until the learner has passed; a later failed retry does not re-lock it. `wp lw-lms force-complete` and LW Site Manager remain admin overrides. When off, quizzes are practice only and progress works as before.
- The last attempt per user and lesson is kept in user meta `_lw_lms_quiz_{lesson_id}` (no attempt history); uninstall removes it.
- WP-CLI: `wp lw-lms lesson set-quiz <lesson> --file=<file|->`, `wp lw-lms lesson get-quiz <lesson> [--format=json|table]`, `wp lw-lms lesson delete-quiz <lesson>`. `set-quiz` validates strictly — unknown keys, malformed or duplicate question ids and single-choice questions without exactly one correct option are rejected with the offending path — and replaces the whole quiz, so re-importing with stable question ids updates instead of duplicating. `get-quiz` JSON round-trips through `set-quiz` unchanged.

## [1.7.0] - 2026-09-14

### Added
- "Staff Access" setting (Settings → General, off by default): users with the `manage_lms` capability (administrators by default) get every course and lesson without buying or enrolling. It is a runtime bypass in `AccessChecker::has_course_access()` — no access row is written, `lw_lms_after_grant` does not fire (so no drip or welcome automation for staff), no free-course enrollment row is created, and turning it off leaves nothing to revoke. With the setting off, access resolution is unchanged.
- `lw_lms_admin_access_capability` filter — changes the capability the staff bypass checks (default `manage_lms`).
- `status` parameter on `GET /lms/v1/courses` (`publish` | `private` | `draft` | `any`, default `publish`). `private` requires `read_private_courses`; `draft` and `any` require `edit_courses`; otherwise the request is rejected with 401/403. Anonymous and default requests are unchanged.
- `GET /lms/v1/courses/{id}` and `GET /lms/v1/lessons/{id}` serve non-published (private, draft, pending, scheduled) courses and lessons to users with the matching capability (`read_private_*` / `edit_*`). Everyone else still gets a 404. Lesson content still goes through the normal access check.
- `status` field in the course list items and in the single-course REST payload.

## [1.6.3] - 2026-09-11

### Fixed
- Security: published lessons — paid ones included — were readable by anyone through the core `/wp/v2/lesson` REST routes (lesson body plus REST-registered meta such as the video URL), bypassing the course access check of `/lms/v1/lessons/{id}`. The core lesson routes now answer 401/403 to users who cannot edit lessons. The block editor (editors and administrators) and the LMS API are unaffected; core `/wp/v2/course` stays public, as course content is the public course description.

## [1.6.2] - 2026-09-06

### Fixed
- The release package and the Composer/Packagist dist no longer ship tests, docs or development configuration (`.gitattributes` export-ignore plus unified release excludes). A hosting malware scanner had flagged a unit-test fixture on a customer site

## [1.6.1] - 2026-08-20

### Changed
- Tested up to WordPress 7.1.

## [1.6.0] - 2026-07-18

### Fixed
- The `lw_lms_has_course_access` filter is now reachable for paid courses. Previously the paid branch returned the legacy-purchase result before the filter line, so an integration could never grant (or deny) paid access through it. The built-in paid checks now short-circuit into a single filtered result.

### Added
- `AccessRepository::revoke_by_source( int $user_id, int $course_id, string $source, ?int $source_id = null )` — revokes only the access rows a given grant source owns, so an integration can cancel its own grant without trampling another source's access for the same course.

### Changed
- Minimum PHP is now 8.2.
- Added PHPStan level 5 static analysis and a PHPUnit test suite (with an access-control regression test) to CI.

## [1.5.1] - 2026-06-13

### Changed
- Maintenance release — internal housekeeping, no functional changes.

## [1.5.0] - 2026-06-13

### Added
- WooCommerce Memberships integration: a `paid` course can be unlocked by an active WooCommerce Membership. New `membership_plan_ids` course meta, selectable as a checkbox list in the Access Settings metabox. `AccessChecker` grants access when the user is an active member of any selected plan (live check via `wc_memberships_is_user_active_member`, after the subscription checks and before the legacy-purchase fallback). The denied `access` REST payload now includes a `memberships` array (plan `id`, `name`, and a `join` URL). No DB schema change; no-op when WooCommerce Memberships is inactive.

## [1.4.0] - 2026-05-13

### Added
- WP-CLI workflow (issue #10):
  - `wp lw-lms course create|list|delete|set-section`
  - `wp lw-lms lesson create|list|assign`
  - `wp lw-lms enroll <user> <course> [--source=…] [--expires-at=…]`
  - `wp lw-lms revoke <user> <course>`
  - `wp lw-lms force-complete <user> <course>`
  - User refs accept ID, login, or email; course/lesson refs accept ID or slug. Each subcommand lives in its own `CLI/*Command.php` file (max 200 lines).
- `lw_lms_settings_tabs` filter — third-party plugins can append, remove, or reorder admin Settings tabs by returning a modified list of `TabInterface` instances. Issue #12.
- `SettingsPage::get_settings_group()` — exposes the settings group identifier so companion plugins can `register_setting()` against the same group and save options through the existing form (single nonce, single submit).

### Fixed
- Course `content` is no longer gated behind `has_access` in the REST `transform_full` response. The course `post_content` is the public marketing/about description; per-lesson content stays gated by the lesson `accessible` flag and the lessons REST endpoint. `content_raw` (unfiltered source) remains editor-only. Issue #13.
- `AccessChecker::has_lesson_access()` now short-circuits on `ACCESS_OPEN` before consulting the preview-lesson branch, so open-course lessons remain accessible to guests even when marked as preview. Previously, preview lessons on open courses wrongly required login. Issue #11.

## [1.3.0] - 2026-05-06

### Added
- `lw_lms_after_grant` action — fires after access is granted (issue #9). Args: `user_id`, `course_id`, `source`, `source_id`, `expires_at` (5 args, callers must register with `$accepted_args = 5`).
- `lw_lms_after_revoke` action — fires only when an active row is actually flipped to revoked. Args: `user_id`, `course_id`, `source` (3 args).
- `lw_lms_pre_grant` filter — return false to abort a grant before any DB work. Args: `$allow`, `user_id`, `course_id`, `source`, `source_id`, `expires_at` (6 args).
- Free-course implicit enrollment. First time a logged-in user accesses a free course, a `source='free'` access row is inserted (idempotent), so `lw_lms_after_grant` fires for free enrollments and downstream automation (drip / welcome email / cohort analytics) can listen to a single grant signal.
- `AccessQueries::has_active_access( user_id, course_id, source = null )` — optional `$source` argument for source-specific active-access checks (`'free'`, `'manual'`, `'woocommerce'`, `'subscription'`).
- `ProgressRepository::mark_course_completed( user_id, course_id )` — enumerates published lessons assigned to the course and upserts each as completed. The final upsert naturally fires `lw_lms_lesson_completed` and `lw_lms_course_completed`.

### Changed
- `lw_lms_lesson_completed` and `lw_lms_course_completed` are now fired centrally by `ProgressRepository::upsert()` and `CompletionTracker::maybe_record()` respectively, instead of by individual REST endpoints. Existing 2-arg signatures (`lesson_id, user_id` and `course_id, user_id`) are preserved.
- `AccessRepository` split into `AccessRepository` (writes: `grant`, `revoke`) and `AccessQueries` (reads: `has_active_access`, `get_user_access`, `get_user_enrollments`). Each class stays within the 200-line limit. Direct callers of the read methods on `AccessRepository` should migrate to `AccessQueries`.
- `ProgressRepository` split into `ProgressRepository` (writes: `upsert`, `delete`, `mark_course_completed`) and `ProgressQueries` (reads: `get`, `get_course_progress`, `get_user_progress`, `get_completed_lessons`). Direct callers of the read methods on `ProgressRepository` should migrate to `ProgressQueries`.

## [1.2.16] - 2026-04-30

### Added
- Standalone Abilities API support. `Integration` now registers a fallback on the official `wp_abilities_api_categories_init` and `wp_abilities_api_init` hooks (priority 20, gated by `did_action()` so the Site Manager bridge wins when active). Abilities are usable with only the WordPress 6.9+ Abilities API or the feature plugin — no Site Manager required.
- Detailed `output_schema` definitions for every ability (`OutputSchemas`): course summary fragment, full course with sections + lessons, per-lesson progress map with status enum, options. Lets AI agents introspect the exact response shape instead of guessing from `'type' => 'array'`.
- `list-courses` response includes `total_pages`, `page`, and `per_page` alongside `total`.
- `AbilityPermissions` factory: prefers Site Manager's `PermissionManager` when injected (via the `lw_site_manager_register_abilities` bridge), falls back to a `current_user_can()` map (`can_edit_posts` → `edit_posts`, `can_manage_options` → `manage_options`, `can_edit_users` → `edit_users`) when registering directly.

### Changed
- SiteManager folder restructured into focused `Abilities/`, `Service/`, and `Schema/` namespaces. `LmsAbilities` and `LmsService` split per concern: course / progress / options.
- `set-progress` description explicitly notes the destructive aspect: reverting from completed loses the completion timestamp.

## [1.2.15] - 2026-04-30

### Added
- Variation-level WooCommerce Subscriptions access (issue #8). New `subscription_variation_ids` course meta accepts `parent_id:variation_id` pairs, so a course can be tied to specific variations of a variable-subscription product (e.g. only the "Yearly" variation grants access, not "Monthly"). Implemented in the new `SubscriptionVariationChecker` (runtime check via `wcs_get_users_subscriptions()` against `active` subscriptions; matches on the variation ID line item, not the parent). Existing parent-level `subscription_ids` behaviour is unchanged and still evaluated first.
- New `Subscription Variations` field in the Course Access metabox — textarea, one `parent_id:variation_id` per line.
- `AccessChecker::get_access_info()` now includes a `subscription_variations` array for paid courses without access (parent_id, variation_id, name, attributes, price, url) so REST clients can render variation-specific upsells.

### Changed
- `AccessMetaboxRenderer` converted from trait to a static helper class (was 78 lines as a trait, would have exceeded the 80-line trait limit with the new render method).

## [1.2.14] - 2026-04-27

### Added
- **Lock-on-complete progress snapshot** (issue #7). When a user first reaches 100% in a course, the lesson count is captured and frozen for that user × course pair. Adding a lesson to the course later no longer demotes completed users from 100% — they stay at 100%, and the new lesson is "extra material". Users still in progress see the current (larger) total and a freshly-recalculated percentage.
  - New `wp_lms_completion_snapshots` table (`user_id`, `course_id`, `total_lessons`, `completed_at`, UNIQUE on `user_id, course_id`).
  - New `ProgressSnapshotTable`, `ProgressSnapshotRepository`, `CompletionTracker`, `ProgressSnapshotMigration` classes.
  - `ProgressRepository::upsert()` now triggers `CompletionTracker::maybe_record()` after every status change, so the snapshot is written exactly once at the moment of completion.
  - Activation migration (`ProgressSnapshotMigration::backfill()`) retroactively writes a snapshot for every user × course pair already at 100% in `wp_lms_progress`. Idempotent — safe to call on every activation.
- DB version bumped to `1.2.0` to trigger the migration on update.

### Changed
- `ProgressCalculator::calculate()` now resolves the total via the snapshot when one exists, otherwise falls back to the current course size.
- Percentage is now clamped to 100% defensively.

## [1.2.13] - 2026-04-27

### Fixed
- Course builder drag&drop now persists `lesson_section_id` and `lesson_order` for every lesson on save (issue #3). Previously only the `course_sections` array was written, leaving the lessons' meta untouched, which caused two bugs: (a) the frontend / REST response went out of sync with the editor, and (b) a "drag → drag back" gesture left orphan state because the second move never triggered a re-save. The new `LessonAssignmentSaver` reads the JSON payload emitted by the JS on every change (and on init) and updates the metas in one pass, defensively skipping lessons not actually attached to the course being saved.

### Added
- All Lessons admin list now has two new columns (issue #5):
  - **Order** — shows `lesson_order`, sortable
  - **Course** — clickable link to the parent course's editor

### Changed
- `CourseContentMetabox` was split into a coordinator + `CourseContentRenderer` + `LessonAssignmentSaver` to keep each file under 200 lines

### Docs
- `ProgressCalculator` class doc now explicitly states the known limitation that adding a lesson to a course mid-progress does not retroactively recalculate users' completion percentages (issue #7)

## [1.2.12] - 2026-03-22

### Added
- LW Site Manager integration - LMS abilities for AI agents
- `lw-lms/list-courses` ability - list courses
- `lw-lms/get-course` ability - get course details with lessons
- `lw-lms/get-progress` ability - get user progress
- `lw-lms/set-progress` ability - update lesson completion
- `lw-lms/get-options` ability - get LMS settings

### Fixed
- `list-courses` input schema now accepts empty requests

## [1.2.11]

### Fixed
- Smarter autoloader fallback - supports root Composer dependency installs

## [1.2.10]

### Fixed
- Graceful error when autoloader is missing (admin notice instead of fatal error)

## [1.2.9]

### Fixed
- Minor fix

## [1.2.8]

### Added
- Hash-based tab navigation on settings page
- New chalkboard-user icon
- Updated ParentPage with SVG icon support from registry
- Suppressed expected PHPCS warnings for custom capabilities and meta queries

## [1.2.7]

### Fixed
- Admin notice isolation for notices relocated by WordPress core JS

## [1.2.6]

### Changed
- Isolate third-party admin notices on LW plugin pages

## [1.2.5]

### Added
- Fresh POT file and Hungarian (hu_HU) translation

## [1.2.4]

### Added
- Central plugin registry from GitHub JSON

## [1.2.3]

### Added
- WP-CLI LearnDash migration command (`wp lw-lms migrate-learndash`)
- Support for `--dry-run` and `--verbose` flags
- Migrates courses, lessons, sections, and lesson order

## [1.2.2]

### Added
- Instructor field to Course Data metabox
- Instructor meta registered with REST API support

## [1.2.1]

### Changed
- Improved Add Enrollment form with explicit Course and Expires labels

## [1.2.0]

### Added
- Manual course enrollment on user profile pages (wp-admin)
- Enrollment table with course name, source, granted date, and expiry
- Course grant/revoke actions with nonce and capability protection
- `AccessRepository::get_user_enrollments()` method

## [1.1.1]

### Fixed
- Auto-create access table on plugin update (not just activation)

## [1.1.0]

### Added
- Time-limited course access (per-product duration)
- `wp_lms_access` database table for fast access lookups
- AccessGranter - automatic access on WooCommerce order completion
- `product_id:days` format in Course Access metabox
- `expires_at` to REST API access info
- `access_duration` to product info in REST API
- Backward-compatible fallback for legacy purchases

### Changed
- DB version updated to 1.1.0

## [1.0.0]

### Added
- Initial release
- Course and Lesson custom post types
- Course sections and lesson ordering
- Access control (open, free, paid)
- WooCommerce integration
- Progress tracking
- REST API
- Video support (YouTube, Vimeo, Wistia, self-hosted)
- Attachments and downloads
