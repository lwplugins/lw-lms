/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import ConfirmButton from '../../components/ConfirmButton';
import Section from '../../components/Section';

/**
 * One lesson's summary: attempts, learners, and how each question went (the
 * question everyone gets wrong is usually about the lesson, not the learner).
 *
 * @param {Object}     props
 * @param {Object}     props.summary  { attempts, learners, questions }.
 * @param {string}     props.lesson   Lesson title.
 * @param {() => void} props.onDelete Delete every attempt of the lesson.
 */
export default function QuestionStats( { summary, lesson, onDelete } ) {
	return (
		<Section
			title={ lesson }
			description={ sprintf(
				/* translators: 1: attempts (e.g. "12 attempts"), 2: learners (e.g. "5 learners"). */
				__( '%1$s by %2$s', 'lw-lms' ),
				sprintf(
					/* translators: %d: number of attempts. */
					_n(
						'%d attempt',
						'%d attempts',
						summary.attempts,
						'lw-lms'
					),
					summary.attempts
				),
				sprintf(
					/* translators: %d: number of learners. */
					_n(
						'%d learner',
						'%d learners',
						summary.learners,
						'lw-lms'
					),
					summary.learners
				)
			) }
			actions={
				<ConfirmButton
					__next40pxDefaultSize
					variant="secondary"
					isDestructive
					confirmText={ __( 'Delete attempts', 'lw-lms' ) }
					question={ __(
						'Delete every attempt of this lesson quiz? This cannot be undone. Lessons learners already completed stay completed.',
						'lw-lms'
					) }
					onConfirm={ onDelete }
				>
					{ __( 'Delete all attempts', 'lw-lms' ) }
				</ConfirmButton>
			}
		>
			{ summary.questions.length > 0 && (
				<div className="lw-lms-table-wrap">
					<table className="lw-lms-table is-cards">
						<thead>
							<tr>
								<th scope="col">
									{ __( 'Question', 'lw-lms' ) }
								</th>
								<th scope="col">
									{ __( 'Answered', 'lw-lms' ) }
								</th>
								<th scope="col">{ __( 'Right', 'lw-lms' ) }</th>
								<th scope="col">{ __( 'Wrong', 'lw-lms' ) }</th>
								<th scope="col">
									{ __( 'Right answers', 'lw-lms' ) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ summary.questions.map( ( question ) => (
								<tr key={ question.id }>
									<td
										data-label={ __(
											'Question',
											'lw-lms'
										) }
									>
										{ question.prompt }
									</td>
									<td
										data-label={ __(
											'Answered',
											'lw-lms'
										) }
									>
										{ question.answered }
									</td>
									<td data-label={ __( 'Right', 'lw-lms' ) }>
										{ question.type === 'open'
											? __( 'Not scored', 'lw-lms' )
											: question.correct }
									</td>
									<td data-label={ __( 'Wrong', 'lw-lms' ) }>
										{ question.type === 'open'
											? __( 'Not scored', 'lw-lms' )
											: question.wrong }
									</td>
									<td
										data-label={ __(
											'Right answers',
											'lw-lms'
										) }
									>
										{ question.correct_ratio === null
											? __( 'Not scored', 'lw-lms' )
											: `${ question.correct_ratio }%` }
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</div>
			) }
			<p className="lw-admin-hint">
				{ __(
					'Question statistics use the latest 500 attempts.',
					'lw-lms'
				) }
			</p>
		</Section>
	);
}
