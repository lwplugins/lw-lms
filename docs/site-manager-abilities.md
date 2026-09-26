# LW LMS - Site Manager Abilities

LW LMS registers these abilities with the WordPress Abilities API. When [LW Site Manager](https://github.com/lwplugins/lw-site-manager) is active they are registered through it (and use its permission settings); without it they are registered directly with the Abilities API when that API is available. These abilities allow AI agents and REST API clients to read and update LMS data.

LW LMS is backend-only: the `url` fields below are the WordPress permalinks of the course posts, but the plugin registers no front-end pages for them.

## Category

`lms` - Learning management system abilities

## Abilities

### `lw-lms/list-courses`

**Type:** readonly
**Permission:** `can_edit_posts`

List published courses (ordered by title) with basic metadata.

**Input:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `per_page` | integer | no | Courses per page (default: 20) |
| `page` | integer | no | Page number (default: 1) |

**Output:**

```json
{
  "success": true,
  "courses": [
    {
      "id": 42,
      "title": "Intro to PHP",
      "status": "publish",
      "url": "https://example.com/?post_type=course&p=42",
      "access_type": "free",
      "duration": "2h",
      "instructor": "Jane Doe"
    }
  ],
  "total": 5,
  "total_pages": 1,
  "page": 1,
  "per_page": 20
}
```

---

### `lw-lms/get-course`

**Type:** readonly
**Permission:** `can_edit_posts`

Get full course details including lessons and sections. `lessons` lists the published lessons of the course, ordered by lesson order.

**Input:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `course_id` | integer | yes | Course post ID |

**Output:**

```json
{
  "success": true,
  "course": {
    "id": 42,
    "title": "Intro to PHP",
    "status": "publish",
    "url": "https://example.com/?post_type=course&p=42",
    "access_type": "free",
    "duration": "2h",
    "instructor": "Jane Doe",
    "content": "<p>Course description...</p>",
    "excerpt": "Learn PHP from scratch.",
    "sections": [
      { "id": "sec-1", "title": "Getting Started", "order": 1 }
    ],
    "lessons": [
      {
        "id": 55,
        "title": "Hello World",
        "section_id": "sec-1",
        "order": 1,
        "duration": "10m"
      }
    ]
  }
}
```

---

### `lw-lms/get-progress`

**Type:** readonly
**Permission:** `can_manage_lms` (the `manage_lms` or `manage_options` capability)

Get user progress for a course, including per-lesson completion status.

**Input:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `user_id` | integer | yes | WordPress user ID |
| `course_id` | integer | yes | Course post ID |

**Output:**

```json
{
  "success": true,
  "progress": {
    "user_id": 7,
    "course_id": 42,
    "percentage": 50,
    "completed_lessons": 2,
    "total_lessons": 4,
    "is_completed": false,
    "lessons": {
      "55": { "status": "completed", "completed_at": "2025-03-10 14:22:00" },
      "56": { "status": "completed", "completed_at": "2025-03-11 09:05:00" },
      "57": { "status": "in_progress", "completed_at": null }
    }
  }
}
```

---

### `lw-lms/set-progress`

**Type:** write
**Permission:** `can_manage_lms` (the `manage_lms` or `manage_options` capability). The lesson must belong to `course_id`, otherwise the ability answers `lesson_not_in_course` (400).

Update lesson completion status for a user. Upserts the progress record.

**Input:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `user_id` | integer | yes | WordPress user ID |
| `course_id` | integer | yes | Course post ID |
| `lesson_id` | integer | yes | Lesson post ID |
| `status` | string | yes | `completed`, `in_progress`, or `not_started` |

**Output:**

```json
{
  "success": true,
  "message": "Progress updated."
}
```

---

### `lw-lms/get-options`

**Type:** readonly
**Permission:** `can_manage_options`

Get global LW LMS plugin settings.

**Input:** none

**Output:**

```json
{
  "success": true,
  "options": {
    "courses_per_page": 10,
    "enable_preview_lessons": true,
    "default_access_type": "free",
    "auto_enroll_admins": false,
    "quiz_pass_percentage": 80,
    "require_quiz_pass": false,
    "woo_enabled": true,
    "delete_data_on_uninstall": false
  }
}
```

## Error Responses

All abilities return a `WP_Error` on failure. Common error codes:

| Code | Status | Description |
|------|--------|-------------|
| `missing_course_id` | 400 | `course_id` not provided |
| `missing_params` | 400 | Required fields missing |
| `invalid_status` | 400 | Status value not in allowed list |
| `lesson_not_in_course` | 400 | The lesson does not belong to `course_id` (`set-progress`) |
| `not_found` | 404 | Course not found |
| `course_not_found` | 404 | Course not found (in progress endpoints) |
| `user_not_found` | 404 | User not found |
| `lesson_not_found` | 404 | Lesson not found or wrong post type |
| `save_failed` | 500 | Database write failed |
