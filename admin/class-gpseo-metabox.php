<?php
/**
 * Post-editor metabox: canonical URL override, meta description, noindex/nofollow,
 * and Open Graph / Twitter Card overrides with a live social preview.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GP_SEO_Metabox {

	const NONCE_ACTION = 'gpseo_metabox_save';
	const NONCE_NAME   = 'gpseo_metabox_nonce';

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );
		add_action( 'save_post', array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_metabox() {
		foreach ( array( 'post', 'page' ) as $screen ) {
			add_meta_box(
				'gpseo-metabox',
				__( 'Technical SEO Toolkit', 'garion-projetos-technical-seo-toolkit' ),
				array( $this, 'render' ),
				$screen,
				'normal',
				'default'
			);
		}
	}

	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'gpseo-metabox', GPSEO_URL . 'assets/js/metabox-social.js', array( 'jquery' ), GPSEO_VERSION, true );
		wp_localize_script(
			'gpseo-metabox',
			'gpseoMetaboxData',
			array(
				'i18n' => array(
					'chooseImage' => __( 'Choose image', 'garion-projetos-technical-seo-toolkit' ),
					'useImage'    => __( 'Use this image', 'garion-projetos-technical-seo-toolkit' ),
				),
			)
		);
	}

	public function render( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$meta_description = get_post_meta( $post->ID, '_gpseo_meta_description', true );
		$canonical        = get_post_meta( $post->ID, '_gpseo_canonical_url', true );
		$noindex          = get_post_meta( $post->ID, '_gpseo_noindex', true );
		$nofollow         = get_post_meta( $post->ID, '_gpseo_nofollow', true );

		$og_title       = get_post_meta( $post->ID, GP_SEO_Social_Meta::TITLE_META_KEY, true );
		$og_description = get_post_meta( $post->ID, GP_SEO_Social_Meta::DESCRIPTION_META_KEY, true );
		$og_image       = get_post_meta( $post->ID, GP_SEO_Social_Meta::IMAGE_META_KEY, true );

		$preview = GP_SEO_Social_Meta::get_data( $post );
		?>
		<p>
			<label for="gpseo_meta_description"><strong><?php esc_html_e( 'Meta description', 'garion-projetos-technical-seo-toolkit' ); ?></strong></label><br />
			<textarea id="gpseo_meta_description" name="gpseo_meta_description" rows="3" style="width:100%;" maxlength="160"><?php echo esc_textarea( $meta_description ); ?></textarea>
		</p>
		<p>
			<label for="gpseo_canonical_url"><strong><?php esc_html_e( 'Canonical URL override', 'garion-projetos-technical-seo-toolkit' ); ?></strong></label><br />
			<input type="url" id="gpseo_canonical_url" name="gpseo_canonical_url" style="width:100%;" value="<?php echo esc_attr( $canonical ); ?>" placeholder="<?php echo esc_attr( get_permalink( $post ) ); ?>" />
		</p>
		<p>
			<label><input type="checkbox" name="gpseo_noindex" value="1" <?php checked( $noindex ); ?> /> <?php esc_html_e( 'Noindex (hide from search engines)', 'garion-projetos-technical-seo-toolkit' ); ?></label>
			&nbsp;&nbsp;
			<label><input type="checkbox" name="gpseo_nofollow" value="1" <?php checked( $nofollow ); ?> /> <?php esc_html_e( 'Nofollow (do not follow links on this page)', 'garion-projetos-technical-seo-toolkit' ); ?></label>
		</p>

		<hr />

		<h3><?php esc_html_e( 'Social sharing (Open Graph / Twitter Card)', 'garion-projetos-technical-seo-toolkit' ); ?></h3>

		<p>
			<label for="gpseo_og_title"><strong><?php esc_html_e( 'Title override', 'garion-projetos-technical-seo-toolkit' ); ?></strong></label><br />
			<input type="text" id="gpseo_og_title" name="gpseo_og_title" style="width:100%;" value="<?php echo esc_attr( $og_title ); ?>" placeholder="<?php echo esc_attr( get_the_title( $post ) ); ?>" />
		</p>
		<p>
			<label for="gpseo_og_description"><strong><?php esc_html_e( 'Description override', 'garion-projetos-technical-seo-toolkit' ); ?></strong></label><br />
			<textarea id="gpseo_og_description" name="gpseo_og_description" rows="2" style="width:100%;"><?php echo esc_textarea( $og_description ); ?></textarea>
		</p>
		<p>
			<label for="gpseo_og_image"><strong><?php esc_html_e( 'Image override', 'garion-projetos-technical-seo-toolkit' ); ?></strong></label><br />
			<input type="url" id="gpseo_og_image" name="gpseo_og_image" style="width:70%;" value="<?php echo esc_attr( $og_image ); ?>" />
			<button type="button" class="button" id="gpseo_og_image_button"><?php esc_html_e( 'Choose image', 'garion-projetos-technical-seo-toolkit' ); ?></button>
		</p>

		<div id="gpseo-social-preview" class="gpseo-social-preview">
			<div class="gpseo-social-preview-image" style="<?php echo $preview['image'] ? 'background-image:url(' . esc_url( $preview['image'] ) . ');' : ''; ?>"></div>
			<div class="gpseo-social-preview-body">
				<p class="gpseo-social-preview-title"><?php echo esc_html( $preview['title'] ); ?></p>
				<p class="gpseo-social-preview-description"><?php echo esc_html( $preview['description'] ); ?></p>
				<p class="gpseo-social-preview-url"><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?></p>
			</div>
		</div>
		<?php
	}

	public function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['gpseo_meta_description'] ) ) {
			update_post_meta( $post_id, '_gpseo_meta_description', sanitize_textarea_field( wp_unslash( $_POST['gpseo_meta_description'] ) ) );
		}

		if ( isset( $_POST['gpseo_canonical_url'] ) ) {
			update_post_meta( $post_id, '_gpseo_canonical_url', esc_url_raw( wp_unslash( $_POST['gpseo_canonical_url'] ) ) );
		}

		update_post_meta( $post_id, '_gpseo_noindex', ! empty( $_POST['gpseo_noindex'] ) ? 1 : 0 );
		update_post_meta( $post_id, '_gpseo_nofollow', ! empty( $_POST['gpseo_nofollow'] ) ? 1 : 0 );

		if ( isset( $_POST['gpseo_og_title'] ) ) {
			update_post_meta( $post_id, GP_SEO_Social_Meta::TITLE_META_KEY, sanitize_text_field( wp_unslash( $_POST['gpseo_og_title'] ) ) );
		}

		if ( isset( $_POST['gpseo_og_description'] ) ) {
			update_post_meta( $post_id, GP_SEO_Social_Meta::DESCRIPTION_META_KEY, sanitize_textarea_field( wp_unslash( $_POST['gpseo_og_description'] ) ) );
		}

		if ( isset( $_POST['gpseo_og_image'] ) ) {
			update_post_meta( $post_id, GP_SEO_Social_Meta::IMAGE_META_KEY, esc_url_raw( wp_unslash( $_POST['gpseo_og_image'] ) ) );
		}
	}
}
