=== LW LMS ===
Contributors: lwplugins
Tags: lms, courses, lessons, learning, education
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.3.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight LMS plugin for WordPress - courses, lessons, and progress tracking without the bloat.

== Description ==

LW LMS provides a simple yet powerful learning management system for WordPress. No upsells, no tracking, just clean and efficient course management.

= Features =

**Course Management**

* Custom post type for courses with Gutenberg support
* Course sections for organizing lessons
* Multiple access types: open, free (login required), paid (WooCommerce)
* Course attachments and downloads
* Course categories, tags, and difficulty levels

**Lesson Management**

* Custom post type for lessons with Gutenberg support
* Video support (YouTube, Vimeo, Wistia, self-hosted)
* Lesson attachments and downloads
* Automatic video provider detection

**Progress Tracking**

* Track user progress through courses
* Mark lessons as completed
* Course completion percentage
* Per-user progress storage

**WooCommerce Integration**

* Link courses to WooCommerce products
* WooCommerce Subscriptions support
* Automatic access control based on purchases

**REST API**

* Full REST API for headless implementations
* Course and lesson endpoints
* Progress endpoints
* Protected download endpoints

**Admin Interface**

* Intuitive course content builder
* Drag-and-drop lesson ordering
* Section management
* Access control settings

= Requirements =

* PHP 8.1 or higher
* WordPress 6.0 or higher
* WooCommerce (optional, for paid courses)
* WooCommerce Subscriptions (optional, for subscription-based access)

== Installation ==

1. Upload the `lw-lms` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu
3. Go to **LW Plugins → LMS** to configure

Or install via Composer:

`composer require lwplugins/lw-lms`

== Frequently Asked Questions ==

= Do I need WooCommerce? =

No, WooCommerce is only required if you want to sell courses. Open and free courses work without WooCommerce.

= Can I use this with a headless frontend? =

Yes, LW LMS provides a full REST API at `/wp-json/lms/v1/` for headless implementations.

= How do I create a course? =

Go to **Courses → Add New** in your WordPress admin. Use the Gutenberg editor for course content, then use the metaboxes to add sections, lessons, and configure access settings.

= How do I track user progress? =

User progress is automatically tracked when users complete lessons via the REST API. You can view progress in the admin or query it via the API.

== Screenshots ==

1. Course editor with sections and lessons
2. Lesson editor with video support
3. Settings page
4. REST API response example

== Changelog ==

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
