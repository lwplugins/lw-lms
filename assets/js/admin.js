/**
 * LW LMS Admin JavaScript
 */
(function ($) {
	'use strict';

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

	/**
	 * Lesson screen: the section select follows the chosen course.
	 */
	function initLessonSections() {
		var $box = $( '.lw-lms-lesson-course' );

		if ( ! $box.length) {
			return;
		}

		var sections  = $box.data( 'sections' ) || {};
		var $course   = $box.find( '#lw_lms_lesson_course_id' );
		var $wrap     = $box.find( '.lw-lms-section-select' );
		var $section  = $box.find( '#lw_lms_lesson_section_id' );
		var $noneItem = $section.find( 'option[value=""]' ).first().clone();

		$course.on(
			'change',
			function () {
				var courseId = $( this ).val();
				var list     = sections[ courseId ] || [];

				$section.empty().append( $noneItem.clone() );

				list.forEach(
					function (item) {
						$section.append( $( '<option>' ).val( item.id ).text( item.title ) );
					}
				);

				$section.val( '' );
				$wrap.toggle( !! courseId );
			}
		);
	}

	$( document ).ready(
		function () {
			initAttachments();
			initLessonSections();
		}
	);

})( jQuery );
