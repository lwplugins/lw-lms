/**
 * LW LMS Admin JavaScript
 */
(function ($) {
	'use strict';

	/**
	 * Initialize settings page tabs.
	 */
	function initTabs() {
		var tabLinks  = document.querySelectorAll( '.lw-lms-tabs a' );
		var tabPanels = document.querySelectorAll( '.lw-lms-tab-panel' );

		if ( ! tabLinks.length || ! tabPanels.length) {
			return;
		}

		var hash     = window.location.hash.substring( 1 );
		var firstTab = tabLinks[0].getAttribute( 'href' ).substring( 1 );
		var validTab = false;

		tabLinks.forEach(
			function (link) {
				if (link.getAttribute( 'href' ).substring( 1 ) === hash) {
					validTab = true;
				}
			}
		);

		activateTab( validTab ? hash : firstTab );

		tabLinks.forEach(
			function (link) {
				link.addEventListener(
					'click',
					function (e) {
						e.preventDefault();
						var tabId = this.getAttribute( 'href' ).substring( 1 );
						activateTab( tabId );
						history.replaceState( null, '', '#' + tabId );
					}
				);
			}
		);

		// Preserve active tab on form submit.
		var form = document.querySelector( '.lw-lms-settings' );
		if (form) {
			form = form.closest( 'form' );
		}
		if (form) {
			form.addEventListener(
				'submit',
				function () {
					var activeLink = document.querySelector( '.lw-lms-tabs a.active' );
					if ( ! activeLink) {
						return;
					}
					var tabSlug = activeLink.getAttribute( 'href' ).substring( 1 );
					var referer = form.querySelector( 'input[name="_wp_http_referer"]' );
					if (referer && referer.value.indexOf( '#' ) === -1) {
						referer.value += '#' + tabSlug;
					}
				}
			);
		}

		function activateTab(tabId) {
			tabLinks.forEach(
				function (link) {
					var linkTabId = link.getAttribute( 'href' ).substring( 1 );
					if (linkTabId === tabId) {
						link.classList.add( 'active' );
					} else {
						link.classList.remove( 'active' );
					}
				}
			);

			tabPanels.forEach(
				function (panel) {
					if (panel.id === 'tab-' + tabId) {
						panel.classList.add( 'active' );
					} else {
						panel.classList.remove( 'active' );
					}
				}
			);
		}
	}

	/**
	 * Initialize attachment handling.
	 */
	function initAttachments() {
		$( document ).on(
			'click',
			'.lw-lms-add-attachment, #lw-lms-add-attachment, #lw-lms-add-lesson-attachment',
			function (e) {
				e.preventDefault();

				var $list    = $( this ).siblings( '.lw-lms-attachments-list' );
				var $data    = $( this ).siblings( 'input[type="hidden"]' );

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

	$( document ).ready(
		function () {
			initTabs();
			initAttachments();
		}
	);

})( jQuery );
