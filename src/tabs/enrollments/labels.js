/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Where a grant came from. Unknown sources (e.g. set by WP-CLI) show as is.
 *
 * @param {string} source Source key.
 * @return {string} Label.
 */
export const sourceLabel = ( source ) =>
	( {
		manual: __( 'Manual', 'lw-lms' ),
		woocommerce: __( 'WooCommerce order', 'lw-lms' ),
		free: __( 'Free course', 'lw-lms' ),
	} )[ source ] || source;

export const STATUS = {
	active: { badge: 'ok', label: () => __( 'Active', 'lw-lms' ) },
	expired: { badge: 'warning', label: () => __( 'Expired', 'lw-lms' ) },
	revoked: { badge: 'idle', label: () => __( 'Revoked', 'lw-lms' ) },
};
