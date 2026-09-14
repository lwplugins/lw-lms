<?php
/**
 * Quiz results admin page.
 *
 * @package LightweightPlugins\LMS
 */

declare(strict_types=1);

namespace LightweightPlugins\LMS\Admin;

use LightweightPlugins\LMS\Admin\Quiz\QuizResultsRenderer;
use LightweightPlugins\LMS\PostTypes\Lesson;
use LightweightPlugins\LMS\Quiz\QuizAttemptQueries;

/**
 * "Quiz Results" under the LW Plugins menu: who attempted a lesson's quiz,
 * how often, with what result — and which question everyone gets wrong.
 */
final class QuizResultsPage {

	/**
	 * Page slug.
	 */
	public const SLUG = 'lw-lms-quiz-results';

	/**
	 * Learners per page.
	 */
	private const PER_PAGE = 20;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
	}

	/**
	 * Register the submenu page.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		ParentPage::maybe_register();

		add_submenu_page(
			ParentPage::SLUG,
			__( 'Quiz Results', 'lw-lms' ),
			__( 'Quiz Results', 'lw-lms' ),
			'manage_lms',
			self::SLUG,
			[ $this, 'render' ]
		);
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_lms' ) ) {
			return;
		}

		// Read-only listing: the lesson filter and pager are not state changes,
		// so there is no nonce to verify here.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$lessons   = self::lessons_with_attempts();
		$lesson_id = isset( $_GET['lesson_id'] ) ? absint( $_GET['lesson_id'] ) : (int) ( array_key_first( $lessons ) ?? 0 );
		$paged     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! isset( $lessons[ $lesson_id ] ) ) {
			$lesson_id = (int) ( array_key_first( $lessons ) ?? 0 );
		}

		echo '<div class="wrap">';
		printf( '<h1>%s</h1>', esc_html__( 'Quiz Results', 'lw-lms' ) );

		if ( [] === $lessons ) {
			printf(
				'<p>%s</p></div>',
				esc_html__( 'No quiz has been submitted yet.', 'lw-lms' )
			);
			return;
		}

		QuizResultsRenderer::render_filter( $lessons, $lesson_id, self::SLUG );

		QuizResultsRenderer::render_summary(
			QuizAttemptQueries::count_for_lesson( $lesson_id ),
			QuizAttemptQueries::count_learners( $lesson_id )
		);

		QuizResultsRenderer::render_learners(
			QuizAttemptQueries::learners( $lesson_id, self::PER_PAGE, ( $paged - 1 ) * self::PER_PAGE )
		);

		QuizResultsRenderer::render_pager(
			QuizAttemptQueries::count_learners( $lesson_id ),
			self::PER_PAGE,
			$paged
		);

		QuizResultsRenderer::render_question_stats( QuizAttemptQueries::question_stats( $lesson_id ) );

		echo '</div>';
	}

	/**
	 * Lessons that have at least one attempt, titled and sorted.
	 *
	 * @return array<int, string>
	 */
	private static function lessons_with_attempts(): array {
		$lessons = [];

		foreach ( QuizAttemptQueries::lesson_ids() as $lesson_id ) {
			$post = get_post( $lesson_id );

			if ( ! $post || Lesson::POST_TYPE !== $post->post_type ) {
				continue;
			}

			$lessons[ $lesson_id ] = $post->post_title;
		}

		asort( $lessons );

		return $lessons;
	}
}
