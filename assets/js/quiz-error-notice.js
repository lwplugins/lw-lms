/**
 * LW LMS: report a rejected quiz JSON in the block editor.
 *
 * The classic Quiz metabox is saved in the background after the post, and its
 * HTML is not re-rendered, so a rejected quiz used to fail silently. After
 * every metabox save (and once on load) this asks the server whether the last
 * quiz save was rejected and shows the reason as an editor notice.
 */
( function ( wp, config ) {
	'use strict';

	if ( ! wp || ! wp.data || ! wp.apiFetch || ! config ) {
		return;
	}

	var NOTICE_ID = 'lw-lms-quiz-error';

	function postId() {
		var editor = wp.data.select( 'core/editor' );
		return editor ? editor.getCurrentPostId() : 0;
	}

	function check() {
		var id = postId();

		if ( ! id ) {
			return;
		}

		wp.apiFetch( { path: config.path.replace( '%d', String( id ) ) } ).then(
			function ( response ) {
				var notices = wp.data.dispatch( 'core/notices' );

				if ( response && response.error ) {
					notices.createErrorNotice(
						config.prefix + ' ' + response.error,
						{ id: NOTICE_ID, isDismissible: true }
					);
				} else {
					notices.removeNotice( NOTICE_ID );
				}
			}
		).catch( function () {} );
	}

	var wasSaving = false;

	wp.data.subscribe(
		function () {
			var editPost = wp.data.select( 'core/edit-post' );
			var saving   = !! ( editPost && editPost.isSavingMetaBoxes && editPost.isSavingMetaBoxes() );

			if ( wasSaving && ! saving ) {
				check();
			}

			wasSaving = saving;
		}
	);

	if ( wp.domReady ) {
		wp.domReady( check );
	} else {
		window.addEventListener( 'load', check );
	}
} )( window.wp, window.lwLmsQuizNotice );
