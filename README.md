# LW LMS

> **!! This plugin is under active development and is not recommended for production use. !!**

Lightweight LMS plugin for WordPress - courses, lessons, and progress tracking without the bloat.

![LW LMS Settings](.github/screenshot.png)

## Requirements

- PHP 8.1+
- WordPress 6.0+
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
| GET | `/lms/v1/download/{id}` | Download attachment |

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
- The response reveals the right answer only for wrong answers (`correct_option_id`, `correct_option`, `correct_answer`); `GET` never contains one.
- `last_attempt` in `GET /lessons/{id}` carries `percentage`, `passed`, `attempts`, `best_percentage` and `review` — the stored snapshot of the last attempt, so a reload can show what was answered.
- Hooks: `lw_lms_quiz_submitted( $lesson_id, $user_id, $percentage, $passed )`, `lw_lms_quiz_passed( $lesson_id, $user_id, $percentage )`.

Editors get a validated JSON editor on the lesson screen and a **LW Plugins → Quiz Results** page (per-learner attempts and per-question statistics). Every submission is stored in `{prefix}lms_quiz_attempts` with its answer snapshot.

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
