/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { NumberRow } from '../components/Fields';
import KeyValue from '../components/KeyValue';
import Section from '../components/Section';
import { OptionSwitch, SwitchList } from '../components/Switches';

/**
 * REST API reference (page size + every public endpoint) and data removal.
 *
 * @param {Object} props
 * @param {Object} props.store Settings store.
 */
export default function AdvancedTab( { store } ) {
	const { meta } = store.data;

	return (
		<>
			<Section
				title={ __( 'REST API', 'lw-lms' ) }
				description={ __(
					'Your front end or app reads courses and saves progress through these endpoints.',
					'lw-lms'
				) }
			>
				<NumberRow
					store={ store }
					name="courses_per_page"
					title={ __( 'Courses per page', 'lw-lms' ) }
					help={ __(
						'How many courses GET /courses returns when the request does not set per_page.',
						'lw-lms'
					) }
				/>
				<KeyValue
					rows={ [
						{
							label: __( 'Namespace', 'lw-lms' ),
							value: (
								<code className="lw-admin-code">
									{ meta.namespace }
								</code>
							),
						},
						{
							label: __( 'Base URL', 'lw-lms' ),
							value: (
								<code className="lw-admin-code">
									{ meta.restBase }
								</code>
							),
						},
					] }
				/>
				<div className="lw-lms-table-wrap">
					<table className="lw-lms-table is-cards">
						<thead>
							<tr>
								<th scope="col">
									{ __( 'Endpoint', 'lw-lms' ) }
								</th>
								<th scope="col">
									{ __( 'What it does', 'lw-lms' ) }
								</th>
								<th scope="col">
									{ __( 'Who may call it', 'lw-lms' ) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ meta.endpoints.map( ( endpoint ) => (
								<tr key={ endpoint.method + endpoint.path }>
									<td
										data-label={ __(
											'Endpoint',
											'lw-lms'
										) }
									>
										<span className="lw-lms-method">
											{ endpoint.method }
										</span>{ ' ' }
										<code className="lw-admin-code">
											{ endpoint.path }
										</code>
									</td>
									<td
										data-label={ __(
											'What it does',
											'lw-lms'
										) }
									>
										{ endpoint.description }
									</td>
									<td
										className="lw-admin-muted"
										data-label={ __(
											'Who may call it',
											'lw-lms'
										) }
									>
										{ endpoint.access }
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</div>
			</Section>
			<Section title={ __( 'Data removal', 'lw-lms' ) }>
				<SwitchList>
					<OptionSwitch
						store={ store }
						name="delete_data_on_uninstall"
						title={ __(
							'Delete all data when the plugin is deleted',
							'lw-lms'
						) }
						help={ __(
							"When the plugin is deleted from the Plugins screen, permanently remove all enrollments, progress, completion records and quiz attempts, the course and lesson settings (access, sections, drip, quizzes, attachments list), the learners' drip start dates, the LMS settings and the LMS capabilities. Course and lesson posts are kept. Leave this off to keep everything, for example when reinstalling.",
							'lw-lms'
						) }
					/>
				</SwitchList>
			</Section>
		</>
	);
}
