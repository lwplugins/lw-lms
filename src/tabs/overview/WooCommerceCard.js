/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import KeyValue from '../../components/KeyValue';
import Section from '../../components/Section';
import StatusBadge from '../../components/StatusBadge';
import YesNo from '../../components/YesNo';

/**
 * WooCommerce state on the Overview. Only rendered while WooCommerce is
 * active; Subscriptions and Memberships only appear when they are detected.
 *
 * @param {Object} props
 * @param {Object} props.woo Overview `woocommerce` block.
 */
export default function WooCommerceCard( { woo } ) {
	return (
		<Section
			title={ __( 'WooCommerce', 'lw-lms' ) }
			badge={
				woo.enabled ? (
					<StatusBadge status="ok">
						{ __( 'On', 'lw-lms' ) }
					</StatusBadge>
				) : (
					<StatusBadge status="idle">
						{ __( 'Off', 'lw-lms' ) }
					</StatusBadge>
				)
			}
			description={
				woo.enabled
					? __(
							'Orders grant access to the courses linked to their products.',
							'lw-lms'
						)
					: __(
							'The integration is off: orders do not grant or remove access. Turn it on under Settings, WooCommerce.',
							'lw-lms'
						)
			}
		>
			<KeyValue
				rows={ [
					{
						label: __( 'Paid courses', 'lw-lms' ),
						value: woo.paidCourses,
					},
					{
						label: __( 'Active access from orders', 'lw-lms' ),
						value: woo.orderAccess,
					},
					woo.subscriptions && {
						label: __( 'WooCommerce Subscriptions', 'lw-lms' ),
						value: (
							<YesNo value label={ __( 'Detected', 'lw-lms' ) } />
						),
					},
					woo.memberships && {
						label: __( 'WooCommerce Memberships', 'lw-lms' ),
						value: (
							<YesNo value label={ __( 'Detected', 'lw-lms' ) } />
						),
					},
				] }
			/>
		</Section>
	);
}
