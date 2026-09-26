/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { chartBar, people, plus, postList, pages } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import LoadError from '../../components/LoadError';
import Section from '../../components/Section';
import {
	SkeletonRegion,
	SkeletonSection,
	SkeletonTiles,
} from '../../components/skeleton';
import StatTile from '../../components/StatTile';
import { api } from '../../data/api';
import { CAN_MANAGE_LEARNERS, LINKS } from '../../data/boot';
import useRemote from '../../data/useRemote';
import WooCommerceCard from './WooCommerceCard';

const number = ( value ) => Number( value || 0 ).toLocaleString();

/**
 * "N drafts" detail line (nothing when there are none).
 *
 * @param {number} drafts Draft count.
 * @return {string|null} Detail.
 */
const draftsLine = ( drafts ) =>
	drafts > 0
		? sprintf(
				/* translators: %s: number of drafts. */
				_n( '%s draft', '%s drafts', drafts, 'lw-lms' ),
				number( drafts )
			)
		: null;

/**
 * Overview: content and learner counts, the WooCommerce integration (only
 * while WooCommerce is active) and quick links.
 */
export default function OverviewTab() {
	const { data, error, isLoading, reload } = useRemote( api.overview );

	if ( error ) {
		return <LoadError message={ error } onRetry={ reload } />;
	}

	if ( isLoading ) {
		return (
			<SkeletonRegion className="lw-skel-tab">
				<SkeletonTiles count={ 4 } />
				<SkeletonSection />
			</SkeletonRegion>
		);
	}

	const links = [
		{
			href: LINKS.newCourse,
			icon: plus,
			label: __( 'Add a course', 'lw-lms' ),
		},
		{
			href: LINKS.courses,
			icon: pages,
			label: __( 'All courses', 'lw-lms' ),
		},
		{
			href: LINKS.lessons,
			icon: postList,
			label: __( 'All lessons', 'lw-lms' ),
		},
		CAN_MANAGE_LEARNERS && {
			href: '#enrollments',
			icon: people,
			label: __( 'Manage enrollments', 'lw-lms' ),
		},
		CAN_MANAGE_LEARNERS && {
			href: '#quiz-results',
			icon: chartBar,
			label: __( 'See quiz results', 'lw-lms' ),
		},
	].filter( Boolean );

	return (
		<>
			<div className="lw-admin-tiles">
				<StatTile
					label={ __( 'Courses', 'lw-lms' ) }
					value={ number( data.courses.published ) }
					detail={
						draftsLine( data.courses.drafts ) ||
						__( 'Published', 'lw-lms' )
					}
				/>
				<StatTile
					label={ __( 'Lessons', 'lw-lms' ) }
					value={ number( data.lessons.published ) }
					detail={
						draftsLine( data.lessons.drafts ) ||
						__( 'Published', 'lw-lms' )
					}
				/>
				<StatTile
					label={ __( 'Active enrollments', 'lw-lms' ) }
					value={ number( data.enrollments ) }
					detail={ __( 'Learners with access right now', 'lw-lms' ) }
				/>
				<StatTile
					label={ __( 'Quiz attempts', 'lw-lms' ) }
					value={ number( data.quizAttempts ) }
					detail={ sprintf(
						/* translators: %d: number of days. */
						_n(
							'In the last %d day',
							'In the last %d days',
							data.quizAttemptDays,
							'lw-lms'
						),
						data.quizAttemptDays
					) }
				/>
			</div>
			{ data.woocommerce && <WooCommerceCard woo={ data.woocommerce } /> }
			<Section title={ __( 'Quick links', 'lw-lms' ) }>
				<div className="lw-lms-links">
					{ links.map( ( link ) => (
						<Button
							key={ link.href }
							__next40pxDefaultSize
							variant="secondary"
							icon={ link.icon }
							href={ link.href }
						>
							{ link.label }
						</Button>
					) ) }
				</div>
			</Section>
		</>
	);
}
