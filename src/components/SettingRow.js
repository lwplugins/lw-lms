/**
 * Two-column settings row: title + help on the left, the control on the right.
 * Controls inside hide their own label from sight (hideLabelFromVision) or are
 * tied to the title through `htmlFor`, so each field has one accessible name.
 *
 * It also shows the server's validation messages for the option.
 *
 * @param {Object}   props
 * @param {string}   props.title    Visible title.
 * @param {Element}  props.help     Description under the title.
 * @param {string}   props.htmlFor  Id of the control the title labels.
 * @param {boolean}  props.stacked  Control goes under the text (wide controls).
 * @param {string[]} props.errors   Server validation messages.
 * @param {Element}  props.children The control(s).
 */
export default function SettingRow( {
	title,
	help,
	htmlFor,
	stacked = false,
	errors = [],
	children,
} ) {
	const Title = htmlFor ? 'label' : 'span';
	const errorId = htmlFor ? `${ htmlFor }-errors` : undefined;

	return (
		<div
			className={ `lw-admin-row ${ stacked ? 'is-stacked' : '' } ${
				errors.length ? 'has-error' : ''
			}` }
		>
			<div className="lw-admin-row__text">
				<Title className="lw-admin-row__title" htmlFor={ htmlFor }>
					{ title }
				</Title>
				{ help && <p className="lw-admin-row__help">{ help }</p> }
			</div>
			<div className="lw-admin-row__control">
				{ children }
				{ errors.length > 0 && (
					<ul className="lw-admin-fielderror" id={ errorId }>
						{ errors.map( ( message ) => (
							<li key={ message }>{ message }</li>
						) ) }
					</ul>
				) }
			</div>
		</div>
	);
}
