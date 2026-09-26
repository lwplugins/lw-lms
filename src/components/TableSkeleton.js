/**
 * Internal dependencies
 */
import { SkeletonRegion, SkeletonRows } from './skeleton';

/**
 * Placeholder rows for a list while its first page loads.
 *
 * @param {Object} props
 * @param {number} props.rows Row count.
 */
export default function TableSkeleton( { rows = 6 } ) {
	return (
		<SkeletonRegion>
			<SkeletonRows count={ rows } />
		</SkeletonRegion>
	);
}
