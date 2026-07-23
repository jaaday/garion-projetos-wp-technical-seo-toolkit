<?php
/**
 * Reusable, escaped presentation helpers shared by every admin screen and the
 * post-editor metabox: badges, cards, metric tiles, alerts, empty states,
 * breadcrumbs, tooltips and the responsive table shell.
 *
 * Every method either returns a pre-escaped HTML string or echoes directly;
 * callers are still responsible for escaping any dynamic values they pass in.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GP_SEO_Admin_UI {

	/**
	 * Map a check/issue severity to a visual tone slug used by badges and cards.
	 */
	public static function severity_tone( $severity ) {
		$map = array(
			'critical'       => 'critical',
			'high'           => 'high',
			'medium'         => 'warning',
			'low'            => 'info',
			'recommendation' => 'info',
			'informational'  => 'neutral',
		);

		return $map[ $severity ] ?? 'neutral';
	}

	/**
	 * Map an issue lifecycle status to a visual tone slug.
	 */
	public static function status_tone( $status ) {
		$map = array(
			'open'      => 'warning',
			'reopened'  => 'warning',
			'ignored'   => 'neutral',
			'resolved'  => 'success',
			'pending'   => 'neutral',
			'running'   => 'info',
			'completed' => 'success',
			'failed'    => 'critical',
			'cancelled' => 'neutral',
		);

		return $map[ $status ] ?? 'neutral';
	}

	/**
	 * Dashicon glyph associated with a severity, used so meaning never relies on color alone.
	 */
	public static function severity_icon( $severity ) {
		$map = array(
			'critical'       => 'dashicons-dismiss',
			'high'           => 'dashicons-warning',
			'medium'         => 'dashicons-flag',
			'low'            => 'dashicons-info-outline',
			'recommendation' => 'dashicons-lightbulb',
			'informational'  => 'dashicons-info-outline',
		);

		return $map[ $severity ] ?? 'dashicons-marker';
	}

	public static function status_icon( $status ) {
		$map = array(
			'open'      => 'dashicons-warning',
			'reopened'  => 'dashicons-update',
			'ignored'   => 'dashicons-hidden',
			'resolved'  => 'dashicons-yes-alt',
			'pending'   => 'dashicons-clock',
			'running'   => 'dashicons-update',
			'completed' => 'dashicons-yes-alt',
			'failed'    => 'dashicons-dismiss',
			'cancelled' => 'dashicons-no-alt',
		);

		return $map[ $status ] ?? 'dashicons-marker';
	}

	/**
	 * A small inline dashicon. $label is optional visually-hidden text for screen readers
	 * when the icon is used without adjacent visible text.
	 */
	public static function icon( $dashicon, $label = '' ) {
		$html = '<span class="dashicons ' . esc_attr( $dashicon ) . ' gpseo-icon" aria-hidden="true"></span>';

		if ( $label ) {
			$html .= '<span class="screen-reader-text">' . esc_html( $label ) . '</span>';
		}

		return $html;
	}

	/**
	 * A pill badge. Never relies on color alone: always paired with an icon and text.
	 */
	public static function badge( $label, $tone = 'neutral', $icon = '' ) {
		$tone = sanitize_html_class( $tone );

		return sprintf(
			'<span class="gpseo-badge gpseo-badge--%1$s">%2$s%3$s</span>',
			esc_attr( $tone ),
			$icon ? self::icon( $icon ) : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes its own parts.
			esc_html( $label )
		);
	}

	public function render_badge( $label, $tone = 'neutral', $icon = '' ) {
		echo self::badge( $label, $tone, $icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge() escapes its own parts.
	}

	public static function severity_badge( $severity, $label = '' ) {
		return self::badge( $label ? $label : ucfirst( $severity ), self::severity_tone( $severity ), self::severity_icon( $severity ) );
	}

	public static function status_badge( $status, $label = '' ) {
		return self::badge( $label ? $label : ucfirst( $status ), self::status_tone( $status ), self::status_icon( $status ) );
	}

	/**
	 * Plugin header: icon, name, version pill and short description.
	 */
	public static function header( $title, $version, $description ) {
		printf(
			'<div class="gpseo-header"><div class="gpseo-header__icon" aria-hidden="true"><span class="dashicons dashicons-search"></span></div><div class="gpseo-header__text"><h1 class="gpseo-header__title">%1$s <span class="gpseo-header__version">v%2$s</span></h1><p class="gpseo-header__desc">%3$s</p></div></div>',
			esc_html( $title ),
			esc_html( $version ),
			esc_html( $description )
		);
	}

	/**
	 * Breadcrumb trail. $items is an ordered list of [ 'label' => ..., 'url' => ... ];
	 * the last item should omit 'url' to render as the current, non-linked page.
	 */
	public static function breadcrumbs( array $items ) {
		if ( count( $items ) < 2 ) {
			return;
		}

		echo '<nav class="gpseo-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'garion-projetos-technical-seo-toolkit' ) . '">';
		$last = count( $items ) - 1;

		foreach ( $items as $index => $item ) {
			if ( $index > 0 ) {
				echo '<span class="gpseo-breadcrumbs__sep" aria-hidden="true">/</span>';
			}

			if ( ! empty( $item['url'] ) && $index !== $last ) {
				printf( '<a href="%s">%s</a>', esc_url( $item['url'] ), esc_html( $item['label'] ) );
			} else {
				printf( '<span aria-current="page">%s</span>', esc_html( $item['label'] ) );
			}
		}

		echo '</nav>';
	}

	/**
	 * Tab navigation. $tabs is slug => label; $icons is slug => dashicon (optional).
	 */
	public static function tabs( array $tabs, $current, $base_url, array $icons = array() ) {
		echo '<h2 class="nav-tab-wrapper gpseo-tabs" role="tablist">';

		foreach ( $tabs as $slug => $label ) {
			$active = $current === $slug;
			printf(
				'<a role="tab" aria-selected="%1$s" href="%2$s" class="nav-tab gpseo-tabs__link%3$s">%4$s%5$s</a>',
				$active ? 'true' : 'false',
				esc_url( add_query_arg( array( 'tab' => $slug ), $base_url ) ),
				$active ? ' nav-tab-active gpseo-tabs__link--active' : '',
				isset( $icons[ $slug ] ) ? self::icon( $icons[ $slug ] ) : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes its own parts.
				esc_html( $label )
			);
		}

		echo '</h2>';
	}

	/**
	 * A metric tile for the dashboard grid.
	 */
	public static function metric_card( $value, $label, $tone = 'default', $icon = '' ) {
		printf(
			'<div class="gpseo-metric gpseo-metric--%1$s">%2$s<div class="gpseo-metric__body"><strong class="gpseo-metric__value">%3$s</strong><span class="gpseo-metric__label">%4$s</span></div></div>',
			esc_attr( sanitize_html_class( $tone ) ),
			$icon ? '<span class="gpseo-metric__icon">' . self::icon( $icon ) . '</span>' : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes its own parts.
			esc_html( $value ),
			esc_html( $label )
		);
	}

	/**
	 * Notice/alert box. Icon always accompanies color so meaning is never color-only.
	 */
	public static function alert( $message, $type = 'info', $dismissible = false ) {
		$icons = array(
			'info'    => 'dashicons-info-outline',
			'success' => 'dashicons-yes-alt',
			'warning' => 'dashicons-warning',
			'danger'  => 'dashicons-dismiss',
		);
		$icon  = $icons[ $type ] ?? 'dashicons-info-outline';

		printf(
			'<div class="gpseo-alert gpseo-alert--%1$s"%2$s>%3$s<div class="gpseo-alert__body">%4$s</div></div>',
			esc_attr( sanitize_html_class( $type ) ),
			$dismissible ? ' data-dismissible="1"' : '',
			self::icon( $icon ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes its own parts.
			wp_kses_post( $message )
		);
	}

	/**
	 * Empty-state placeholder shown when a table/section has no data yet.
	 */
	public static function empty_state( $title, $description = '', $icon = 'dashicons-info-outline', $action_html = '' ) {
		printf(
			'<div class="gpseo-empty-state">%1$s<p class="gpseo-empty-state__title">%2$s</p>%3$s%4$s</div>',
			self::icon( $icon ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes its own parts.
			esc_html( $title ),
			$description ? '<p class="gpseo-empty-state__desc">' . esc_html( $description ) . '</p>' : '',
			$action_html ? '<div class="gpseo-empty-state__action">' . $action_html . '</div>' : '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- action markup is built by trusted callers with their own escaping.
		);
	}

	/**
	 * Table cell empty-state row, used inside an already-open <tbody>.
	 */
	public static function empty_row( $colspan, $title, $description = '' ) {
		printf(
			'<tr class="gpseo-empty-row"><td colspan="%1$d">%2$s</td></tr>',
			(int) $colspan,
			self::empty_state( $title, $description ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- empty_state() escapes its own parts.
		);
	}

	public static function card_start( $title = '', $subtitle = '', $class = '' ) {
		printf( '<div class="gpseo-card %s">', esc_attr( $class ) );

		if ( $title ) {
			echo '<div class="gpseo-card__header"><h2 class="gpseo-card__title">' . esc_html( $title ) . '</h2>';
			if ( $subtitle ) {
				echo '<p class="gpseo-card__subtitle">' . esc_html( $subtitle ) . '</p>';
			}
			echo '</div>';
		}

		echo '<div class="gpseo-card__body">';
	}

	public static function card_end() {
		echo '</div></div>';
	}

	/**
	 * Small "(?)" tooltip trigger with accessible text, for labels that need extra context.
	 */
	public static function tooltip( $text ) {
		return sprintf(
			'<span class="gpseo-tooltip" tabindex="0" data-gpseo-tooltip="%1$s"><span class="dashicons dashicons-editor-help" aria-hidden="true"></span><span class="screen-reader-text">%1$s</span></span>',
			esc_attr( $text )
		);
	}

	/**
	 * Opens the responsive table shell. $headers is an ordered list of column labels
	 * used both for the visible <thead> and as the data-label attribute the CSS uses
	 * to relabel cells when the table collapses into stacked cards on small screens.
	 */
	public static function table_start( array $headers, $class = '' ) {
		echo '<div class="gpseo-table-wrap"><table class="gpseo-table widefat striped ' . esc_attr( $class ) . '"><thead><tr>';

		foreach ( $headers as $header ) {
			echo '<th>' . esc_html( $header ) . '</th>';
		}

		echo '</tr></thead><tbody>';
	}

	public static function table_end() {
		echo '</tbody></table></div>';
	}
}
