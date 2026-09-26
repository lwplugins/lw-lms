/**
 * WordPress dependencies
 */
import { ComboboxControl } from '@wordpress/components';
import { useDebounce } from '@wordpress/compose';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { api } from '../../data/api';
import { CAN_SEE_EMAILS } from '../../data/boot';

/**
 * Pick a user by typing part of their name or username (or email, for
 * users who may list users).
 *
 * @param {Object}                  props
 * @param {string}                  props.value    Selected user ID ('' = none).
 * @param {(value: string) => void} props.onChange Select a user.
 */
export default function UserPicker( { value, onChange } ) {
	const [ options, setOptions ] = useState( [] );

	const search = useDebounce( ( text ) => {
		if ( text.trim().length < 2 ) {
			return;
		}
		api.users( text.trim() ).then(
			( users ) =>
				setOptions( ( prev ) => {
					const chosen = prev.filter( ( o ) => o.value === value );
					const found = users.map( ( user ) => ( {
						value: String( user.id ),
						label: `${ user.name } (${ user.email || user.login })`,
					} ) );
					return [
						...chosen,
						...found.filter( ( o ) => o.value !== value ),
					];
				} ),
			() => setOptions( [] )
		);
	}, 300 );

	return (
		<ComboboxControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ __( 'Learner', 'lw-lms' ) }
			help={
				CAN_SEE_EMAILS
					? __(
							'Type at least two letters of the name, username or email.',
							'lw-lms'
						)
					: __(
							'Type at least two letters of the name or username.',
							'lw-lms'
						)
			}
			value={ value || null }
			options={ options }
			onFilterValueChange={ search }
			onChange={ ( next ) => onChange( next || '' ) }
		/>
	);
}
