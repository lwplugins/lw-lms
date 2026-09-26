/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Callout from '../components/Callout';
import Section from '../components/Section';
import { OptionSwitch, SwitchList } from '../components/Switches';

/**
 * The WooCommerce integration switch (the tab only exists while WooCommerce
 * is active).
 *
 * @param {Object} props
 * @param {Object} props.store Settings store.
 */
export default function WooCommerceTab( { store } ) {
	return (
		<Section
			title={ __( 'Paid courses', 'lw-lms' ) }
			description={ __(
				'Link products to a course on the course edit screen (Access Settings).',
				'lw-lms'
			) }
		>
			<SwitchList>
				<OptionSwitch
					store={ store }
					name="woo_enabled"
					title={ __(
						'Enable WooCommerce integration for paid courses',
						'lw-lms'
					) }
					help={ __(
						'When off, orders no longer grant or revoke course access, and WooCommerce purchases, subscriptions and memberships are not checked. Access already granted by past orders is kept.',
						'lw-lms'
					) }
				/>
			</SwitchList>
			<Callout>
				{ __(
					'Completed and processing orders grant access to the linked courses. A refunded, cancelled or failed order removes the access that order gave.',
					'lw-lms'
				) }
			</Callout>
		</Section>
	);
}
