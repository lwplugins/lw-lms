# LW LMS

> **!! This plugin is under active development and is not recommended for production use. !!**

Lightweight LMS plugin for WordPress - courses, lessons, and progress tracking without the bloat.

![LW LMS Settings](.github/screenshot.png)

## Requirements

- PHP 8.0+
- WordPress 6.6+
- WooCommerce (optional, for paid courses)
- WooCommerce Subscriptions (optional, for subscription-based access)

## Features

- **Courses & Lessons** - Custom post types with Gutenberg support
- **Sections** - Organize lessons into sections with drag-and-drop ordering
- **Access Control** - Open, free (login required), or paid (WooCommerce)
- **Progress Tracking** - Per-user lesson completion and course progress percentage
- **Video Support** - YouTube, Vimeo, Wistia, self-hosted (auto-detect)
- **Attachments** - File downloads for courses and lessons
- **REST API** - Full API at `/wp-json/lms/v1/` for headless implementations
- **WooCommerce** - Link courses to products and subscriptions

## Installation

```bash
composer require lwplugins/lw-lms
```

Or upload the `lw-lms` folder to `/wp-content/plugins/` and activate.

## REST API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/lms/v1/courses` | List courses (`status`: `publish` (default), `private`, `draft`, `any`) |
| GET | `/lms/v1/courses/{id}` | Get single course |
| GET | `/lms/v1/lessons/{id}` | Get single lesson (includes `quiz`, without answers) |
| POST | `/lms/v1/lessons/{id}/quiz` | Submit quiz answers (scored server-side) |
| POST | `/lms/v1/progress` | Update lesson progress |
| GET | `/lms/v1/progress` | Get user progress |
| GET | `/lms/v1/download/{id}` | Download attachment (use the signed `download_url` from the payloads) |

Free courses: a logged-in user is enrolled (a `free` access row, which fires `lw_lms_after_grant` and starts the drip clock) the first time they open the course (`GET /courses/{id}`) or one of its lessons. Listing courses (`GET /courses`) never enrolls anyone.

### Downloads

Every attachment in a course or lesson payload carries a `download_url`. Use it as is, for example as a plain `<a href>`: it is a signed link (`lw_user`, `lw_expires`, `lw_signature` query arguments) issued to the user who fetched the payload, so it works without a REST nonce and without cookies. It is valid for one hour; change that with the `lw_lms_download_link_ttl` filter (seconds). An expired or altered link answers 403 `download_link_expired`, so fetch the course or lesson again for a fresh one.

The access check still runs when the file is requested, for the user the link was issued to: revoking access also stops links already handed out. A request without a signature is checked for the REST-authenticated user (cookie + `X-WP-Nonce`, application password).

Only files listed as attachments of a course or lesson are served, and only when that course or lesson is visible to the user and the user has access to it. Anything else answers 404.

### Extending the payloads

Companion plugins can add their own keys to the course and lesson responses:

| Filter | Arguments | Response |
|--------|-----------|----------|
| `lw_lms_rest_course_list_item` | `array $data, WP_Post $post, int $user_id` | each item of `GET /courses` |
| `lw_lms_rest_course` | `array $data, WP_Post $post, int $user_id, bool $has_access` | `GET /courses/{id}` |
| `lw_lms_rest_lesson` | `array $data, WP_Post $post, int $user_id` | `GET /lessons/{id}` (after the access check) |

Only new top-level keys are kept. The keys core writes (`access`, `accessible`, `quiz`, `progress`, …) stay as core wrote them — overriding or removing them has no effect.

```php
add_filter(
    'lw_lms_rest_course',
    static function ( array $data, \WP_Post $post, int $user_id, bool $has_access ): array {
        if ( $user_id && \LightweightPlugins\LMS\Progress\ProgressCalculator::is_course_completed( $user_id, $post->ID ) ) {
            $data['certificate_url'] = rest_url( sprintf( 'my-certs/v1/courses/%d/certificate', $post->ID ) );
        }

        return $data;
    },
    10,
    4
);
```

### Lesson video markup

`GET /lms/v1/lessons/{id}` returns `video.html` next to the stored video fields: a ready, self-sizing 16:9 player (an iframe for YouTube, Vimeo and Wistia, a `<video>` element for self-hosted files). Insert it as is instead of building the iframe from `video.embed`:

```js
playerBox.innerHTML = lesson.video.html;
```

