# LW LMS - Site Manager Abilities

LW LMS registers abilities with [LW Site Manager](https://github.com/lwplugins/lw-site-manager) when both plugins are active. These abilities allow AI agents and REST API clients to read and update LMS data.

## Category

`lms` - Learning management system abilities

## Abilities

### `lw-lms/list-courses`

**Type:** readonly
**Permission:** `can_edit_posts`

List all published courses with basic metadata.

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
      "url": "https://example.com/course/intro-to-php",
      "access_type": "free",
      "duration": "2h",
      "instructor": "Jane Doe"
    }
  ],
  "total": 5
}
```

---

### `lw-lms/get-course`

**Type:** readonly
**Permission:** `can_edit_posts`

Get full course details including lessons and sections.

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
    "url": "https://example.com/course/intro-to-php",
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
**Permission:** `can_edit_posts`

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
**Permission:** `can_edit_posts`

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
    "show_progress_bar": true,
    "enable_preview_lessons": true,
    "default_access_type": "free",
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
| `not_found` | 404 | Course or user not found |
| `lesson_not_found` | 404 | Lesson not found or wrong post type |
| `save_failed` | 500 | Database write failed |
