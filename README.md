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
      "options": [ { "text": "…", "correct": true }, { "text": "…" } ] },
    { "id": "q_e2f6a37486e7", "type": "boolean", "prompt": "…", "correct": true },
    { "id": "q_3ae2154b9400", "type": "open", "prompt": "…", "sample": "…" }
  ]
}
```

- Question ids are caller-supplied and stable (start with a letter; letters, digits, `_`, `-`). Unknown keys are rejected.
- `pass_percentage` and `shuffle_options` are optional; the default threshold is set under **LW Plugins → LMS → General → Quizzes**.
- Submit: `POST /lms/v1/lessons/{id}/quiz` with `{ "answers": { "<id>": <option index | true/false | "text"> } }`. The option index is 0-based in the order `GET` returns — the server never shuffles, so a client that shuffles (`shuffle_options`) must submit the original index.
- Hooks: `lw_lms_quiz_submitted( $lesson_id, $user_id, $percentage, $passed )`, `lw_lms_quiz_passed( $lesson_id, $user_id, $percentage )`.

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
