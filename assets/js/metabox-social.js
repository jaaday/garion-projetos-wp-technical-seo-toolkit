( function ( data ) {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var titleField = document.getElementById( 'gpseo_og_title' );
		var descriptionField = document.getElementById( 'gpseo_og_description' );
		var imageField = document.getElementById( 'gpseo_og_image' );
		var imageButton = document.getElementById( 'gpseo_og_image_button' );
		var preview = document.getElementById( 'gpseo-social-preview' );

		if ( ! preview ) {
			return;
		}

		var previewImage = preview.querySelector( '.gpseo-social-preview-image' );
		var previewTitle = preview.querySelector( '.gpseo-social-preview-title' );
		var previewDescription = preview.querySelector( '.gpseo-social-preview-description' );

		var postTitleField = document.getElementById( 'title' );
		var metaDescriptionField = document.getElementById( 'gpseo_meta_description' );

		function updatePreview() {
			var title = ( titleField.value || ( postTitleField ? postTitleField.value : '' ) ).trim();
			var description = ( descriptionField.value || ( metaDescriptionField ? metaDescriptionField.value : '' ) ).trim();

			if ( previewTitle ) {
				previewTitle.textContent = title;
			}
			if ( previewDescription ) {
				previewDescription.textContent = description;
			}
			if ( previewImage ) {
				previewImage.style.backgroundImage = imageField.value ? 'url(' + imageField.value + ')' : 'none';
			}
		}

		[ titleField, descriptionField, imageField ].forEach( function ( field ) {
			if ( field ) {
				field.addEventListener( 'input', updatePreview );
			}
		} );

		if ( imageButton && window.wp && window.wp.media ) {
			imageButton.addEventListener( 'click', function ( e ) {
				e.preventDefault();

				var frame = window.wp.media( {
					title: data.i18n.chooseImage,
					button: { text: data.i18n.useImage },
					multiple: false,
				} );

				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					imageField.value = attachment.url;
					updatePreview();
				} );

				frame.open();
			} );
		}
	} );
} )( window.gpseoMetaboxData || { i18n: {} } );
