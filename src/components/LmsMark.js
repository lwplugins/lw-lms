/**
 * WordPress dependencies
 */
import { SVG, Path } from '@wordpress/primitives';

/**
 * LW LMS mark: a graduation cap (Font Awesome Pro duotone), inlined. Both
 * layers follow `color`, which the stylesheet sets to the brand token
 * ($lw-brand); the back layer is drawn at 40%.
 */
export default function LmsMark() {
	return (
		<SVG
			className="lw-admin-mark"
			viewBox="0 0 576 512"
			xmlns="http://www.w3.org/2000/svg"
			aria-hidden="true"
			focusable="false"
		>
			<Path
				opacity=".4"
				fill="currentColor"
				d="M0 160c0-10.7 7-19.7 16.6-22.8 2.3-.7 4.8-1.2 7.4-1.2l264 0c13.3 0 24 10.7 24 24s-10.7 24-24 24l-240 0 0 272c0 13.3-10.7 24-24 24S0 469.3 0 456L0 160z"
			/>
			<Path
				fill="currentColor"
				d="M16.6 137.2c2.3-.7 4.8-1.2 7.4-1.2l264 0c13.3 0 24 10.7 24 24s-10.7 24-24 24l-240 0 0 11.8 209.2 86.1c9.8 4 20.2 6.1 30.8 6.1s21-2.1 30.8-6.1l242.4-99.8c9-3.7 14.8-12.4 14.8-22.1s-5.8-18.4-14.8-22.1L318.8 38.1C309 34.1 298.6 32 288 32s-21 2.1-30.8 6.1L16.6 137.2zM96 267.4L96 384c0 53 86 96 192 96s192-43 192-96l0-116.6-142.9 58.9c-15.6 6.4-32.2 9.7-49.1 9.7s-33.5-3.3-49.1-9.7L96 267.4z"
			/>
		</SVG>
	);
}
