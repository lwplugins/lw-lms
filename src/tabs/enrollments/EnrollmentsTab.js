/**
 * WordPress dependencies
 */
import { useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

/**
 * Internal dependencies
 */
import FilterSelect from '../../components/FilterSelect';
import LoadError from '../../components/LoadError';
import Pagination from '../../components/Pagination';
import SearchFilter from '../../components/SearchFilter';
import Section from '../../components/Section';
import TableSkeleton from '../../components/TableSkeleton';
import { api, errorMessage } from '../../data/api';
import { WOOCOMMERCE } from '../../data/boot';
import postOptions from '../../data/courseOptions';
import useList from '../../data/useList';
import useRemote from '../../data/useRemote';
import EnrollmentTable from './EnrollmentTable';
import GrantForm from './GrantForm';

/**
 * Enrollments: who has access to which course, with progress; filters,
 * manual grant and revoke.
 */
export default function EnrollmentsTab() {
	const list = useList( api.enrollments, { status: 'active' } );
	const courses = useRemote( api.courses );
	const [ busy, setBusy ] = useState( null );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	const revoke = async ( row ) => {
		setBusy( row.id );
		try {
			await api.revoke( row.userId, row.courseId );
			createSuccessNotice( __( 'Access revoked.', 'lw-lms' ), {
				type: 'snackbar',
			} );
			await list.reload();
		} catch ( e ) {
			createErrorNotice( errorMessage( e ), { type: 'snackbar' } );
		}
		setBusy( null );
	};

	let body;
	if ( list.error ) {
		body = <LoadError message={ list.error } onRetry={ list.reload } />;
	} else if ( list.isLoading ) {
		body = <TableSkeleton />;
	} else if ( list.data.items.length === 0 ) {
		body = (
			<p className="lw-lms-empty">
				{ __( 'No enrollments match these filters.', 'lw-lms' ) }
			</p>
		);
	} else {
		body = (
			<EnrollmentTable
				rows={ list.data.items }
				onRevoke={ revoke }
				busy={ busy }
			/>
		);
	}

	return (
		<>
			<Section className={ list.isFetching ? 'is-fetching' : '' }>
				<div className="lw-lms-filters">
					<SearchFilter
						label={ __( 'Search learners', 'lw-lms' ) }
						value={ list.filters.search }
						onChange={ ( value ) =>
							list.setFilter( 'search', value )
						}
					/>
					<FilterSelect
						label={ __( 'Course', 'lw-lms' ) }
						value={ list.filters.course }
						options={ postOptions(
							courses.data,
							__( 'All courses', 'lw-lms' )
						) }
						onChange={ ( value ) =>
							list.setFilter( 'course', value )
						}
					/>
					<FilterSelect
						label={ __( 'Status', 'lw-lms' ) }
						value={ list.filters.status }
						options={ [
							{ value: '', label: __( 'Any status', 'lw-lms' ) },
							{
								value: 'active',
								label: __( 'Active', 'lw-lms' ),
							},
							{
								value: 'expired',
								label: __( 'Expired', 'lw-lms' ),
							},
							{
								value: 'revoked',
								label: __( 'Revoked', 'lw-lms' ),
							},
						] }
						onChange={ ( value ) =>
							list.setFilter( 'status', value )
						}
					/>
					<FilterSelect
						label={ __( 'Source', 'lw-lms' ) }
						value={ list.filters.source }
						options={ [
							{ value: '', label: __( 'Any source', 'lw-lms' ) },
							{
								value: 'manual',
								label: __( 'Manual', 'lw-lms' ),
							},
							WOOCOMMERCE && {
								value: 'woocommerce',
								label: __( 'WooCommerce order', 'lw-lms' ),
							},
							{
								value: 'free',
								label: __( 'Free course', 'lw-lms' ),
							},
						].filter( Boolean ) }
						onChange={ ( value ) =>
							list.setFilter( 'source', value )
						}
					/>
				</div>
				{ body }
				{ list.data && list.data.total > 0 && (
					<Pagination
						page={ list.page }
						pages={ list.data.pages }
						total={ list.data.total }
						onChange={ list.setPage }
					/>
				) }
			</Section>
			<GrantForm courses={ courses.data } onGranted={ list.reload } />
		</>
	);
}
