/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Select options of a course or lesson lookup; unpublished ones say so.
 *
 * @param {Object[]} posts    Lookup rows { id, title, status }.
 * @param {string}   allLabel First option ('' value).
 * @return {Object[]} { value, label }.
 */
export default function postOptions( posts, allLabel ) {
	return [
		{ value: '', label: allLabel },
		...( posts || [] ).map( ( post ) => ( {
			value: String( post.id ),
			label:
				post.status === 'publish'
					? post.title
					: sprintf(
							/* translators: 1: title, 2: post status such as draft. */
							__( '%1$s (%2$s)', 'lw-lms' ),
							post.title,
							post.status
						),
		} ) ),
	];
}
