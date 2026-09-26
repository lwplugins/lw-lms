/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { chevronLeft, chevronRight } from '@wordpress/icons';

/**
 * Result count plus previous / next page buttons (hidden on a single page).
 *
 * @param {Object}                 props
 * @param {number}                 props.page     Current page (1-based).
 * @param {number}                 props.pages    Page count.
 * @param {number}                 props.total    Matching rows.
 * @param {(page: number) => void} props.onChange Go to a page.
 */
export default function Pagination( { page, pages, total, onChange } ) {
	return (
		<div className="lw-lms-pager">
			<span className="lw-admin-muted">
				{ sprintf(
					/* translators: %s: number of results. */
					_n( '%s result', '%s results', total, 'lw-lms' ),
					Number( total ).toLocaleString()
				) }
			</span>
			{ pages > 1 && (
				<div className="lw-admin-inline">
					<Button
						__next40pxDefaultSize
						variant="tertiary"
						icon={ chevronLeft }
						label={ __( 'Previous page', 'lw-lms' ) }
						disabled={ page <= 1 }
						accessibleWhenDisabled
						onClick={ () => onChange( page - 1 ) }
					/>
					<span>
						{ sprintf(
							/* translators: 1: current page, 2: number of pages. */
							__( 'Page %1$d of %2$d', 'lw-lms' ),
							page,
							pages
						) }
					</span>
					<Button
						__next40pxDefaultSize
						variant="tertiary"
						icon={ chevronRight }
						label={ __( 'Next page', 'lw-lms' ) }
						disabled={ page >= pages }
						accessibleWhenDisabled
						onClick={ () => onChange( page + 1 ) }
					/>
				</div>
			) }
		</div>
	);
}
