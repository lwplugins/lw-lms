/**
 * LW LMS Admin JavaScript
 */
(function ($) {
	'use strict';

	// Tab switching
	function initTabs() {
		$( '.lw-lms-tabs a' ).on(
			'click',
			function (e) {
				e.preventDefault();
				var target = $( this ).attr( 'href' ).substring( 1 );

				// Update active states
				$( '.lw-lms-tabs a' ).removeClass( 'active' );
				$( this ).addClass( 'active' );

				// Show target panel
				$( '.lw-lms-tab-panel' ).removeClass( 'active' );
				$( '#tab-' + target ).addClass( 'active' );
			}
		);
	}

	// Attachment handling
	function initAttachments() {
		// Add attachment
		$( document ).on(
			'click',
			'.lw-lms-add-attachment, #lw-lms-add-attachment, #lw-lms-add-lesson-attachment',
			function (e) {
				e.preventDefault();

				var $list    = $( this ).siblings( '.lw-lms-attachments-list' );
				var $data    = $( this ).siblings( 'input[type="hidden"]' );
				var isCourse = $( this ).attr( 'id' ) === 'lw-lms-add-attachment';

				var frame = wp.media(
					{
						title: lwLmsAdmin.i18n.selectFile || 'Select File',
						multiple: false,
						library: { type: '' }
					}
				);

				frame.on(
					'select',
					function () {
						var attachment = frame.state().get( 'selection' ).first().toJSON();

						var attachments = [];
						try {
							attachments = JSON.parse( $data.val() || '[]' );
						} catch (e) {
							attachments = [];
						}

						attachments.push(
							{
								id: attachment.id,
								title: attachment.title,
								description: ''
							}
						);

						$data.val( JSON.stringify( attachments ) );

						// Add row
						var $row = $(
							'<div class="lw-lms-attachment-row" data-attachment-id="' + attachment.id + '">' +
							'<span class="lw-lms-attachment-name">' + attachment.filename + '</span>' +
							'<button type="button" class="button-link lw-lms-remove-attachment">Remove</button>' +
							'</div>'
						);

						$list.append( $row );
					}
				);

				frame.open();
			}
		);

		// Remove attachment
		$( document ).on(
			'click',
			'.lw-lms-remove-attachment',
			function (e) {
				e.preventDefault();

				if ( ! confirm( lwLmsAdmin.i18n.confirmDelete )) {
					return;
				}

				var $row  = $( this ).closest( '.lw-lms-attachment-row' );
				var $list = $row.parent();
				var $data = $list.siblings( 'input[type="hidden"]' );
				var id    = $row.data( 'attachment-id' );

				var attachments = [];
				try {
					attachments = JSON.parse( $data.val() || '[]' );
				} catch (e) {
					attachments = [];
				}

				attachments = attachments.filter(
					function (a) {
						return a.id !== id;
					}
				);

				$data.val( JSON.stringify( attachments ) );
				$row.remove();
			}
		);
	}

	// Initialize on document ready
	$( document ).ready(
		function () {
			initTabs();
			initAttachments();
		}
	);

})( jQuery );
