/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import ConfirmButton from '../../components/ConfirmButton';
import ProgressBar from '../../components/ProgressBar';
import StatusBadge from '../../components/StatusBadge';
import { LINKS } from '../../data/boot';
import { STATUS, sourceLabel } from './labels';

/**
 * Learner cell: name (profile link) and email (login without list_users);
 * "Deleted user" when gone.
 *
 * @param {Object} props
 * @param {Object} props.row Enrollment row.
 */
function Learner( { row } ) {
	if ( ! row.user ) {
		return (
			<span className="lw-admin-muted">
				{ __( 'Deleted user', 'lw-lms' ) }
			</span>
		);
	}
	return (
		<span className="lw-admin-stack">
			<a href={ `${ LINKS.userEdit }${ row.userId }` }>
				{ row.user.name }
			</a>
			<span className="lw-admin-hint">
				{ row.user.email || row.user.login }
			</span>
		</span>
	);
}

/**
 * Course cell: title linking to its edit screen; "Deleted course" when gone.
 *
 * @param {Object} props
 * @param {Object} props.post Course or lesson ({ title, editUrl }) or null.
 */
export function PostLink( { post } ) {
	if ( ! post ) {
		return (
			<span className="lw-admin-muted">
				{ __( 'Deleted', 'lw-lms' ) }
			</span>
		);
	}
	return post.editUrl ? (
		<a href={ post.editUrl }>{ post.title }</a>
	) : (
		<span>{ post.title }</span>
	);
}

/**
 * The enrollments of one page. On narrow screens each row becomes a card
 * (the cells carry their column name in data-label).
 *
 * @param {Object}                props
 * @param {Object[]}              props.rows     Rows.
 * @param {(row: Object) => void} props.onRevoke Revoke a row's learner-course pair.
 * @param {number|null}           props.busy     Row id being revoked.
 */
export default function EnrollmentTable( { rows, onRevoke, busy } ) {
	return (
		<div className="lw-lms-table-wrap">
			<table className="lw-lms-table is-cards">
				<thead>
					<tr>
						<th scope="col">{ __( 'Learner', 'lw-lms' ) }</th>
						<th scope="col">{ __( 'Course', 'lw-lms' ) }</th>
						<th scope="col">{ __( 'Progress', 'lw-lms' ) }</th>
						<th scope="col">{ __( 'Status', 'lw-lms' ) }</th>
						<th scope="col">{ __( 'Source', 'lw-lms' ) }</th>
						<th scope="col">{ __( 'Enrolled', 'lw-lms' ) }</th>
						<th scope="col">{ __( 'Access ends', 'lw-lms' ) }</th>
						<th scope="col">
							<span className="screen-reader-text">
								{ __( 'Actions', 'lw-lms' ) }
							</span>
						</th>
					</tr>
				</thead>
				<tbody>
					{ rows.map( ( row ) => {
						const status = STATUS[ row.status ] || STATUS.revoked;
						return (
							<tr key={ row.id }>
								<td data-label={ __( 'Learner', 'lw-lms' ) }>
									<Learner row={ row } />
								</td>
								<td data-label={ __( 'Course', 'lw-lms' ) }>
									<PostLink post={ row.course } />
								</td>
								<td data-label={ __( 'Progress', 'lw-lms' ) }>
									<ProgressBar progress={ row.progress } />
								</td>
								<td
									className="lw-lms-nowrap"
									data-label={ __( 'Status', 'lw-lms' ) }
								>
									<StatusBadge status={ status.badge }>
										{ status.label() }
									</StatusBadge>
								</td>
								<td
									className="lw-lms-nowrap"
									data-label={ __( 'Source', 'lw-lms' ) }
								>
									{ sourceLabel( row.source ) }
								</td>
								<td data-label={ __( 'Enrolled', 'lw-lms' ) }>
									{ row.granted }
								</td>
								<td
									data-label={ __( 'Access ends', 'lw-lms' ) }
								>
									{ row.expires ||
										__( 'Lifetime', 'lw-lms' ) }
								</td>
								<td className="lw-lms-table__actions">
									{ row.status === 'active' && (
										<ConfirmButton
											__next40pxDefaultSize
											variant="tertiary"
											isDestructive
											isBusy={ busy === row.id }
											disabled={ busy === row.id }
											confirmText={ __(
												'Revoke access',
												'lw-lms'
											) }
											question={ sprintf(
												/* translators: 1: learner name, 2: course title. */
												__(
													'Revoke access to %2$s for %1$s? Every active enrollment of this learner in the course ends, including ones from orders. Their progress is kept.',
													'lw-lms'
												),
												row.user?.name ||
													__(
														'Deleted user',
														'lw-lms'
													),
												row.course?.title ||
													__( 'Deleted', 'lw-lms' )
											) }
											onConfirm={ () => onRevoke( row ) }
										>
											{ __( 'Revoke', 'lw-lms' ) }
										</ConfirmButton>
									) }
								</td>
							</tr>
						);
					} ) }
				</tbody>
			</table>
		</div>
	);
}
