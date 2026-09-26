/**
 * WordPress dependencies
 */
import { SelectControl } from '@wordpress/components';

/**
 * A compact labelled select for a list filter ('' = no filter).
 *
 * @param {Object}                  props
 * @param {string}                  props.label    Visible label.
 * @param {string}                  props.value    Current value.
 * @param {Object[]}                props.options  { value, label } (first = "All").
 * @param {(value: string) => void} props.onChange Change.
 * @param {boolean}                 props.disabled Disabled.
 */
export default function FilterSelect( {
	label,
	value,
	options,
	onChange,
	disabled = false,
} ) {
	return (
		<SelectControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			className="lw-lms-filter"
			label={ label }
			value={ String( value ?? '' ) }
			options={ options }
			disabled={ disabled }
			onChange={ onChange }
		/>
	);
}