With [LW Cookie](https://github.com/lwplugins/lw-cookie) 1.7.1+ active and its content blocking on, a visitor who has not accepted the cookie category of the video host gets the player in LW Cookie's blocked form: LW Cookie's placeholder with a message and an "Accept & play video" button, and an iframe that has no `src` yet. The button grants that one category, then LW Cookie loads the video in place. Consent given from the banner loads it in place too. Nothing is requested from the video host before consent. Without LW Cookie, `video.html` is the plain player.

The `lw_lms_video_html` filter (`string $html, array $video`) lets other plugins change the markup.

## Drip & linear progression

A course runs with **free** progression by default: any lesson, any order. Switched to **linear**, lessons open one after the other, and each level can hold content back for a while:

| Level | Options |
|-------|---------|
| Course | opens *N* hours / days / weeks / months after enrollment |
| Section (module) | the same, or *N* after the previous section is completed |
| Lesson | the same, or *N* after the previous lesson is completed |

Schedules only apply in linear mode — with free progression there is nothing to pace. The order is the one the course builder shows: lessons outside a section first, then the sections by their order.

**Never held back:** a lesson the learner already completed, preview lessons, every lesson of a course they finished, users covered by Staff Access, and open courses (readable without logging in anyway). `wp lw-lms force-complete` and LW Site Manager remain admin overrides.

The clock each "after enrollment" delay counts from is stored per learner and course, set at their first grant whatever the source. Renewals and re-grants never restart it, and learners who enrolled before the course started dripping keep their real enrollment date.

```bash
wp lw-lms course set-drip 42 --progression=linear --delay=2 --unit=week
wp lw-lms lesson set-drip 108 --mode=previous --delay=3 --unit=day
wp lw-lms drip status alice 42      # per lesson: open/locked, why, and when it opens
wp lw-lms drip set-start alice 42 --date="2026-09-01 08:00:00"
```

In the REST payloads, `GET /courses/{id}` carries `progression`, and each lesson carries:

```json
{ "accessible": false, "locked_reason": "schedule", "available_at": "2026-09-21T10:00:00+02:00" }
```

`locked_reason` is `sequence` (the lesson before is unfinished, so the moment is not known yet), `schedule` (waiting for `available_at`) or `null`. A locked lesson answers 403 `lesson_locked` — with the same two fields in the error data — on `GET /lessons/{id}`, `POST /progress`, `POST /lessons/{id}/quiz` and `GET /download/{id}`.

The `lw_lms_lesson_locks` filter receives the locked lessons of a course for one learner (`array $locks, int $course_id, int $user_id`), so a companion plugin can open or hold back a lesson.

## Quizzes

One JSON document per lesson, managed with WP-CLI:

```bash
wp lw-lms lesson set-quiz <lesson> --file=quiz.json   # validate + replace ("-" = STDIN)
wp lw-lms lesson get-quiz <lesson> [--format=json|table]
wp lw-lms lesson delete-quiz <lesson>
```

```json
{
  "pass_percentage": 80,
  "shuffle_options": true,
  "questions": [
    { "id": "q_9e9b2eef93b3", "type": "single", "prompt": "…",
      "options": [ { "id": "opt_a", "text": "…", "correct": true }, { "text": "…" } ] },
    { "id": "q_e2f6a37486e7", "type": "boolean", "prompt": "…", "correct": true },
    { "id": "q_3ae2154b9400", "type": "open", "prompt": "…", "sample": "…" }
  ]
}
```

- Question ids are caller-supplied and stable (start with a letter; letters, digits, `_`, `-`). Unknown keys are rejected.
- An option may carry its own `id`. Recommended: without one it is addressed by position (`o0`, `o1`, …), so reordering options changes what a stored answer meant.
- `pass_percentage` and `shuffle_options` are optional; the default threshold is set under **LW Plugins → LMS → General → Quizzes**.
- Submit: `POST /lms/v1/lessons/{id}/quiz` with `{ "answers": { "<question id>": <option id | index | true/false | "text"> } }`. **Answer with the option id** — with `shuffle_options` on, the server sends the options in random order, and a positional index would mean something else.
- The response says per question only whether the answer was right (`"correct": true|false`); it never contains the right answer, and neither does `GET` (including `last_attempt.review`). The full record, right answers included, stays in the attempt table for the Quiz Results page.
- `last_attempt` in `GET /lessons/{id}` carries `percentage`, `passed`, `attempts`, `best_percentage` and `review` — the stored snapshot of the last attempt, so a reload can show what was answered.
- Submissions are throttled per learner and lesson: at least 15 seconds apart and at most 20 in any 24 hours. Beyond that the endpoint answers 429 `quiz_rate_limited` with `retry_after` (seconds) in the error data. Change the limits with the `lw_lms_quiz_attempt_cooldown` and `lw_lms_quiz_daily_attempt_limit` filters (`int $value, int $user_id, int $lesson_id`; 0 turns a limit off).
- Hooks: `lw_lms_quiz_submitted( $lesson_id, $user_id, $percentage, $passed )`, `lw_lms_quiz_passed( $lesson_id, $user_id, $percentage )`.

Editors get a validated JSON editor on the lesson screen and a **LW Plugins → Quiz Results** page (per-learner attempts and per-question statistics). Every submission is stored in `{prefix}lms_quiz_attempts` with its answer snapshot.

## Capabilities

Courses and lessons use the regular WordPress post capabilities (`capability_type` `post`), in wp-admin and in the APIs alike:

| Who | Can |
|-----|-----|
| Anyone who can edit a course or lesson (`edit_post` on it) | edit it, its LMS settings through core REST, and read it through `/wp/v2/lesson/{id}` |
| `edit_posts` / `read_private_posts` | list draft / private courses and lessons through `GET /lms/v1/courses?status=…` |
| `edit_others_posts` | list lessons through `/wp/v2/lesson` |
| `manage_lms` (administrators) | quiz results, enrollments on user profiles, the `lw-lms/get-progress` and `lw-lms/set-progress` abilities, staff access to every course (when enabled), every core lesson route |
| `manage_options` | the settings screen, the `lw-lms/get-options` ability |

`manage_lms` is the plugin's only custom capability.

## Development

```bash
composer install
composer phpcs     # Code style check
composer phpcbf    # Auto-fix
```

## License

GPL-2.0-or-later


## Sponsor

<a href="https://sinann.io/">
  <img src="https://sinann.io/favicon.svg" alt="Sinann" width="40">
</a>

Supported by [Sinann](https://sinann.io/)
