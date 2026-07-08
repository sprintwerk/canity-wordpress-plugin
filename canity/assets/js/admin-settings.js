( function () {
	'use strict';

	var embed = document.getElementById( 'canity_embed_detail' );
	var modeWrap = document.getElementById( 'canity-detail-mode-wrap' );
	var pageWrap = document.getElementById( 'canity-detail-page-wrap' );

	if ( ! embed || ! modeWrap ) {
		return;
	}

	function sync() {
		modeWrap.classList.toggle( 'hidden', ! embed.checked );
		if ( pageWrap ) {
			var pageRadio = modeWrap.querySelector( 'input[value="page"]' );
			pageWrap.classList.toggle( 'hidden', ! embed.checked || ! pageRadio || ! pageRadio.checked );
		}
	}

	embed.addEventListener( 'change', sync );
	modeWrap.addEventListener( 'change', sync );
	sync();
} )();
