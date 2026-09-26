/**
 * WordPress dependencies
 */
import { Button, TextControl } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useCallback, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { download, trash } from '@wordpress/icons';
import { store as noticesStore } from '@wordpress/notices';

/**
 * Internal dependencies
 */
import ConfirmButton from '../../components/ConfirmButton';
import FilterSelect from '../../components/FilterSelect';
import LoadError from '../../components/LoadError';
import Pagination from '../../components/Pagination';
import SearchFilter from '../../components/SearchFilter';
import Section from '../../components/Section';
import TableSkeleton from '../../components/TableSkeleton';
import { api, errorMessage } from '../../data/api';
import postOptions from '../../data/courseOptions';
import useList from '../../data/useList';
import useRemote from '../../data/useRemote';
import AttemptDetail from './AttemptDetail';
import AttemptTable from './AttemptTable';
import QuestionStats from './QuestionStats';

/**
 * Save text as a file in the browser.
 *
 * @param {string} filename File name.
 * @param {string} text     Content.
 */
function saveFile( filename, text ) {
	const url = URL.createObjectURL(
		new window.Blob( [ text ], { type: 'text/csv;charset=utf-8' } )
	);
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = filename;
	document.body.appendChild( link );
	link.click();
	link.remove();
	URL.revokeObjectURL( url );
}

/**
 * Quiz results: every attempt across lessons with filters, one attempt's
 * answers, deleting attempts, a CSV export, and per-question statistics
 * when one lesson is selected.
 */
export default function QuizResultsTab() {
	const list = useList( api.attempts );
	const courses = useRemote( api.courses );
	const course = list.filters.course || '';
	const lessonsFetcher = useCallback(
		() => api.lessons( course ),
		[ course ]
	);
	const lessons = useRemote( lessonsFetcher );
	const [ selected, setSelected ] = useState( [] );
	const [ viewing, setViewing ] = useState( null );
	const [ isExporting, setIsExporting ] = useState( false );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	const done = ( count ) => {
		createSuccessNotice(
			sprintf(
				/* translators: %d: number of deleted attempts. */
				_n(
					'%d attempt deleted.',
					'%d attempts deleted.',
					count,
					'lw-lms'
				),
				count
			),
			{ type: 'snackbar' }
		);
		setSelected( [] );
		return list.reload();
	};
	const fail = ( e ) =>
		createErrorNotice( errorMessage( e ), { type: 'snackbar' } );

	const remove = ( ids ) =>
		( ids.length === 1
			? api.deleteAttempt( ids[ 0 ] )
			: api.deleteAttempts( ids )
		).then( ( r ) => done( r.deleted ), fail );

	const removeLesson = () =>
		api
			.deleteLessonAttempts( list.filters.lesson )
			.then( ( r ) => done( r.deleted ), fail );

	const exportCsv = async () => {
		setIsExporting( true );
		try {
			const { page, ...filters } = list.filters;
			const result = await api.exportAttempts( filters );
			saveFile( result.filename, result.csv );
			if ( result.truncated ) {
				createErrorNotice(
					sprintf(
						/* translators: %d: number of rows in the file. */
						__(
							'The file holds the newest %d attempts. Narrow the filters to export the rest.',
							'lw-lms'
						),
						result.rows
					),
					{ type: 'snackbar' }
				);
			}
		} catch ( e ) {
			fail( e );
		}
		setIsExporting( false );
	};

	const setFilter = ( key, value ) => {
		setSelected( [] );
		if ( key === 'course' ) {
			list.setFilters( { course: value, lesson: '' } );
			return;
		}
		list.setFilter( key, value );
	};

	const lessonTitle =
		( lessons.data || [] ).find(
			( l ) => String( l.id ) === String( list.filters.lesson )
		)?.title || __( 'Lesson', 'lw-lms' );

	let body;
	if ( list.error ) {
		body = <LoadError message={ list.error } onRetry={ list.reload } />;
	} else if ( list.isLoading ) {
		body = <TableSkeleton />;
	} else if ( list.data.items.length === 0 ) {
		body = (
			<p className="lw-lms-empty">
				{ __( 'No quiz attempts match these filters.', 'lw-lms' ) }
			</p>
		);
	} else {
		body = (
			<AttemptTable
				rows={ list.data.items }
				selected={ selected }
				onSelect={ setSelected }
				onView={ setViewing }
				onDelete={ remove }
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
						onChange={ ( value ) => setFilter( 'search', value ) }
					/>
					<FilterSelect
						label={ __( 'Course', 'lw-lms' ) }
						value={ course }
						options={ postOptions(
							courses.data,
							__( 'All courses', 'lw-lms' )
						) }
						onChange={ ( value ) => setFilter( 'course', value ) }
					/>
					<FilterSelect
						label={ __( 'Lesson', 'lw-lms' ) }
						value={ list.filters.lesson }
						options={ postOptions(
							lessons.data,
							__( 'All lessons', 'lw-lms' )
						) }
						onChange={ ( value ) => setFilter( 'lesson', value ) }
					/>
					<FilterSelect
						label={ __( 'Result', 'lw-lms' ) }
						value={ list.filters.passed }
						options={ [
							{ value: '', label: __( 'Any result', 'lw-lms' ) },
							{ value: '1', label: __( 'Passed', 'lw-lms' ) },
							{ value: '0', label: __( 'Not passed', 'lw-lms' ) },
						] }
						onChange={ ( value ) => setFilter( 'passed', value ) }
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						className="lw-lms-filter"
						type="date"
						label={ __( 'From', 'lw-lms' ) }
						value={ list.filters.from || '' }
						onChange={ ( value ) => setFilter( 'from', value ) }
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						className="lw-lms-filter"
						type="date"
						label={ __( 'To', 'lw-lms' ) }
						value={ list.filters.to || '' }
						onChange={ ( value ) => setFilter( 'to', value ) }
					/>
				</div>
				<div className="lw-lms-toolbar">
					<div className="lw-admin-inline">
						{ selected.length > 0 && (
							<ConfirmButton
								__next40pxDefaultSize
								variant="secondary"
								isDestructive
								icon={ trash }
								confirmText={ __( 'Delete', 'lw-lms' ) }
								question={ sprintf(
									/* translators: %d: number of selected attempts. */
									_n(
										'Delete %d selected attempt? This cannot be undone.',
										'Delete %d selected attempts? This cannot be undone.',
										selected.length,
										'lw-lms'
									),
									selected.length
								) }
								onConfirm={ () => remove( selected ) }
							>
								{ sprintf(
									/* translators: %d: number of selected attempts. */
									__( 'Delete selected (%d)', 'lw-lms' ),
									selected.length
								) }
							</ConfirmButton>
						) }
					</div>
					<Button
						__next40pxDefaultSize
						variant="secondary"
						icon={ download }
						isBusy={ isExporting }
						disabled={ isExporting || ! list.data?.total }
						accessibleWhenDisabled
						onClick={ exportCsv }
					>
						{ __( 'Export CSV', 'lw-lms' ) }
					</Button>
				</div>
				{ body }
				{ list.data && list.data.total > 0 && (
					<Pagination
						page={ list.page }
						pages={ list.data.pages }
						total={ list.data.total }
						onChange={ ( page ) => {
							setSelected( [] );
							list.setPage( page );
						} }
					/>
				) }
			</Section>
			{ list.data?.summary && (
				<QuestionStats
					summary={ list.data.summary }
					lesson={ lessonTitle }
					onDelete={ removeLesson }
				/>
			) }
			{ viewing && (
				<AttemptDetail
					id={ viewing }
					onClose={ () => setViewing( null ) }
				/>
			) }
		</>
	);
}
