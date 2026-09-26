/**
 * Field shorthands bound to the settings store, so the tabs stay
 * declarative. Every row shows the server's validation messages for its key
 * and renders nothing when the option does not exist.
 */
/**
 * WordPress dependencies
 */
import { SelectControl, TextControl } from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';

/**
 * Internal dependencies
 */
import { useDescribedByRef } from './FieldErrors';
import SettingRow from './SettingRow';

/**
 * Error wiring of one row: the message list id, and the aria attributes of
 * the control (only while the option has messages).
 *
 * @param {Object}   Component Row component (instance id namespace).
 * @param {string[]} errors    Messages.
 * @return {Object} { errorId, describedBy, invalid }.
 */
function useErrorIds( Component, errors = [] ) {
	const errorId = `${ useInstanceId( Component, 'lw-lms-field' ) }-errors`;
	const has = errors.length > 0;

	return {
		errorId,
		describedBy: has ? errorId : undefined,
		invalid: has || undefined,
	};
}

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
	const errors = store.errors[ name ];
	const ids = useErrorIds( SelectRow, errors );
	const ref = useDescribedByRef( ids.describedBy );

	if ( ! store.has( name ) ) {
		return null;
	}

	return (
		<SettingRow
			title={ title }
			help={ help }
			errors={ errors }
			errorId={ ids.errorId }
		>
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				ref={ ref }
				aria-invalid={ ids.invalid }
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
	const errors = store.errors[ name ];
	const ids = useErrorIds( NumberRow, errors );

	if ( ! store.has( name ) ) {
		return null;
	}

	const range = store.data.meta.ranges?.[ name ] || {};

	return (
		<SettingRow
			title={ title }
			help={ help }
			errors={ errors }
			errorId={ ids.errorId }
		>
			<div className="lw-admin-inline lw-lms-number">
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					type="number"
					aria-describedby={ ids.describedBy }
					aria-invalid={ ids.invalid }
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
