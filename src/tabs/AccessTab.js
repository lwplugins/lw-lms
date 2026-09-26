/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { SelectRow } from '../components/Fields';
import Section from '../components/Section';
import { OptionSwitch, SwitchList } from '../components/Switches';

/**
 * Course access: the access type new courses start with, preview lessons
 * and staff access.
 *
 * @param {Object} props
 * @param {Object} props.store Settings store.
 */
export default function AccessTab( { store } ) {
	return (
		<>
			<Section
				title={ __( 'New courses', 'lw-lms' ) }
				description={ __(
					'What a course you create starts with. You can change it on each course.',
					'lw-lms'
				) }
			>
				<SelectRow
					store={ store }
					name="default_access_type"
					title={ __( 'Default access type', 'lw-lms' ) }
					help={ __(
						'Open courses need no login, free courses need a login, paid courses need a purchase.',
						'lw-lms'
					) }
					options={ [
						{
							value: 'open',
							label: __( 'Open (anyone)', 'lw-lms' ),
						},
						{
							value: 'free',
							label: __( 'Free (login required)', 'lw-lms' ),
						},
						{
							value: 'paid',
							label: __( 'Paid (purchase required)', 'lw-lms' ),
						},
					] }
				/>
			</Section>
			<Section title={ __( 'Previews and staff', 'lw-lms' ) }>
				<SwitchList>
					<OptionSwitch
						store={ store }
						name="enable_preview_lessons"
						title={ __( 'Preview lessons', 'lw-lms' ) }
						help={ __(
							'Lessons you mark as a preview in the course builder can be opened by logged-in visitors without enrolling. When off, no lesson is treated as a preview; your choices are kept for when you turn it back on.',
							'lw-lms'
						) }
					/>
					<OptionSwitch
						store={ store }
						name="auto_enroll_admins"
						title={ __(
							'Give LMS managers access to every course',
							'lw-lms'
						) }
						help={ __(
							'Users with the manage_lms capability (administrators by default) can open every course and lesson without buying or enrolling. No enrollment is recorded, so no welcome emails or drip sequences are triggered.',
							'lw-lms'
						) }
					/>
				</SwitchList>
			</Section>
		</>
	);
}
