/**
 * Field shorthands bound to the settings store, so the tabs stay
 * declarative. Every row shows the server's validation messages for its key
 * and renders nothing when the option does not exist.
 */
/**
 * WordPress dependencies
 */
import { SelectControl, TextControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import SettingRow from './SettingRow';

/**
 * Select row (title + help left, select right).
 *
 * @param {Object}   props
 * @param {string}   props.title   Title.
 * @param {Element}  props.help    Help.
 * @param {Object}   props.store   Settings store.
 * @param {string}   props.name    Option key.
 * @param {Object[]} props.options { value, label }.
 */
export function SelectRow( { title, help, store, name, options } ) {
	if ( ! store.has( name ) ) {
		return null;
	}

	return (
		<SettingRow
			title={ title }
			help={ help }
			errors={ store.errors[ name ] }
		>
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ title }
				hideLabelFromVision
				value={ String( store.data.options[ name ] ?? '' ) }
				options={ options }
				onChange={ ( value ) => store.set( name, value ) }
			/>
		</SettingRow>
	);
}

/**
 * Whole-number row. The range comes from the server (meta.ranges); a value
 * outside it is sent as typed and the server's message shows under it.
 *
 * @param {Object}  props
 * @param {string}  props.title  Title.
 * @param {Element} props.help   Help.
 * @param {Object}  props.store  Settings store.
 * @param {string}  props.name   Option key.
 * @param {string}  props.suffix Unit after the input (e.g. %).
 */
export function NumberRow( { title, help, store, name, suffix } ) {
	if ( ! store.has( name ) ) {
		return null;
	}

	const range = store.data.meta.ranges?.[ name ] || {};

	return (
		<SettingRow
			title={ title }
			help={ help }
			errors={ store.errors[ name ] }
		>
			<div className="lw-admin-inline lw-lms-number">
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					type="number"
					label={ title }
					hideLabelFromVision
					min={ range.min }
					max={ range.max }
					step={ 1 }
					value={ String( store.data.options[ name ] ?? '' ) }
					onChange={ ( value ) =>
						store.set(
							name,
							/^\d{1,9}$/.test( value )
								? parseInt( value, 10 )
								: value
						)
					}
				/>
				{ suffix && <span className="lw-admin-muted">{ suffix }</span> }
			</div>
		</SettingRow>
	);
}
