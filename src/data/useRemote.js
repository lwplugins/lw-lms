/**
 * WordPress dependencies
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { errorMessage } from './api';

/**
 * Load one read-only resource (skeleton while loading, retry on failure).
 *
 * Reloads whenever the fetcher changes, so pass a stable function (an api
 * method, or one wrapped in useCallback with its inputs as dependencies).
 * A slow response never overwrites a newer one.
 *
 * @param {() => Promise} fetcher API call.
 * @return {Object} { data, error, isLoading, reload }.
 */
export default function useRemote( fetcher ) {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( null );
	const latest = useRef( 0 );

	const reload = useCallback( () => {
		const ticket = ++latest.current;
		setError( null );
		return fetcher().then(
			( result ) => {
				if ( ticket === latest.current ) {
					setData( result );
				}
			},
			( e ) => {
				if ( ticket === latest.current ) {
					setError( errorMessage( e ) );
				}
			}
		);
	}, [ fetcher ] );

	useEffect( () => {
		reload();
	}, [ reload ] );

	return { data, error, isLoading: ! data && ! error, reload };
}
