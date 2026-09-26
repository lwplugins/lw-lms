/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { NumberRow } from '../components/Fields';
import Section from '../components/Section';
import { OptionSwitch, SwitchList } from '../components/Switches';

/**
 * Quiz defaults: pass mark and whether passing is required.
 *
 * @param {Object} props
 * @param {Object} props.store Settings store.
 */
export default function QuizzesTab( { store } ) {
	return (
		<>
			<Section title={ __( 'Pass mark', 'lw-lms' ) }>
				<NumberRow
					store={ store }
					name="quiz_pass_percentage"
					title={ __( 'Default pass percentage', 'lw-lms' ) }
					help={ __(
						'Used when a lesson quiz does not set its own pass_percentage.',
						'lw-lms'
					) }
					suffix="%"
				/>
			</Section>
			<Section title={ __( 'Graded quizzes', 'lw-lms' ) }>
				<SwitchList>
					<OptionSwitch
						store={ store }
						name="require_quiz_pass"
						title={ __(
							'Require passing the quiz to complete a lesson',
							'lw-lms'
						) }
						help={ __(
							'When on, a lesson with a quiz is marked completed when the learner passes it, and cannot be completed any other way. When off, quizzes are practice only.',
							'lw-lms'
						) }
					/>
				</SwitchList>
			</Section>
		</>
	);
}
