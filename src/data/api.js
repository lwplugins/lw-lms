/**
 * Every REST call the admin makes, in one place (lw-lms/v1, prefix /admin).
 */
/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { NAMESPACE } from './boot';

const path = ( route, query ) =>
	addQueryArgs( `/${ NAMESPACE }/admin${ route }`, query || {} );
const get = ( route, query ) => apiFetch( { path: path( route, query ) } );
const post = ( route, data ) =>
	apiFetch( { path: path( route ), method: 'POST', data } );
const remove = ( route ) =>
	apiFetch( { path: path( route ), method: 'DELETE' } );

export const api = {
	// GET → { options, meta }; POST any subset of options (atomic) → same.
	settings: () => get( '/settings' ),
	saveSettings: ( patch ) => post( '/settings', patch ),

	overview: () => get( '/overview' ),

	// Lookups for filters and pickers.
	courses: () => get( '/lookups/courses' ),
	lessons: ( course ) => get( '/lookups/lessons', course ? { course } : {} ),
	users: ( search ) => get( '/lookups/users', { search } ),

	// Enrollments: list → { items, total, page, perPage, pages }.
	enrollments: ( query ) => get( '/enrollments', query ),
	grant: ( body ) => post( '/enrollments', body ),
	revoke: ( userId, courseId ) =>
		remove( `/enrollments/${ userId }/${ courseId }` ),

	// Quiz attempts: list → { items, total, page, perPage, pages, summary }.
	attempts: ( query ) => get( '/quiz-attempts', query ),
	attempt: ( id ) => get( `/quiz-attempts/${ id }` ),
	deleteAttempt: ( id ) => remove( `/quiz-attempts/${ id }` ),
	deleteAttempts: ( ids ) => post( '/quiz-attempts/delete', { ids } ),
	deleteLessonAttempts: ( lessonId ) =>
		remove( `/lessons/${ lessonId }/quiz-attempts` ),
	exportAttempts: ( query ) => get( '/quiz-attempts/export', query ),
};

/**
 * Human message of a failed request.
 *
 * @param {Object} error apiFetch rejection.
 * @return {string} Message.
 */
export const errorMessage = ( error ) =>
	error?.message ||
	__( 'That did not work. Reload the page and try again.', 'lw-lms' );

/**
 * Per-field validation errors of a `400 lw_lms_invalid` response.
 *
 * @param {Object} error apiFetch rejection.
 * @return {Object|null} { key: [ messages ] } or null.
 */
export function fieldErrors( error ) {
	const fields = error?.data?.fields;
	if ( ! fields || typeof fields !== 'object' ) {
		return null;
	}
	return Object.fromEntries(
		Object.entries( fields ).map( ( [ key, messages ] ) => [
			key,
			( Array.isArray( messages ) ? messages : [ messages ] ).map(
				String
			),
		] )
	);
}
