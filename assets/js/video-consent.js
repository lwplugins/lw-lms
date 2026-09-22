/**
 * LW LMS — lesson video consent button (LW Cookie integration).
 *
 * While LW Cookie blocks embeds, an unconsented lesson video (REST
 * `video.html`) arrives in LW Cookie's own blocked form: a
 * `.lw-cookie-embed-block` placeholder followed by an iframe carrying
 * data-lw-blocked / data-lw-category / data-lw-original-src. LW Cookie's guard
 * restores such iframes in place whenever consent is saved. This script only
 * wires the placeholder button — LW Cookie attaches its handler solely to
 * placeholders it builds itself. Delegated, so players inserted after page
 * load (single-page frontends) are covered too.
 *
 * @package LightweightPlugins\LMS
 */

( function () {
	'use strict';

	document.addEventListener(
		'click',
		function ( event ) {
			var target = event.target;
			var button = target && target.closest ? target.closest( '[data-lw-lms-consent]' ) : null;
			var api    = window.LWCookie;

			if ( ! button || ! api ) {
				return;
			}

			event.preventDefault();

			// Grant just the category the player needs; LW Cookie saves and
			// logs the consent and loads the video in place, without a reload.
			if ( typeof api.acceptCategory === 'function' ) {
				api.acceptCategory( button.getAttribute( 'data-lw-lms-consent' ) );
			} else if ( typeof api.openPreferences === 'function' ) {
				api.openPreferences();
			}
		}
	);
}() );
