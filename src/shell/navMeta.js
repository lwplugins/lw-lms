/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Icon, caution } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import StatusBadge from '../components/StatusBadge';
import { tabOfField } from './tabs';

/**
 * Nav extras: "Off" on WooCommerce while the integration is switched off,
 * and a flag on every tab holding a field the last save rejected (the flag
 * wins).
 *
 * @param {Object}      props
 * @param {Object}      props.errors  Field errors { key: [ messages ] }.
 * @param {Object|null} props.options Saved options (null while loading).
 * @return {Object} { tabId: node }.
 */
export default function navMeta( { errors, options } ) {
	const out = {};

	if ( options && ! options.woo_enabled ) {
		out.woocommerce = (
			<StatusBadge status="idle">{ __( 'Off', 'lw-lms' ) }</StatusBadge>
		);
	}

	Object.keys( errors ).forEach( ( key ) => {
		out[ tabOfField( key ) ] = (
			<span className="lw-admin-navflag">
				<Icon icon={ caution } size={ 18 } />
				<span className="screen-reader-text">
					{ __( 'Has invalid settings', 'lw-lms' ) }
				</span>
			</span>
		);
	} );

	return out;
}
