/**
 * LW LMS Course Builder JavaScript
 */
(function ($) {
	'use strict';

	var CourseBuilder = {
		$contentList: null,
		$sectionsInput: null,
		$previewInput: null,
		sections: [],
		previewLessons: [],

		init: function () {
			this.$contentList   = $( '#lw-lms-content-list' );
			this.$sectionsInput = $( '#lw-lms-course-sections' );
			this.$previewInput  = $( '#lw-lms-preview-lessons' );

			if ( ! this.$contentList.length) {
				return;
			}

			this.loadData();
			this.initSortable();
			this.bindEvents();
		},

		loadData: function () {
			try {
				this.sections = JSON.parse( this.$sectionsInput.val() || '[]' );
			} catch (e) {
				this.sections = [];
			}

			try {
				this.previewLessons = JSON.parse( this.$previewInput.val() || '[]' );
			} catch (e) {
				this.previewLessons = [];
			}
		},

		initSortable: function () {
			var self = this;

			// Sections sortable
			this.$contentList.sortable(
				{
					items: '.lw-lms-section, .lw-lms-lesson:not(.in-section)',
					handle: '.lw-lms-drag-handle',
					placeholder: 'ui-sortable-placeholder',
					tolerance: 'pointer',
					update: function () {
						self.updateOrder();
					}
				}
			);

			// Lessons sortable within sections
			$( '.lw-lms-section-lessons' ).sortable(
				{
					items: '.lw-lms-lesson',
					handle: '.lw-lms-drag-handle',
					placeholder: 'ui-sortable-placeholder',
					connectWith: '.lw-lms-section-lessons, #lw-lms-content-list',
					tolerance: 'pointer',
					update: function (event, ui) {
						self.updateLessonSection( ui.item );
						self.updateOrder();
					}
				}
			);
		},

		bindEvents: function () {
			var self = this;

			// Add section
			$( document ).on(
				'click',
				'.lw-lms-add-section',
				function (e) {
					e.preventDefault();
					self.addSection();
				}
			);

			// Edit section
			$( document ).on(
				'click',
				'.lw-lms-edit-section',
				function (e) {
					e.preventDefault();
					var $section = $( this ).closest( '.lw-lms-section' );
					self.editSection( $section );
				}
			);

			// Delete section
			$( document ).on(
				'click',
				'.lw-lms-delete-section',
				function (e) {
					e.preventDefault();
					if (confirm( lwLmsAdmin.i18n.confirmDelete )) {
						var $section = $( this ).closest( '.lw-lms-section' );
						self.deleteSection( $section );
					}
				}
			);

			// Preview checkbox
			$( document ).on(
				'change',
				'.lw-lms-preview-checkbox',
				function () {
					var lessonId = parseInt( $( this ).data( 'lesson-id' ) );
					self.togglePreview( lessonId, $( this ).is( ':checked' ) );
				}
			);
		},

		addSection: function () {
			var id    = 'sec_' + Date.now();
			var title = lwLmsAdmin.i18n.newSection;

			var section = {
				id: id,
				title: title,
				description: '',
				order: this.sections.length + 1
			};

			this.sections.push( section );
			this.saveData();

			// Add to DOM
			var template = $( '#lw-lms-section-template' ).html();
			template     = template.replace( /{{id}}/g, id );
			template     = template.replace( /{{title}}/g, title );

			var $section = $( template );
			this.$contentList.append( $section );

			// Make lessons sortable
			$section.find( '.lw-lms-section-lessons' ).sortable(
				{
					items: '.lw-lms-lesson',
					handle: '.lw-lms-drag-handle',
					placeholder: 'ui-sortable-placeholder',
					connectWith: '.lw-lms-section-lessons, #lw-lms-content-list',
					tolerance: 'pointer',
					update: function (event, ui) {
						CourseBuilder.updateLessonSection( ui.item );
						CourseBuilder.updateOrder();
					}
				}
			);

			// Start editing
			this.editSection( $section );
		},

		editSection: function ($section) {
			var sectionId = $section.data( 'section-id' );
			var section   = this.sections.find(
				function (s) {
					return s.id === sectionId; }
			);

			if ( ! section) {
				return;
			}

			var $header      = $section.find( '.lw-lms-section-header' );
			var currentTitle = section.title;

			var $form = $(
				'<div class="lw-lms-section-edit-form">' +
				'<input type="text" value="' + currentTitle + '" placeholder="Section title">' +
				'<button type="button" class="button button-primary lw-lms-save-section">Save</button>' +
				'<button type="button" class="button lw-lms-cancel-section">Cancel</button>' +
				'</div>'
			);

			$header.hide();
			$section.prepend( $form );

			$form.find( 'input' ).focus().select();

			// Save handler
			$form.find( '.lw-lms-save-section' ).on(
				'click',
				function () {
					var newTitle = $form.find( 'input' ).val().trim();
					if (newTitle) {
						section.title = newTitle;
						CourseBuilder.saveData();
						$section.find( '.lw-lms-section-title' ).text( newTitle );
					}
					$form.remove();
					$header.show();
				}
			);

			// Cancel handler
			$form.find( '.lw-lms-cancel-section' ).on(
				'click',
				function () {
					$form.remove();
					$header.show();
				}
			);

			// Enter key
			$form.find( 'input' ).on(
				'keypress',
				function (e) {
					if (e.which === 13) {
						$form.find( '.lw-lms-save-section' ).click();
					}
				}
			);
		},

		deleteSection: function ($section) {
			var sectionId = $section.data( 'section-id' );

			// Move lessons out of section
			var $lessons = $section.find( '.lw-lms-lesson' );
			$lessons.each(
				function () {
					$( this ).insertBefore( $section );
				}
			);

			// Remove from data
			this.sections = this.sections.filter(
				function (s) {
					return s.id !== sectionId;
				}
			);

			this.saveData();
			$section.remove();
			this.updateOrder();
		},

		updateLessonSection: function ($lesson) {
			var lessonId  = $lesson.data( 'lesson-id' );
			var $parent   = $lesson.parent();
			var sectionId = $parent.data( 'section-id' ) || '';

			// This would need AJAX to update the lesson meta
			// For now, we'll handle this on form submit
		},

		updateOrder: function () {
			// Update sections order
			var order = 1;
			this.$contentList.find( '.lw-lms-section' ).each(
				function () {
					var sectionId = $( this ).data( 'section-id' );
					var section   = CourseBuilder.sections.find(
						function (s) {
							return s.id === sectionId; }
					);
					if (section) {
						section.order = order++;
					}
				}
			);

			this.saveData();
		},

		togglePreview: function (lessonId, isPreview) {
			if (isPreview) {
				if (this.previewLessons.indexOf( lessonId ) === -1) {
					this.previewLessons.push( lessonId );
				}
			} else {
				this.previewLessons = this.previewLessons.filter(
					function (id) {
						return id !== lessonId;
					}
				);
			}

			this.$previewInput.val( JSON.stringify( this.previewLessons ) );
		},

		saveData: function () {
			this.$sectionsInput.val( JSON.stringify( this.sections ) );
		}
	};

	// Initialize on document ready
	$( document ).ready(
		function () {
			CourseBuilder.init();
		}
	);

})( jQuery );
