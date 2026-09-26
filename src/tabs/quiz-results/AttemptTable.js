/**
 * WordPress dependencies
 */
import { Button, CheckboxControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { seen, trash } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import ConfirmButton from '../../components/ConfirmButton';
import StatusBadge from '../../components/StatusBadge';
import { LINKS } from '../../data/boot';
import { PostLink } from '../enrollments/EnrollmentTable';

/**
 * One page of attempts with row selection (for bulk delete).
 *
 * @param {Object}                  props
 * @param {Object[]}                props.rows     Rows.
 * @param {number[]}                props.selected Selected attempt IDs.
 * @param {(ids: number[]) => void} props.onSelect Replace the selection.
 * @param {(id: number) => void}    props.onView   Open one attempt.
 * @param {(ids: number[]) => void} props.onDelete Delete attempts.
 */
export default function AttemptTable( {
	rows,
	selected,
	onSelect,
	onView,
	onDelete,
} ) {
	const ids = rows.map( ( row ) => row.id );
	const all =
		ids.length > 0 && ids.every( ( id ) => selected.includes( id ) );
	const toggle = ( id, on ) =>
		onSelect(
			on ? [ ...selected, id ] : selected.filter( ( x ) => x !== id )
		);

	return (
		<div className="lw-lms-table-wrap">
			<table className="lw-lms-table is-cards">
				<thead>
					<tr>
						<td className="lw-lms-table__check">
							<CheckboxControl
								__nextHasNoMarginBottom
								label={ __(
									'Select all on this page',
									'lw-lms'
								) }
								hideLabelFromVision
								checked={ all }
								onChange={ ( on ) => onSelect( on ? ids : [] ) }
							/>
						</td>
						<th scope="col">{ __( 'Learner', 'lw-lms' ) }</th>
						<th scope="col">{ __( 'Lesson', 'lw-lms' ) }</th>
						<th scope="col">{ __( 'Score', 'lw-lms' ) }</th>
						<th scope="col">{ __( 'Result', 'lw-lms' ) }</th>
						<th scope="col">{ __( 'Submitted', 'lw-lms' ) }</th>
						<th scope="col">
							<span className="screen-reader-text">
								{ __( 'Actions', 'lw-lms' ) }
							</span>
						</th>
					</tr>
				</thead>
				<tbody>
					{ rows.map( ( row ) => (
						<tr key={ row.id }>
							<td className="lw-lms-table__check">
								<CheckboxControl
									__nextHasNoMarginBottom
									label={ sprintf(
										/* translators: %d: attempt ID. */
										__( 'Select attempt %d', 'lw-lms' ),
										row.id
									) }
									hideLabelFromVision
									checked={ selected.includes( row.id ) }
									onChange={ ( on ) => toggle( row.id, on ) }
								/>
							</td>
							<td data-label={ __( 'Learner', 'lw-lms' ) }>
								{ row.user ? (
									<a
										href={ `${ LINKS.userEdit }${ row.userId }` }
									>
										{ row.user.name }
									</a>
								) : (
									<span className="lw-admin-muted">
										{ __( 'Deleted user', 'lw-lms' ) }
									</span>
								) }
							</td>
							<td data-label={ __( 'Lesson', 'lw-lms' ) }>
								<span className="lw-admin-stack">
									<PostLink post={ row.lesson } />
									{ row.course && (
										<span className="lw-admin-hint">
											{ row.course.title }
										</span>
									) }
								</span>
							</td>
							<td data-label={ __( 'Score', 'lw-lms' ) }>
								<span className="lw-admin-nowrap">
									{ sprintf(
										/* translators: 1: percentage, 2: points, 3: scored questions. */
										__( '%1$s%% (%2$d of %3$d)', 'lw-lms' ),
										row.percentage,
										row.score,
										row.scoredQuestions
									) }
								</span>
							</td>
							<td data-label={ __( 'Result', 'lw-lms' ) }>
								{ row.passed ? (
									<StatusBadge status="ok">
										{ __( 'Passed', 'lw-lms' ) }
									</StatusBadge>
								) : (
									<StatusBadge status="warning">
										{ __( 'Not passed', 'lw-lms' ) }
									</StatusBadge>
								) }
							</td>
							<td data-label={ __( 'Submitted', 'lw-lms' ) }>
								{ row.submitted }
							</td>
							<td className="lw-lms-table__actions">
								<Button
									__next40pxDefaultSize
									variant="tertiary"
									icon={ seen }
									onClick={ () => onView( row.id ) }
								>
									{ __( 'Answers', 'lw-lms' ) }
								</Button>
								<ConfirmButton
									__next40pxDefaultSize
									variant="tertiary"
									isDestructive
									icon={ trash }
									label={ __( 'Delete attempt', 'lw-lms' ) }
									confirmText={ __( 'Delete', 'lw-lms' ) }
									question={ __(
										'Delete this attempt? This cannot be undone.',
										'lw-lms'
									) }
									onConfirm={ () => onDelete( [ row.id ] ) }
								/>
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}
