=== LW LMS ===
Contributors: lwplugins
Tags: lms, courses, lessons, headless, rest-api
Requires at least: 6.6
Tested up to: 7.1
Stable tag: 1.9.2
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Headless LMS backend for WordPress: courses, lessons, access, progress, quizzes, drip and a REST API. No frontend output; you build the learner UI.

== Description ==

**LW LMS is backend-only (headless).** It provides the data model, the wp-admin screens, access control, progress tracking, quizzes, drip scheduling, the WooCommerce integration and a REST API. It does **not** display anything on your site: there are no templates, no shortcodes, no blocks and no theme output, and courses and lessons have no public URLs. You build the learner-facing frontend yourself (in your theme, a separate app or a headless site) on top of the `lms/v1` REST API.

No upsells, no tracking.

= Features =

**Courses and lessons**

* Course and lesson post types, edited in the block editor (not public, no front-end URLs)
* Course sections (modules) and a drag-and-drop course builder for ordering lessons
* Access types: open (anyone, no login), free (login required) and paid (WooCommerce)
* Preview lessons that logged-in users can open without access to the course
* Course duration, instructor, featured image and excerpt; lesson duration
* Course categories, tags and difficulty levels
* File attachments on courses and lessons, picked from the Media Library
* Lesson video: YouTube, Vimeo, Wistia or a self-hosted file, with the provider detected from the URL

**Progress**

* Per-user lesson completion and course completion percentage
* Course completion is recorded once and stays at 100% when lessons are added later
* Learners mark lessons completed through the REST API; admins can override it with WP-CLI or the abilities

**Quizzes**

* One quiz per lesson, stored as a validated JSON document (single-choice, true/false and ungraded open questions)
* Edited in a JSON editor on the lesson screen or with WP-CLI
* Scored on the server; answers to learners never reveal the correct answer
* Optional rule: a lesson with a quiz can only be completed after its quiz is passed
* Every attempt is stored with its answers; submissions are throttled per learner and lesson

**Drip and linear progression**

* Free (any order) or linear progression per course
* In linear mode the course, a section or a lesson can open N hours, days, weeks or months after enrollment, or after the previous section or lesson is completed
* The REST payloads carry the lock reason and the unlock date

**WooCommerce**

* Access is granted when an order linked to the course is processing or completed, and taken back when the order is refunded, cancelled or failed
* Optional access duration per product (time-limited access)
* WooCommerce Subscriptions, per subscription product or per variation
* WooCommerce Memberships plans

**REST API (`/wp-json/lms/v1/`)**

* Courses (list with filters, single course with sections and lessons), lessons, progress, quiz submission and file downloads
* Signed, time-limited download links for course and lesson attachments
* Filters to add your own keys to the course and lesson payloads

**Admin**

* One LMS screen under LW Plugins: overview, enrollments with per-course progress (grant and revoke), quiz results (answers, per-question statistics, delete, CSV export) and settings
* Manual enrollment on user profiles
* A `manage_lms` capability for the learner sections

**Also included**

* WP-CLI commands for courses, lessons, sections, quizzes, drip, enrollments and completion
* A WP-CLI importer that copies LearnDash courses and lessons into LW LMS (`wp lw-lms migrate-learndash`)
* Abilities API integration (list and read courses, read and set progress, read settings), also used by LW Site Manager
* Personal data export and erase (Tools → Export / Erase Personal Data)
* LW Cookie (1.7.1+) integration: the lesson video player waits for cookie consent when LW Cookie blocks the video host. While LW Cookie blocks embeds, LW LMS loads one small script on the front end that makes the placeholder's "Accept & play video" button work; this is the only thing it adds to the front end

= About downloads =

Attachments are ordinary Media Library files. The REST API hands out signed download links (valid for one hour by default, see the `lw_lms_download_link_ttl` filter) and checks the learner's access when a file is requested through them. The files themselves stay in the uploads folder, so anyone who has a file's direct media URL can still open it. Do not rely on LW LMS alone for files that must stay private.

= Requirements =

* PHP 8.0 or higher
* WordPress 6.6 or higher
* A frontend of your own (theme, app or headless site) that uses the REST API to show courses to learners
* WooCommerce (optional, for paid courses)
* WooCommerce Subscriptions or WooCommerce Memberships (optional, for subscription- or membership-based access)

