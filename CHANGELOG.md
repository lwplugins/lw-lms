# Changelog

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
- `ProgressCalculator` class doc now explicitly states the known limitation that adding a lesson to a course mid-progress does not retroactively recalculate users' completion percentages (issue #7); LearnDash has the same behaviour

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
