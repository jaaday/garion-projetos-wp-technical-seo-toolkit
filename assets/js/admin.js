( function ( wp, data ) {
	'use strict';

	if ( ! wp || ! wp.apiFetch ) {
		return;
	}

	if ( data.restNonce && wp.apiFetch.createNonceMiddleware ) {
		wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( data.restNonce ) );
	}

	var i18n = data.i18n || {};

	/**
	 * Minimal accessible modal used for destructive-action confirmations and
	 * the optional "ignore reason" prompt, replacing native confirm()/prompt()
	 * so both look consistent with the rest of the admin UI.
	 *
	 * options: { title, message, withReason (bool), confirmLabel, cancelLabel }
	 * Resolves with `false` when cancelled, or `true` (or the reason string
	 * when withReason is set) when confirmed.
	 */
	function gpseoModal( options ) {
		return new Promise( function ( resolve ) {
			var overlay = document.createElement( 'div' );
			overlay.className = 'gpseo-modal-overlay';

			var box = document.createElement( 'div' );
			box.className = 'gpseo-modal';
			box.setAttribute( 'role', 'dialog' );
			box.setAttribute( 'aria-modal', 'true' );

			var titleEl = document.createElement( 'h2' );
			titleEl.textContent = options.title || i18n.confirmTitle || 'Please confirm';
			box.appendChild( titleEl );

			if ( options.message ) {
				var messageEl = document.createElement( 'p' );
				messageEl.textContent = options.message;
				box.appendChild( messageEl );
			}

			var textarea = null;
			if ( options.withReason ) {
				textarea = document.createElement( 'textarea' );
				textarea.rows = 3;
				textarea.setAttribute( 'aria-label', options.title || '' );
				box.appendChild( textarea );
			}

			var actions = document.createElement( 'div' );
			actions.className = 'gpseo-modal__actions';

			var cancelButton = document.createElement( 'button' );
			cancelButton.type = 'button';
			cancelButton.className = 'button';
			cancelButton.textContent = options.cancelLabel || i18n.cancel || 'Cancel';

			var confirmButton = document.createElement( 'button' );
			confirmButton.type = 'button';
			confirmButton.className = 'button button-primary';
			confirmButton.textContent = options.confirmLabel || i18n.confirm || 'Confirm';

			actions.appendChild( cancelButton );
			actions.appendChild( confirmButton );
			box.appendChild( actions );
			overlay.appendChild( box );
			document.body.appendChild( overlay );

			var previouslyFocused = document.activeElement;

			function close( result ) {
				document.removeEventListener( 'keydown', onKeydown );
				overlay.remove();
				if ( previouslyFocused && previouslyFocused.focus ) {
					previouslyFocused.focus();
				}
				resolve( result );
			}

			function onKeydown( event ) {
				if ( 'Escape' === event.key ) {
					close( false );
				}
			}

			overlay.addEventListener( 'mousedown', function ( event ) {
				if ( event.target === overlay ) {
					close( false );
				}
			} );

			cancelButton.addEventListener( 'click', function () {
				close( false );
			} );

			confirmButton.addEventListener( 'click', function () {
				close( options.withReason ? ( textarea.value || '' ) : true );
			} );

			document.addEventListener( 'keydown', onKeydown );
			confirmButton.focus();
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {

		/* Destructive links (delete redirect, delete 404, ...) get a modal instead of a raw confirm(). */
		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest ? event.target.closest( '[data-gpseo-confirm]' ) : null;
			if ( ! trigger ) {
				return;
			}

			event.preventDefault();
			gpseoModal( { message: trigger.getAttribute( 'data-gpseo-confirm' ), confirmLabel: i18n.confirm, cancelLabel: i18n.cancel } ).then( function ( confirmed ) {
				if ( confirmed ) {
					window.location.href = trigger.href;
				}
			} );
		} );

		var scanButton = document.getElementById( 'gpseo-scan-now' );
		var scanMessage = document.getElementById( 'gpseo-scan-message' );

		function pollBrokenLinks() {
			wp.apiFetch( { path: '/' + data.restNamespace + '/broken-links/status?_=' + Date.now() } ).then( function ( status ) {
				if ( 'running' === status.status ) {
					scanMessage.textContent = i18n.scanning;
					window.setTimeout( pollBrokenLinks, 3000 );
				} else {
					scanMessage.textContent = i18n.done;
					window.location.reload();
				}
			} );
		}

		if ( scanButton ) {
			if ( '1' === scanButton.getAttribute( 'data-scanning' ) ) {
				pollBrokenLinks();
			}
			scanButton.addEventListener( 'click', function () {
				scanButton.disabled = true;
				scanMessage.textContent = i18n.scanning;
				wp.apiFetch( { path: '/' + data.restNamespace + '/broken-links/scan', method: 'POST' } ).then( pollBrokenLinks );
			} );
		}

		var startButton = document.getElementById( 'gpseo-start-audit' );
		var cancelButton = document.getElementById( 'gpseo-cancel-audit' );
		var progressBox = document.getElementById( 'gpseo-audit-progress' );
		var auditMessage = document.getElementById( 'gpseo-audit-message' );
		var pollTimer = 0;

		function showAuditError( error ) {
			if ( auditMessage ) {
				auditMessage.textContent = error && error.message ? error.message : i18n.auditFailed;
			}
			if ( startButton ) {
				startButton.disabled = false;
			}
		}

		function pollAudit( auditId ) {
			wp.apiFetch( { path: '/' + data.restNamespace + '/audits/' + auditId + '?_=' + Date.now() } ).then( function ( audit ) {
				if ( progressBox ) {
					var progress = progressBox.querySelector( 'progress' );
					var label = progressBox.querySelector( '.gpseo-audit-progress-label' );
					progress.value = audit.progress;
					label.textContent = audit.processed_items + ' / ' + audit.total_items + ' (' + audit.progress + '%)';
				}
				if ( auditMessage ) {
					auditMessage.textContent = i18n.auditRunning;
				}
				if ( 'pending' === audit.status || 'running' === audit.status ) {
					pollTimer = window.setTimeout( function () { pollAudit( auditId ); }, 3000 );
					return;
				}
				if ( 'completed' === audit.status ) {
					if ( auditMessage ) { auditMessage.textContent = i18n.auditDone; }
					window.setTimeout( function () { window.location.reload(); }, 800 );
					return;
				}
				showAuditError( { message: audit.error_message || i18n.auditFailed } );
			} ).catch( showAuditError );
		}

		if ( progressBox ) {
			var activeId = parseInt( progressBox.getAttribute( 'data-audit-id' ), 10 );
			if ( activeId > 0 ) {
				progressBox.hidden = false;
				pollAudit( activeId );
			}
		}

		if ( startButton ) {
			startButton.addEventListener( 'click', function () {
				startButton.disabled = true;
				if ( auditMessage ) { auditMessage.textContent = i18n.auditRunning; }
				wp.apiFetch( { path: '/' + data.restNamespace + '/audits', method: 'POST' } ).then( function ( response ) {
					if ( progressBox ) {
						progressBox.hidden = false;
						progressBox.setAttribute( 'data-audit-id', response.audit_id );
					}
					pollAudit( response.audit_id );
				} ).catch( showAuditError );
			} );
		}

		if ( cancelButton ) {
			cancelButton.addEventListener( 'click', function () {
				var auditId = progressBox ? parseInt( progressBox.getAttribute( 'data-audit-id' ), 10 ) : 0;
				if ( ! auditId ) { return; }
				cancelButton.disabled = true;
				window.clearTimeout( pollTimer );
				wp.apiFetch( { path: '/' + data.restNamespace + '/audits/' + auditId + '/cancel', method: 'POST' } ).then( function () {
					window.location.reload();
				} ).catch( showAuditError );
			} );
		}

		document.querySelectorAll( '.gpseo-issue-action, .gpseo-issue-status' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var issueId = parseInt( button.getAttribute( 'data-issue-id' ), 10 );
				var action = button.getAttribute( 'data-action' );

				function run( body ) {
					button.disabled = true;
					wp.apiFetch( { path: '/' + data.restNamespace + '/issues/' + issueId + '/' + action, method: 'POST', data: body || {} } ).then( function () {
						window.location.reload();
					} ).catch( function ( error ) {
						button.disabled = false;
						window.alert( error && error.message ? error.message : i18n.auditFailed ); // eslint-disable-line no-alert -- REST failures still need a blocking, unmissable notice.
					} );
				}

				if ( 'ignore' === action ) {
					gpseoModal( {
						title: i18n.ignoreReason,
						message: i18n.ignoreReasonHelp,
						withReason: true,
						confirmLabel: i18n.confirm,
						cancelLabel: i18n.cancel,
					} ).then( function ( reason ) {
						if ( false === reason ) { return; }
						run( { reason: reason } );
					} );
					return;
				}

				run( {} );
			} );
		} );

		document.querySelectorAll( '.gpseo-rerun-audit' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var postId = parseInt( button.getAttribute( 'data-post-id' ), 10 );
				var path = postId > 0 ? '/contents/' + postId + '/audit' : '/audits';
				button.disabled = true;
				wp.apiFetch( { path: '/' + data.restNamespace + path, method: 'POST' } ).then( function ( response ) {
					if ( response.audit_id ) { pollAudit( response.audit_id ); } else { window.location.reload(); }
				} ).catch( function ( error ) {
					button.disabled = false;
					window.alert( error && error.message ? error.message : i18n.auditFailed ); // eslint-disable-line no-alert -- REST failures still need a blocking, unmissable notice.
				} );
			} );
		} );
	} );
} )( window.wp, window.gpseoData || {} );
