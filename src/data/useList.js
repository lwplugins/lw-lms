/**
 * WordPress dependencies
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { errorMessage } from './api';

/**
 * Drop empty filter values, so the query string only carries real filters.
 *
 * @param {Object} filters Filters.
 * @return {Object} Query.
 */
const clean = ( filters ) =>
	Object.fromEntries(
		Object.entries( filters ).filter(
			( [ , value ] ) =>
				value !== '' && value !== null && value !== undefined
		)
	);

/**
 * A filtered, paginated server list. Changing a filter goes back to page 1.
 * The previous page stays on screen while the next one loads (no flash), and
 * a slow response never overwrites a newer one.
 *
 * @param {(query: Object) => Promise} fetcher        API call.
 * @param {Object}                     initialFilters Filters on first load.
 * @return {Object} { data, error, isLoading, filters, setFilter, setFilters, page, setPage, reload }.
 */
export default function useList( fetcher, initialFilters = {} ) {
	const [ filters, setFiltersState ] = useState( initialFilters );
	const [ page, setPage ] = useState( 1 );
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ isFetching, setIsFetching ] = useState( true );
	const latest = useRef( 0 );

	const reload = useCallback( () => {
		const ticket = ++latest.current;
		setIsFetching( true );
		setError( null );
		return fetcher( { ...clean( filters ), page } ).then(
			( result ) => {
				if ( ticket === latest.current ) {
					setData( result );
					setIsFetching( false );
				}
			},
			( e ) => {
				if ( ticket === latest.current ) {
					setError( errorMessage( e ) );
					setIsFetching( false );
				}
			}
		);
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ filters, page ] );

	useEffect( () => {
		reload();
	}, [ reload ] );

	const setFilters = ( values ) => {
		setFiltersState( ( prev ) => ( { ...prev, ...values } ) );
		setPage( 1 );
	};

	return {
		data,
		error,
		isLoading: ! data && ! error,
		isFetching,
		filters,
		setFilter: ( key, value ) => setFilters( { [ key ]: value } ),
		setFilters,
		page,
		setPage,
		reload,
	};
}
