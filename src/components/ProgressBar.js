/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Course progress: a thin bar plus "40% (2 of 5)".
 *
 * @param {Object} props
 * @param {Object} props.progress { completed, total, percentage }.
 */
export default function ProgressBar( { progress } ) {
	const { completed, total, percentage } = progress;

	return (
		<div className="lw-lms-progress">
			<span className="lw-lms-progress__track" aria-hidden="true">
				<span
					className="lw-lms-progress__fill"
					style={ { width: `${ percentage }%` } }
				/>
			</span>
			<span className="lw-lms-progress__text">
				{ sprintf(
					/* translators: 1: percentage, 2: completed lessons, 3: all lessons. */
					__( '%1$d%% (%2$d of %3$d)', 'lw-lms' ),
					percentage,
					completed,
					total
				) }
			</span>
		</div>
	);
}