== Installation ==

1. Upload the `lw-lms` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu
3. Go to **LW Plugins → LMS** to configure

Or install via Composer:

`composer require lwplugins/lw-lms`

Then build or connect the frontend that shows courses to your learners, using the REST API at `/wp-json/lms/v1/`.

== Frequently Asked Questions ==

= Does it display courses on my site? =

No. LW LMS is backend-only: it adds no templates, shortcodes, blocks or theme output, and courses and lessons have no public pages. Your theme, a separate app or a headless site has to fetch courses, lessons and progress from the REST API at `/wp-json/lms/v1/` and render them. The one piece of markup it supplies is the ready video player in the lesson payload (`video.html`), for you to insert into your own page (plus, with LW Cookie blocking embeds, the small script behind that player's consent button).

= Do I need WooCommerce? =

No, WooCommerce is only required if you want to sell courses. Open and free courses work without WooCommerce.

= Can I use this with a headless frontend? =

Yes, that is how it is meant to be used. All learner-facing data is available from the REST API at `/wp-json/lms/v1/`.

= How do I create a course? =

Go to **Courses → Add New** in your WordPress admin. Write the course content in the block editor, then use the boxes below it to add sections and lessons, set the access type and configure drip.

= How do I track user progress? =

Your frontend marks lessons completed with `POST /wp-json/lms/v1/progress`; progress is not recorded by viewing a lesson in wp-admin. You can see each learner's progress per course under **LW Plugins → LMS → Enrollments**, or read it from the API.

= Are course files protected? =

Partly. Download links in the API payloads are signed, expire and check access on every request, but the files remain regular Media Library uploads and are reachable at their direct URL. See "About downloads" above.

= Can I import courses from LearnDash? =

Yes. `wp lw-lms migrate-learndash` copies LearnDash courses and lessons (with sections, lesson order, access type, linked WooCommerce products, featured images and lesson video URLs) into LW LMS. Run it with `--dry-run` first to preview. Items that were already imported are skipped.

== Screenshots ==

1. Course editor with sections and lessons
2. Lesson editor with video support
3. LMS screen: overview, enrollments, quiz results and settings
4. REST API response example

== Changelog ==

= 1.9.2 =
* Fix: Notices from themes and other plugins (for example a theme's purchase-code or recommended-plugins notice) could show on the LW LMS screen. They are now kept off every LW Plugins screen, whatever their markup

= 1.9.1 =
* Fix: With LW Cookie's content blocking on, a lesson video the visitor had not consented to showed as an empty black box, with no message and no way to accept the cookies it needs (issue #18). GET /lms/v1/lessons/{id} now returns `video.html`, a ready 16:9 player. While LW Cookie blocks the video host, the player comes as LW Cookie's own placeholder: "To watch this video, accept the required cookies." and an "Accept & play video" button (Hungarian translation included). The button grants just that cookie category and the video loads in place, without a page reload. Consent from the banner loads it too. Nothing is requested from the video host before consent. Frontends should insert `video.html` instead of building the iframe from `video.embed`
* New: `lw_lms_video_html` filter for the lesson video markup

= 1.9.0 =
* New: Drip and linear progression (issue #16). A course can run in linear mode, where lessons open one after the other, and where the course, a section (module) and a single lesson can each wait a number of hours, days, weeks or months — counted from enrollment, or from the completion of the previous lesson or section
* New: The drip clock starts at the learner's first grant, whatever the source (purchase, manual enrollment, free course), and a renewal or re-grant never restarts it. Learners enrolled before a course started dripping keep their real enrollment date
* New: GET /lms/v1/courses/{id} carries `progression`, and every lesson carries `locked_reason` and `available_at`, so a frontend can show the unlock date; the lesson, progress, quiz and download endpoints answer 403 `lesson_locked` with the same details
* New: "Progression & Drip" box on the course, a "Drip" box on the lesson, and a schedule per section in the course builder
* New: WP-CLI `wp lw-lms course set-drip`, `wp lw-lms lesson set-drip`, `wp lw-lms drip status` (why is a lesson still locked for this learner) and `wp lw-lms drip set-start`
* New: `lw_lms_lesson_locks` filter so a companion plugin can open or hold back a lesson
* Note: drip only applies in linear mode. Completed lessons, preview lessons, finished courses, Staff Access and open courses are never held back
* Fix: The course builder sanitizes the section list before storing it, keeping only the fields a section is made of

= 1.8.3 =
* Fix: Security — protected course and lesson attachments were downloadable by anyone through GET /lms/v1/download/{id}, without logging in or owning the course. The endpoint looks up which course or lesson a file belongs to, but searched the stored meta in the wrong shape, found nothing, and treated every file as unrelated to the LMS. Files attached to a course or lesson are now matched correctly and the access check runs. Attachments that belong to no course or lesson keep behaving as before

= 1.8.2 =
* New: lw_lms_rest_course_list_item (data, post, user_id), lw_lms_rest_course (data, post, user_id, has_access — 4 args) and lw_lms_rest_lesson (data, post, user_id) filters, so companion plugins can add their own keys to the lms/v1 course and lesson responses without a second request. Callbacks may only add keys: the keys core writes (access, accessible, quiz, progress, …) are kept as core wrote them, and a non-array return is ignored

= 1.8.1 =
* New: Every quiz submission is now stored in its own table (`{prefix}lms_quiz_attempts`) with the full answer snapshot, so attempt history survives and a result can be shown or defended later. Attempts recorded by 1.8.0 are migrated on update (without answers, which were never stored)
* New: The learner's last attempt in GET /lms/v1/lessons/{id} carries a `review` array, so a page reload can still show what was answered and what was right, plus `attempts` and `best_percentage`
* New: Quiz metabox on the lesson editor — readable listing of the stored quiz plus a validated JSON editor with the exact error path; a Quiz column on the All Lessons list
* New: "Quiz Results" page under LW Plugins — per learner attempts, best and last score, pass state, and per-question statistics showing which question everyone gets wrong
* New: Single-choice options may carry a stable `id`; answers can be submitted as an option id instead of an index, so reordering options no longer changes the meaning of stored answers
* Fix: `shuffle_options` was declared but never implemented, and a client that shuffled on its own silently scored wrong, because the server expects the stored order. The server now shuffles the options it sends, and each option carries the id to answer with
* Fix: Uninstall now also drops the course completion snapshot table

= 1.8.0 =
* New: Lesson quizzes stored as one JSON meta per lesson (no new post types or tables) with single-choice, true/false and open (unscored) questions, a per-lesson pass percentage and a global default (80)
* New: GET /lms/v1/lessons/{id} includes the quiz without correct answers, plus the user's last attempt, behind the existing lesson access gate
* New: POST /lms/v1/lessons/{id}/quiz scores answers server-side and reveals the correct answer only for wrong answers
* New: lw_lms_quiz_submitted (lesson_id, user_id, percentage, passed — 4 args) and lw_lms_quiz_passed (lesson_id, user_id, percentage — 3 args) actions
* New: "Graded Quizzes" setting (off by default): passing the quiz completes the lesson, and the lesson cannot be completed through the progress endpoint until the quiz is passed
* New: WP-CLI `wp lw-lms lesson set-quiz`, `get-quiz` and `delete-quiz`; set-quiz validates strictly and replaces the whole quiz, so re-imports with stable question ids update instead of duplicating

= 1.7.0 =
* New: "Staff Access" setting (Settings → General, off by default) gives users with the manage_lms capability access to every course and lesson without buying or enrolling. It is a runtime bypass, so no access row is written, lw_lms_after_grant does not fire and there is nothing to revoke when turned off
* New: lw_lms_admin_access_capability filter to change the capability the staff bypass checks (default manage_lms)
* New: status parameter on GET /lms/v1/courses (publish, private, draft, any; default publish). Non-published statuses require read_private_courses or edit_courses
* New: GET /lms/v1/courses/{id} and GET /lms/v1/lessons/{id} serve non-published courses and lessons to users with the matching capability; everyone else still gets a 404
* New: status field in course list items and the single-course REST payload

= 1.6.3 =
* Fix: Security — published lessons, paid ones included, were readable by anyone through the core /wp/v2/lesson REST routes, bypassing the LMS access check. Those routes are now limited to users who can edit lessons; the block editor and the LMS API are unaffected

= 1.6.2 =
* Fix: the release package and Composer dist no longer ship tests, docs or development configuration

= 1.6.1 =
* Update: Tested up to WordPress 7.1.

= 1.6.0 =
* Fix: The lw_lms_has_course_access filter now runs for paid courses (it was unreachable behind the built-in checks), so integrations can grant or deny paid access through it
* New: AccessRepository::revoke_by_source() lets an integration revoke only its own grant rows without trampling access granted by another source
* Update: Minimum PHP is now 8.2; added PHPStan level 5 and a PHPUnit test suite to CI

= 1.5.1 =
* Update: Maintenance release — internal housekeeping, no functional changes.

= 1.5.0 =
* New: WooCommerce Memberships integration — a paid course can be unlocked by an active membership. Select one or more membership plans in the course Access Settings; active members of any selected plan get access. Live check (no stored access row), gracefully no-op when WooCommerce Memberships is inactive.

= 1.4.0 =
* New: WP-CLI workflow — `wp lw-lms course create|list|delete|set-section`, `wp lw-lms lesson create|list|assign`, `wp lw-lms enroll`, `wp lw-lms revoke`, `wp lw-lms force-complete`. Each command resolves user/course/lesson refs by ID, slug, login, or email. Issue #10.
* New: `lw_lms_settings_tabs` filter — third-party plugins can append, remove, or reorder admin Settings tabs by returning a modified list of `TabInterface` instances. `SettingsPage::get_settings_group()` is also public, so companion plugins can `register_setting()` against the same group and save options through the existing form. Issue #12.
* Fix: Course `content` is now always public in the REST `transform_full` response, matching the marketing/about-page intent of a course's `post_content`. Lesson content stays gated by the per-lesson `accessible` flag and its REST endpoint. `content_raw` (unfiltered source) remains editor-only. Issue #13.
* Fix: Open-course lessons are accessible even when marked as preview. `AccessChecker::has_lesson_access()` now short-circuits on `ACCESS_OPEN` before consulting the preview branch, which previously wrongly required login. Issue #11.

= 1.3.0 =
* New: `lw_lms_after_grant` action — fires after access is granted (issue #9). 5 args: user_id, course_id, source, source_id, expires_at.
* New: `lw_lms_after_revoke` action — fires only when an active row is actually flipped to revoked. 3 args: user_id, course_id, source.
* New: `lw_lms_pre_grant` filter — return false to abort a grant before any DB work. 6 args: allow, user_id, course_id, source, source_id, expires_at.
* New: Free-course implicit enrollment. First time a logged-in user accesses a free course, a `source='free'` access row is inserted (idempotent), so `lw_lms_after_grant` fires for free enrollments and downstream automation (drip / welcome email / cohort analytics) can listen to a single grant signal.
* New: `AccessQueries::has_active_access( user_id, course_id, source = null )` — optional `$source` argument for source-specific access checks.
* New: `ProgressRepository::mark_course_completed( user_id, course_id )` — programmatically marks every published lesson in a course completed. The last upsert naturally fires `lw_lms_lesson_completed` and `lw_lms_course_completed`.
* Change: `lw_lms_lesson_completed` and `lw_lms_course_completed` are now fired centrally by `ProgressRepository::upsert()` and `CompletionTracker::maybe_record()` respectively, instead of by individual REST endpoints. Existing 2-arg signatures (`lesson_id, user_id` and `course_id, user_id`) are preserved.
* Change: `AccessRepository` split into `AccessRepository` (writes: grant/revoke) and `AccessQueries` (reads: has_active_access, get_user_access, get_user_enrollments). Keeps each class within the 200-line limit. Direct callers of the read methods on `AccessRepository` should migrate to `AccessQueries`.
* Change: `ProgressRepository` split into `ProgressRepository` (writes: upsert/delete/mark_course_completed) and `ProgressQueries` (reads: get/get_course_progress/get_user_progress/get_completed_lessons). Direct callers of the read methods on `ProgressRepository` should migrate to `ProgressQueries`.

= 1.2.16 =
* New: Standalone Abilities API support — abilities now register directly on `wp_abilities_api_categories_init` / `wp_abilities_api_init` (priority 20) when LW Site Manager is not active. Previously abilities required Site Manager to be installed.
* New: Detailed `output_schema` for every ability (course summary, full course, lesson, progress per-lesson, etc.) so AI agents can introspect the response shape.
* New: `list-courses` response now includes `total_pages`, `page`, and `per_page` for pagination consumers.
* Change: SiteManager folder restructured — `LmsAbilities` and `LmsService` split into focused `Abilities/`, `Service/`, and `Schema/` namespaces.
* Change: `set-progress` description now explicitly notes that reverting from completed loses the completion timestamp.

= 1.2.15 =
* New: Variation-level WooCommerce Subscriptions access (issue #8). New `subscription_variation_ids` course meta lets you tie access to specific variations of a variable subscription (e.g. only the "Yearly" variation grants access, not "Monthly"). Existing `subscription_ids` (parent-level) behaviour is unchanged and still works alongside.
* New: `Subscription Variations` field in the Course Access metabox — one `parent_id:variation_id` per line.
* New: REST course access info now includes `subscription_variations` for paid courses without access.

= 1.2.14 =
* New: Lock-on-complete progress snapshot — once a user reaches 100% in a course, the lesson count is frozen so adding a new lesson later does not knock them below 100% (issue #7). New `wp_lms_completion_snapshots` table; activation migration backfills existing completed users.
* New: Course completion is detected automatically inside `ProgressRepository::upsert()` via the new `CompletionTracker` (no extra hook needed at the call sites).
* Fix: `percentage` is now clamped to 100% defensively so a stale snapshot can never report >100%.

= 1.2.13 =
* Fix: Course builder drag&drop now persists `lesson_section_id` and `lesson_order` for every lesson on save (issue #3) — eliminates the editor/REST sort-order mismatch and the cross-save orphan bug after a drag-and-drop-back gesture
* New: All Lessons admin list now has Order (sortable) and Course (linkable) columns (issue #5)
* Docs: ProgressCalculator now documents the known append-only progress recalculation limitation (issue #7)

= 1.2.12 =
* New: LW Site Manager integration - LMS abilities for AI agents
* New: lw-lms/list-courses - list courses
* New: lw-lms/get-course - get course details with lessons
* New: lw-lms/get-progress - get user progress
* New: lw-lms/set-progress - update lesson completion
* New: lw-lms/get-options - get LMS settings
* Fix: list-courses input schema accepts empty requests

= 1.2.11 =
* Fix: Smarter autoloader fallback - supports root Composer dependency installs

= 1.2.10 =
* Fix: Graceful error when autoloader is missing (admin notice instead of fatal error)

= 1.2.9 =
* Minor fix

= 1.2.8 =
* Hash-based tab navigation on settings page
* New chalkboard-user icon
* Updated ParentPage with SVG icon support from registry
* Suppressed expected PHPCS warnings for custom capabilities and meta queries

= 1.2.7 =
* Fix admin notice isolation for notices relocated by WordPress core JS

= 1.2.6 =
* Isolate third-party admin notices on LW plugin pages

= 1.2.5 =
* Add fresh POT file and Hungarian (hu_HU) translation

= 1.2.4 =
* New: Central plugin registry from GitHub JSON

= 1.2.3 =
* Add WP-CLI LearnDash migration command (wp lw-lms migrate-learndash)
* Supports --dry-run and --verbose flags
* Migrates courses, lessons, sections, and lesson order

= 1.2.2 =
* Add Instructor field to Course Data metabox
* Register instructor meta with REST API support

= 1.2.1 =
* Improve Add Enrollment form with explicit Course and Expires labels

= 1.2.0 =
* Add manual course enrollment on user profile pages (wp-admin)
* Add enrollment table with course name, source, granted date, and expiry
* Add course grant/revoke actions with nonce and capability protection
* Add AccessRepository::get_user_enrollments() method

= 1.1.1 =
* Fix: auto-create access table on plugin update (not just activation)

= 1.1.0 =
* Add time-limited course access (per-product duration)
* Add wp_lms_access database table for fast access lookups
* Add AccessGranter — automatic access on WooCommerce order completion
* Add product_id:days format in Course Access metabox
* Add expires_at to REST API access info
* Add access_duration to product info in REST API
* Add backward-compatible fallback for legacy purchases
* Update DB version to 1.1.0

= 1.0.0 =
* Initial release
* Course and Lesson custom post types
* Course sections and lesson ordering
* Access control (open, free, paid)
* WooCommerce integration
* Progress tracking
* REST API
* Video support (YouTube, Vimeo, Wistia, self-hosted)
* Attachments and downloads

== Upgrade Notice ==

= 1.0.0 =
Initial release.
