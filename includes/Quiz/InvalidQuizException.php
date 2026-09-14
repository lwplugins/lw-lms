<?php
/**
 * Invalid quiz document exception.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Quiz;

/**
 * Thrown by QuizNormalizer when a quiz document fails validation. The message
 * names the offending path (e.g. "quiz.questions[2].id") in plain text.
 */
final class InvalidQuizException extends \InvalidArgumentException {
}
