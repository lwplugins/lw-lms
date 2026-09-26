/**
 * WordPress dependencies
 */
import { Modal } from '@wordpress/components';
import { useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Callout from '../../components/Callout';
import KeyValue from '../../components/KeyValue';
import LoadError from '../../components/LoadError';
import { SkeletonRegion, SkeletonText } from '../../components/skeleton';
import StatusBadge from '../../components/StatusBadge';
import { api } from '../../data/api';
import useRemote from '../../data/useRemote';
import { PostLink } from '../enrollments/EnrollmentTable';

/**
 * What the learner answered, as text.
 *
 * @param {Object} answer Answer entry.
 * @return {string} Text.
 */
function givenText( answer ) {
	if ( answer.type === 'boolean' ) {
		if ( answer.given === true ) {
			return __( 'True', 'lw-lms' );
		}
		if ( answer.given === false ) {
			return __( 'False', 'lw-lms' );
		}
		return '';
	}
	return answer.givenText || '';
}

/**
 * The right answer of a wrongly answered question (administrators only).
 *
 * @param {Object} answer Answer entry.
 * @return {string|null} Text.
 */
function rightText( answer ) {
	if ( answer.correctText ) {
		return answer.correctText;
	}
	if ( answer.correctAnswer === true ) {
		return __( 'True', 'lw-lms' );
	}
	if ( answer.correctAnswer === false ) {
		return __( 'False', 'lw-lms' );
	}
	return null;
}

/**
 * One answered question.
 *
 * @param {Object}  props
 * @param {Object}  props.answer Answer entry.
 * @param {number}  props.index  Position (0-based).
 * @param {boolean} props.reveal Viewer sees the answer key.
 */
function Answer( { answer, index, reveal } ) {
	const given = givenText( answer );
	let badge = null;
	if ( ! answer.scored ) {
		badge = (
			<StatusBadge status="info">
				{ __( 'Not scored', 'lw-lms' ) }
			</StatusBadge>
		);
	} else if ( reveal && answer.correct !== null ) {
		badge = answer.correct ? (
			<StatusBadge status="ok">{ __( 'Right', 'lw-lms' ) }</StatusBadge>
		) : (
			<StatusBadge status="critical">
				{ __( 'Wrong', 'lw-lms' ) }
			</StatusBadge>
		);
	}
	const right =
		reveal && answer.correct === false ? rightText( answer ) : null;

	return (
		<li className="lw-lms-answer">
			<div className="lw-lms-answer__head">
				<strong>
					{ sprintf(
						/* translators: 1: question number, 2: question text. */
						__( '%1$d. %2$s', 'lw-lms' ),
						index + 1,
						answer.prompt
					) }
				</strong>
				{ badge }
			</div>
			<p className="lw-lms-answer__given">
				{ given ? (
					given
				) : (
					<span className="lw-admin-muted">
						{ __( 'No answer', 'lw-lms' ) }
					</span>
				) }
			</p>
			{ right && (
				<p className="lw-admin-hint">
					{ sprintf(
						/* translators: %s: the right answer. */
						__( 'Right answer: %s', 'lw-lms' ),
						right
					) }
				</p>
			) }
		</li>
	);
}

/**
 * One attempt in a dialog: who, when, score and every answer.
 *
 * @param {Object}     props
 * @param {number}     props.id      Attempt ID.
 * @param {() => void} props.onClose Close the dialog.
 */
export default function AttemptDetail( { id, onClose } ) {
	const fetcher = useCallback( () => api.attempt( id ), [ id ] );
	const { data, error, isLoading, reload } = useRemote( fetcher );

	let body;
	if ( error ) {
		body = <LoadError message={ error } onRetry={ reload } />;
	} else if ( isLoading ) {
		body = (
			<SkeletonRegion>
				<span className="lw-skel-stack is-loose">
					<SkeletonText lines={ 3 } />
					<SkeletonText lines={ 4 } />
				</span>
			</SkeletonRegion>
		);
	} else {
		body = (
			<div className="lw-lms-detail">
				<KeyValue
					rows={ [
						{
							label: __( 'Learner', 'lw-lms' ),
							value:
								data.user?.name ||
								__( 'Deleted user', 'lw-lms' ),
						},
						{
							label: __( 'Lesson', 'lw-lms' ),
							value: <PostLink post={ data.lesson } />,
						},
						data.course && {
							label: __( 'Course', 'lw-lms' ),
							value: <PostLink post={ data.course } />,
						},
						{
							label: __( 'Submitted', 'lw-lms' ),
							value: data.submitted,
						},
						{
							label: __( 'Score', 'lw-lms' ),
							value: sprintf(
								/* translators: 1: points, 2: scored questions, 3: percentage. */
								__( '%1$d of %2$d (%3$s%%)', 'lw-lms' ),
								data.score,
								data.scoredQuestions,
								data.percentage
							),
						},
						{
							label: __( 'Result', 'lw-lms' ),
							value: data.passed ? (
								<StatusBadge status="ok">
									{ __( 'Passed', 'lw-lms' ) }
								</StatusBadge>
							) : (
								<StatusBadge status="warning">
									{ __( 'Not passed', 'lw-lms' ) }
								</StatusBadge>
							),
						},
					] }
				/>
				{ ! data.showsAnswers && (
					<Callout>
						{ __(
							'Only administrators see which answers were right.',
							'lw-lms'
						) }
					</Callout>
				) }
				{ data.answers.length > 0 ? (
					<ol className="lw-lms-answers">
						{ data.answers.map( ( answer, index ) => (
							<Answer
								key={ answer.id || index }
								answer={ answer }
								index={ index }
								reveal={ data.showsAnswers }
							/>
						) ) }
					</ol>
				) : (
					<p className="lw-admin-muted">
						{ __(
							'This attempt has no stored answers.',
							'lw-lms'
						) }
					</p>
				) }
			</div>
		);
	}

	return (
		<Modal
			title={ __( 'Quiz attempt', 'lw-lms' ) }
			onRequestClose={ onClose }
			size="medium"
			className="lw-lms-modal"
		>
			{ body }
		</Modal>
	);
}
