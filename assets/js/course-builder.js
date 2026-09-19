/**
 * LW LMS Course Builder JavaScript
 */
(function ($) {
	'use strict';

	var CourseBuilder = {
		$contentList: null,
		$sectionsInput: null,
		$previewInput: null,
		$assignmentsInput: null,
		sections: [],
		previewLessons: [],

		init: function () {
			this.$contentList      = $( '#lw-lms-content-list' );
			this.$sectionsInput    = $( '#lw-lms-course-sections' );
			this.$previewInput     = $( '#lw-lms-preview-lessons' );
			this.$assignmentsInput = $( '#lw-lms-lesson-assignments' );

			if ( ! this.$contentList.length) {
				return;
			}

			this.loadData();
			this.initSortable();
			this.bindEvents();
			this.renderAllDripSummaries();

			// Always flush the current state so a save after a touch-and-restore
			// (drag → drag back) still writes the correct lesson_section_id /
			// lesson_order pairs.
			this.collectAssignments();
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
				order: this.sections.length + 1,
				drip: { mode: 'none', value: 0, unit: 'day' }
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

			var $header = $section.find( '.lw-lms-section-header' );
			var i18n    = lwLmsAdmin.i18n;
			var rule    = this.dripRule( section );

			var $form  = $( '<div class="lw-lms-section-edit-form"></div>' );
			var $title = $( '<input type="text" class="lw-lms-section-title-input">' )
				.attr( 'placeholder', i18n.sectionTitle )
				.val( section.title );

			var $mode = $( '<select class="lw-lms-section-drip-mode"></select>' );
			$.each(
				i18n.dripModes,
				function (value, label) {
					$mode.append( $( '<option></option>' ).attr( 'value', value ).text( label ) );
				}
			);
			$mode.val( rule.mode );

			var $value = $( '<input type="number" class="lw-lms-section-drip-value" min="0" max="999" step="1">' ).val( rule.value );

			var $unit = $( '<select class="lw-lms-section-drip-unit"></select>' );
			$.each(
				i18n.dripUnits,
				function (value, label) {
					$unit.append( $( '<option></option>' ).attr( 'value', value ).text( label ) );
				}
			);
			$unit.val( rule.unit );

			var $delay = $( '<span class="lw-lms-drip-delay"></span>' )
				.append( $( '<span class="lw-lms-drip-after"></span>' ).text( i18n.dripAfter ) )
				.append( $value )
				.append( $unit );

			var $save   = $( '<button type="button" class="button button-primary lw-lms-save-section"></button>' ).text( i18n.save );
			var $cancel = $( '<button type="button" class="button lw-lms-cancel-section"></button>' ).text( i18n.cancel );

			$form.append( $title ).append( $mode ).append( $delay ).append( $save ).append( $cancel );

			$header.hide();
			$section.prepend( $form );

			$title.trigger( 'focus' ).trigger( 'select' );

			$save.on(
				'click',
				function () {
					var newTitle = $title.val().trim();
					if (newTitle) {
						section.title = newTitle;
						$section.find( '.lw-lms-section-title' ).text( newTitle );
					}

					section.drip = {
						mode: $mode.val(),
						value: parseInt( $value.val(), 10 ) || 0,
						unit: $unit.val()
					};

					CourseBuilder.saveData();
					CourseBuilder.renderDripSummary( section );
					$form.remove();
					$header.show();
				}
			);

			$cancel.on(
				'click',
				function () {
					$form.remove();
					$header.show();
				}
			);

			$title.on(
				'keypress',
				function (e) {
					if (e.which === 13) {
						$save.trigger( 'click' );
					}
				}
			);
		},

		/**
		 * A section's drip rule, defaulted for sections saved before drip
		 * existed.
		 */
		dripRule: function (section) {
			var rule = section.drip || {};

			return {
				mode: rule.mode || 'none',
				value: parseInt( rule.value, 10 ) || 0,
				unit: rule.unit || 'day'
			};
		},

		/**
		 * Write the human-readable schedule next to a section's title.
		 */
		renderDripSummary: function (section) {
			var rule    = this.dripRule( section );
			var i18n    = lwLmsAdmin.i18n;
			var $target = this.$contentList.find( '.lw-lms-section-drip[data-section-id="' + section.id + '"]' );

			if ( ! $target.length) {
				return;
			}

			if (rule.mode === 'none') {
				$target.text( '' );
				return;
			}

			var template = rule.mode === 'previous' ? i18n.dripSummaryPrevious : i18n.dripSummaryEnrollment;

			if (rule.value === 0 && rule.mode === 'previous') {
				$target.text( i18n.dripSummaryPreviousNow );
				return;
			}

			var units = rule.value === 1 ? i18n.dripUnitsOne : i18n.dripUnits;

			$target.text(
				template.replace( '%1$d', rule.value ).replace( '%2$s', units[rule.unit] || rule.unit )
			);
		},

		renderAllDripSummaries: function () {
			var self = this;

			this.sections.forEach(
				function (section) {
					self.renderDripSummary( section );
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
			// Lesson moved between sections (or in/out of orphan area) — flush
			// the assignment map so save() writes the new section_id + order.
			void $lesson;
			this.collectAssignments();
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
			this.collectAssignments();
		},

		/**
		 * Walk the DOM and rebuild the lesson → section + order map. Stored on
		 * a hidden input so the metabox save() can persist lesson_section_id
		 * and lesson_order for every lesson the course currently owns.
		 */
		collectAssignments: function () {
			if ( ! this.$assignmentsInput.length) { return; }

			var assignments = [];
			var counter     = 1;

			// Top-level orphan lessons (rendered directly in #lw-lms-content-list).
			this.$contentList.children( '.lw-lms-lesson' ).each(
				function () {
					assignments.push(
						{
							lesson_id: parseInt( $( this ).data( 'lesson-id' ), 10 ),
							section_id: '',
							order: counter++
						}
					);
				}
			);

			// Lessons inside each section, in their current visual order.
			this.$contentList.find( '.lw-lms-section' ).each(
				function () {
					var sectionId = String( $( this ).data( 'section-id' ) || '' );
					$( this ).find( '.lw-lms-section-lessons > .lw-lms-lesson' ).each(
						function () {
							assignments.push(
								{
									lesson_id: parseInt( $( this ).data( 'lesson-id' ), 10 ),
									section_id: sectionId,
									order: counter++
								}
							);
						}
					);
				}
			);

			this.$assignmentsInput.val( JSON.stringify( assignments ) );
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
