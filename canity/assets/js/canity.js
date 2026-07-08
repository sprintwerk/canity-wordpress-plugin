( function () {
	'use strict';

	var config = window.canityConfig || {};
	var restUrl = config.restUrl || '';
	var hashPrefix = config.hashPrefix || 'canity-detail';
	var i18n = config.i18n || {};

	var modal = null;
	var modalPanel = null;
	var modalTitle = null;
	var modalBody = null;
	var termsModal = null;
	var termsModalPanel = null;
	var termsModalBody = null;
	var lastTrigger = null;
	var lastTermsTrigger = null;
	var lastInlineTrigger = null;
	var openInline = null;
	var cache = {};

	function parseHash() {
		var hash = window.location.hash.replace( /^#/, '' );
		var parts = hash.split( '/' );
		if ( parts.length !== 3 || parts[0] !== hashPrefix ) {
			return null;
		}
		return { type: parts[1], id: parts[2] };
	}

	function setHash( type, id ) {
		var next = '#' + hashPrefix + '/' + type + '/' + id;
		if ( window.location.hash !== next ) {
			window.history.pushState( null, '', next );
		}
	}

	function clearHash() {
		if ( window.location.hash.indexOf( '#' + hashPrefix + '/' ) === 0 ) {
			window.history.replaceState( null, '', window.location.pathname + window.location.search );
		}
	}

	function fetchDetail( type, id ) {
		var key = type + ':' + id;
		if ( cache[ key ] ) {
			return Promise.resolve( cache[ key ] );
		}

		var url = restUrl + '?type=' + encodeURIComponent( type ) + '&id=' + encodeURIComponent( id );
		var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
		var timeoutId = controller
			? window.setTimeout( function () {
				controller.abort();
			}, 30000 )
			: null;

		return fetch( url, {
			headers: { Accept: 'application/json' },
			credentials: 'same-origin',
			signal: controller ? controller.signal : undefined,
		} )
			.then( function ( response ) {
				if ( timeoutId ) {
					window.clearTimeout( timeoutId );
				}
				if ( ! response.ok ) {
					throw new Error( 'fetch failed' );
				}
				return response.json();
			} )
			.then( function ( data ) {
				cache[ key ] = data;
				return data;
			} )
			.catch( function ( error ) {
				if ( timeoutId ) {
					window.clearTimeout( timeoutId );
				}
				throw error;
			} );
	}

	function getInlinePanel( type, id ) {
		return document.getElementById( 'canity-inline-' + type + '-' + id );
	}

	function getInlineContent( panel ) {
		if ( ! panel ) {
			return null;
		}
		return panel.querySelector( '.canity-inline-detail__content' );
	}

	function setInlineContent( panel, html ) {
		var content = getInlineContent( panel );
		if ( content ) {
			content.innerHTML = html;
		}
	}

	function closeInline( options ) {
		options = options || {};
		if ( ! openInline ) {
			return;
		}
		setInlineContent( openInline, '' );
		openInline.hidden = true;
		var trigger = document.querySelector(
			'.canity-card__trigger[data-canity-type="' + openInline.dataset.canityType + '"][data-canity-id="' + openInline.dataset.canityId + '"]'
		);
		if ( trigger ) {
			trigger.setAttribute( 'aria-expanded', 'false' );
		}
		openInline = null;
		clearHash();
		if ( ! options.skipFocus && lastInlineTrigger ) {
			lastInlineTrigger.focus();
		}
		lastInlineTrigger = null;
	}

	function closeTermsModal() {
		if ( ! termsModal || termsModal.hidden ) {
			return;
		}
		termsModal.hidden = true;
		termsModalBody.innerHTML = '';
		document.body.classList.remove( 'canity-terms-modal-open' );
		if ( lastTermsTrigger ) {
			lastTermsTrigger.focus();
			lastTermsTrigger = null;
		}
	}

	function openTermsModal( button ) {
		if ( ! termsModal || ! termsModalBody ) {
			return;
		}

		var trigger = button.closest( '.canity-detail__terms-trigger' );
		if ( ! trigger ) {
			return;
		}

		var source = trigger.querySelector( '.canity-detail__terms-source' );
		if ( ! source ) {
			return;
		}

		lastTermsTrigger = button;
		termsModalBody.innerHTML = source.innerHTML;
		termsModal.hidden = false;
		document.body.classList.add( 'canity-terms-modal-open' );
		termsModalPanel.focus();
	}

	function setModalTitle( text ) {
		if ( modalTitle ) {
			modalTitle.textContent = text;
		}
	}

	function syncModalTitleFromContent() {
		if ( ! modalBody || ! modalTitle ) {
			return;
		}

		var contentTitle = modalBody.querySelector( '.canity-detail__title' );
		if ( contentTitle ) {
			setModalTitle( contentTitle.textContent.trim() );
			contentTitle.setAttribute( 'aria-hidden', 'true' );
		} else {
			setModalTitle( i18n.detailTitle || 'Details' );
		}
	}

	function closeModal() {
		if ( ! modal || modal.hidden ) {
			return;
		}
		modal.hidden = true;
		if ( modalBody ) {
			modalBody.innerHTML = '';
		}
		setModalTitle( i18n.detailTitle || 'Details' );
		document.body.classList.remove( 'canity-modal-open' );
		clearHash();
		if ( lastTrigger ) {
			lastTrigger.focus();
			lastTrigger = null;
		}
	}

	function openModal( type, id, trigger ) {
		closeInline();
		if ( ! modal ) {
			return;
		}

		lastTrigger = trigger || null;
		modal.hidden = false;
		document.body.classList.add( 'canity-modal-open' );
		setModalTitle( i18n.loading || 'Loading…' );
		if ( modalBody ) {
			modalBody.innerHTML = '<p class="canity-detail__loading">' + escapeHtml( i18n.loading || 'Loading…' ) + '</p>';
		}
		modalPanel.focus();
		setHash( type, id );

		fetchDetail( type, id )
			.then( function ( data ) {
				if ( modalBody ) {
					modalBody.innerHTML = data.html || '';
				}
				syncModalTitleFromContent();
			} )
			.catch( function () {
				setModalTitle( i18n.error || 'Error' );
				if ( modalBody ) {
					modalBody.innerHTML = '<p class="canity-error">' + escapeHtml( i18n.error || 'Error' ) + '</p>';
				}
			} );
	}

	function openInlinePanel( type, id, trigger ) {
		var panel = getInlinePanel( type, id );
		if ( ! panel ) {
			return;
		}

		if ( openInline === panel && ! panel.hidden ) {
			closeInline();
			return;
		}

		closeModal();
		closeInline( { skipFocus: true } );

		panel.hidden = false;
		setInlineContent(
			panel,
			'<p class="canity-detail__loading">' + escapeHtml( i18n.loading || 'Loading…' ) + '</p>'
		);
		if ( trigger ) {
			trigger.setAttribute( 'aria-expanded', 'true' );
		}
		lastInlineTrigger = trigger || null;
		openInline = panel;
		setHash( type, id );

		panel.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );

		fetchDetail( type, id )
			.then( function ( data ) {
				if ( openInline !== panel ) {
					return;
				}
				setInlineContent( panel, data.html || '' );
			} )
			.catch( function () {
				if ( openInline !== panel ) {
					return;
				}
				setInlineContent(
					panel,
					'<p class="canity-error">' + escapeHtml( i18n.error || 'Error' ) + '</p>'
				);
			} );
	}

	function openFromHash() {
		var parsed = parseHash();
		if ( ! parsed ) {
			return;
		}

		var trigger = document.querySelector(
			'.canity-card__trigger[data-canity-type="' + parsed.type + '"][data-canity-id="' + parsed.id + '"]'
		);
		var grid = trigger ? trigger.closest( '.canity-grid' ) : null;
		var mode = grid ? grid.getAttribute( 'data-canity-detail-mode' ) : 'modal';

		if ( mode === 'inline' ) {
			var panel = getInlinePanel( parsed.type, parsed.id );
			if ( panel ) {
				openInlinePanel( parsed.type, parsed.id, trigger );
				return;
			}
		}

		openModal( parsed.type, parsed.id, trigger );
	}

	function escapeHtml( text ) {
		var div = document.createElement( 'div' );
		div.textContent = text;
		return div.innerHTML;
	}

	function getFocusableElements( container ) {
		if ( ! container ) {
			return [];
		}

		var selector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

		return Array.prototype.filter.call(
			container.querySelectorAll( selector ),
			function ( el ) {
				return el.offsetParent !== null;
			}
		);
	}

	function trapFocus( event, panel ) {
		if ( ! panel ) {
			return;
		}

		var focusable = getFocusableElements( panel );

		if ( focusable.length === 0 ) {
			event.preventDefault();
			panel.focus();
			return;
		}

		var first = focusable[ 0 ];
		var last = focusable[ focusable.length - 1 ];
		var active = document.activeElement;

		if ( event.shiftKey ) {
			if ( active === first || active === panel || ! panel.contains( active ) ) {
				event.preventDefault();
				last.focus();
			}
			return;
		}

		if ( active === last || active === panel || ! panel.contains( active ) ) {
			event.preventDefault();
			first.focus();
		}
	}

	function onTriggerClick( event ) {
		var trigger = event.currentTarget;
		var type = trigger.getAttribute( 'data-canity-type' );
		var id = trigger.getAttribute( 'data-canity-id' );
		if ( ! type || ! id ) {
			return;
		}

		var grid = trigger.closest( '.canity-grid' );
		var mode = grid ? grid.getAttribute( 'data-canity-detail-mode' ) : 'modal';

		if ( mode === 'inline' ) {
			openInlinePanel( type, id, trigger );
			return;
		}

		openModal( type, id, trigger );
	}

	function init() {
		modal = document.getElementById( 'canity-modal' );
		if ( modal ) {
			modalPanel = modal.querySelector( '.canity-modal__panel' );
			modalTitle = modal.querySelector( '#canity-modal-title' );
			modalBody = modal.querySelector( '.canity-modal__body' );

			modal.addEventListener( 'click', function ( event ) {
				if ( event.target.closest( '[data-canity-modal-close]' ) ) {
					closeModal();
				}
			} );
		}

		termsModal = document.getElementById( 'canity-terms-modal' );
		if ( termsModal ) {
			termsModalPanel = termsModal.querySelector( '.canity-modal__panel' );
			termsModalBody = termsModal.querySelector( '.canity-terms-modal__body' );

			termsModal.addEventListener( 'click', function ( event ) {
				if ( event.target.closest( '[data-canity-terms-close]' ) ) {
					closeTermsModal();
				}
			} );
		}

		document.addEventListener( 'click', function ( event ) {
			var termsUrlButton = event.target.closest( '[data-canity-terms-url]' );
			if ( termsUrlButton ) {
				event.preventDefault();
				var termsUrl = termsUrlButton.getAttribute( 'data-canity-terms-url' );
				if ( termsUrl ) {
					window.open( termsUrl, '_blank', 'noopener,noreferrer' );
				}
				return;
			}

			if ( event.target.closest( '[data-canity-terms-open]' ) ) {
				event.preventDefault();
				openTermsModal( event.target.closest( '[data-canity-terms-open]' ) );
				return;
			}

			if ( event.target.closest( '[data-canity-inline-close]' ) ) {
				event.preventDefault();
				closeInline();
				return;
			}

			var card = event.target.closest( '.canity-card--triggerable' );
			if ( card ) {
				var trigger = card.querySelector(
					'.canity-card__trigger[data-canity-type][data-canity-id]'
				);
				if ( trigger ) {
					event.preventDefault();
					onTriggerClick( { currentTarget: trigger } );
				}
				return;
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Escape' ) {
				if ( termsModal && ! termsModal.hidden ) {
					closeTermsModal();
					return;
				}
				if ( modal && ! modal.hidden ) {
					closeModal();
					return;
				}
				if ( openInline ) {
					closeInline();
				}
				return;
			}

			if ( event.key !== 'Tab' ) {
				return;
			}

			if ( termsModal && ! termsModal.hidden ) {
				trapFocus( event, termsModalPanel );
				return;
			}

			if ( modal && ! modal.hidden ) {
				trapFocus( event, modalPanel );
			}
		} );

		window.addEventListener( 'hashchange', openFromHash );
		openFromHash();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
