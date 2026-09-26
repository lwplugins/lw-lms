/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	cart,
	chartBar,
	check,
	code,
	home,
	lock,
	people,
} from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { CAN_MANAGE_LEARNERS, WOOCOMMERCE } from '../data/boot';

/**
 * Nav groups, in order. A tab without a group sits above them.
 */
export const GROUPS = [
	{ id: 'learners', label: __( 'Learners', 'lw-lms' ) },
	{ id: 'settings', label: __( 'Settings', 'lw-lms' ) },
];

/**
 * Tab registry. `save` = the tab edits lw_lms_options (top bar Save shown).
 * `fields` maps option keys to the tab, so a failed save can flag the tab
 * holding an invalid field. `learners` = needs manage_lms; `woocommerce` =
 * only while WooCommerce is active.
 */
const ALL_TABS = [
	{
		id: 'overview',
		label: __( 'Overview', 'lw-lms' ),
		title: __( 'Overview', 'lw-lms' ),
		icon: home,
		save: false,
	},
	{
		id: 'enrollments',
		group: 'learners',
		label: __( 'Enrollments', 'lw-lms' ),
		title: __( 'Enrollments', 'lw-lms' ),
		icon: people,
		save: false,
		learners: true,
	},
	{
		id: 'quiz-results',
		group: 'learners',
		label: __( 'Quiz results', 'lw-lms' ),
		title: __( 'Quiz results', 'lw-lms' ),
		icon: chartBar,
		save: false,
		learners: true,
	},
	{
		id: 'access',
		group: 'settings',
		label: __( 'Access', 'lw-lms' ),
		title: __( 'Course access', 'lw-lms' ),
		icon: lock,
		save: true,
		fields: [
			'default_access_type',
			'enable_preview_lessons',
			'auto_enroll_admins',
		],
	},
	{
		id: 'quizzes',
		group: 'settings',
		label: __( 'Quizzes', 'lw-lms' ),
		title: __( 'Quizzes', 'lw-lms' ),
		icon: check,
		save: true,
		fields: [ 'quiz_pass_percentage', 'require_quiz_pass' ],
	},
	{
		id: 'woocommerce',
		group: 'settings',
		label: __( 'WooCommerce', 'lw-lms' ),
		title: __( 'WooCommerce', 'lw-lms' ),
		icon: cart,
		save: true,
		woocommerce: true,
		fields: [ 'woo_enabled' ],
	},
	{
		id: 'advanced',
		group: 'settings',
		label: __( 'Advanced', 'lw-lms' ),
		title: __( 'Advanced', 'lw-lms' ),
		icon: code,
		save: true,
		fields: [ 'courses_per_page', 'delete_data_on_uninstall' ],
	},
];

export const TABS = ALL_TABS.filter(
	( tab ) =>
		( ! tab.learners || CAN_MANAGE_LEARNERS ) &&
		( ! tab.woocommerce || WOOCOMMERCE )
);

/**
 * Hash slugs of the classic screens, so old links still land on the tab
 * that now holds those settings.
 */
export const ALIASES = {
	general: 'access',
	results: 'quiz-results',
};

/**
 * Tab id that holds an option key (for error flags in the nav). Unknown
 * keys land on the first settings tab so they are never invisible.
 *
 * @param {string} field Option key.
 * @return {string} Tab id.
 */
export const tabOfField = ( field ) =>
	(
		TABS.find( ( tab ) => tab.fields?.includes( field ) ) ||
		TABS.find( ( tab ) => tab.save )
	).id;
