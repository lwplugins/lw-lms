/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import StatusIcon from './StatusIcon';

/**
 * A yes / no value as an icon plus text (never a bare glyph). With `label`
 * the text is the label and the state is spoken to screen readers only.
 *
 * @param {Object}  props
 * @param {boolean} props.value Value.
 * @param {string}  props.label Optional fixed label.
 * @param {string}  props.yes   Text for true.
 * @param {string}  props.no    Text for false.
 */
export default function YesNo( { value, label, yes, no } ) {
	const text = value
		? yes || __( 'Yes', 'lw-lms' )
		: no || __( 'No', 'lw-lms' );

	return (
		<span className={ `lw-lms-yesno ${ value ? 'is-ok' : 'is-idle' }` }>
			<StatusIcon status={ value ? 'ok' : 'critical' } size={ 16 } />
			<span>{ label || text }</span>
			{ label && (
				<span className="screen-reader-text">
					{ value
						? __( '(active)', 'lw-lms' )
						: __( '(not active)', 'lw-lms' ) }
				</span>
			) }
		</span>
	);
}
