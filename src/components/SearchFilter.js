/**
 * WordPress dependencies
 */
import { SearchControl } from '@wordpress/components';
import { useDebounce } from '@wordpress/compose';
import { useState } from '@wordpress/element';

/**
 * Search box that applies its text after a short pause in typing.
 *
 * @param {Object}                  props
 * @param {string}                  props.label    Accessible label and placeholder.
 * @param {string}                  props.value    Applied search.
 * @param {(value: string) => void} props.onChange Apply a search.
 */
export default function SearchFilter( { label, value, onChange } ) {
	const [ text, setText ] = useState( value || '' );
	const apply = useDebounce( onChange, 350 );

	return (
		<SearchControl
			__nextHasNoMarginBottom
			className="lw-lms-filter lw-lms-filter--search"
			label={ label }
			placeholder={ label }
			value={ text }
			onChange={ ( next ) => {
				setText( next );
				apply( next.trim() );
			} }
		/>
	);
}
