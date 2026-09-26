/**
 * WordPress dependencies
 */
import { useCallback } from '@wordpress/element';

/**
 * Space-separated ids for aria-describedby, or undefined when there are none.
 *
 * @param {...(string|undefined|false)} ids Ids (falsy ones are skipped).
 * @return {string|undefined} Attribute value.
 */
export const describedBy = ( ...ids ) =>
	ids.filter( Boolean ).join( ' ' ) || undefined;

/**
 * Ref that sets aria-describedby on a control whose component overwrites
 * that prop (SelectControl always sets it to its own help id).
 *
 * @param {string|undefined} value Attribute value.
 * @return {(node: ?Element) => void} Ref callback.
 */
export function useDescribedByRef( value ) {
	return useCallback(
		( node ) => {
			if ( ! node ) {
				return;
			}
			if ( value ) {
				node.setAttribute( 'aria-describedby', value );
			} else {
				node.removeAttribute( 'aria-describedby' );
			}
		},
		[ value ]
	);
}

/**
 * Server (or client) validation messages next to a field. Give it an id and
 * point the field's aria-describedby at it (and set aria-invalid), so screen
 * readers announce the message with the field.
 *
 * @param {Object}   props
 * @param {string[]} props.errors Messages.
 * @param {string}   props.id     Optional id (for aria-describedby).
 */
export default function FieldErrors( { errors = [], id } ) {
	if ( ! errors.length ) {
		return null;
	}

	return (
		<ul className="lw-admin-fielderror" id={ id }>
			{ errors.map( ( message ) => (
				<li key={ message }>{ message }</li>
			) ) }
		</ul>
	);
}
