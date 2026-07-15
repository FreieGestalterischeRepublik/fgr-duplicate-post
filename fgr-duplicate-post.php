<?php
/**
 * Plugin Name:  FGR Duplicate Post
 * Description:  Ein Plugin der Freien Gestalterischen Republik. Dupliziert Posts, Seiten und Custom Post Types mit einem Klick – inklusive aller Meta-Daten, Taxonomien und Page-Builder-Inhalte (WPBakery, Elementor).
 * Version:      1.0.3
 * Author:       Freie Gestalterische Republik
 * Author URI:   https://fgr.design
 * License:      GPL-2.0-or-later
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * Text Domain:  fgr-duplicate-post
 */

defined( 'ABSPATH' ) || exit;

define( 'FGR_DUPLICATE_POST_VERSION', '1.0.3' );

// Update-Checker: nur registrieren wenn das MU-Plugin den Slug nicht schon belegt hat
if ( ! apply_filters( 'puc_is_slug_in_use-fgr-duplicate-post', false ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'lib/plugin-update-checker/plugin-update-checker.php';
    $fgr_duplicate_post_updater = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/FreieGestalterischeRepublik/fgr-duplicate-post/',
        __FILE__,
        'fgr-duplicate-post'
    );
    $fgr_duplicate_post_updater->setBranch( 'main' );
    $fgr_duplicate_post_updater->getVcsApi()->enableReleaseAssets();
}

// "Details anzeigen" und "Nach Update suchen" erscheinen in der Pluginliste
add_filter( 'plugin_row_meta', function ( array $links, string $plugin_file ): array {
    if ( plugin_basename( __FILE__ ) !== $plugin_file || ! current_user_can( 'update_plugins' ) ) {
        return $links;
    }
    $has_details = false;
    $has_check   = false;
    foreach ( $links as $link ) {
        if ( strpos( $link, 'open-plugin-details-modal' ) !== false ) $has_details = true;
        if ( strpos( $link, 'puc_check_for_updates' )     !== false ) $has_check   = true;
    }
    if ( ! $has_details ) {
        $url     = network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=fgr-duplicate-post&TB_iframe=true&width=600&height=550' );
        $links[] = '<a href="' . esc_url( $url ) . '" class="thickbox open-plugin-details-modal">Details anzeigen</a>';
    }
    if ( ! $has_check ) {
        $url     = wp_nonce_url(
            add_query_arg( [ 'puc_check_for_updates' => 1, 'puc_slug' => 'fgr-duplicate-post' ], self_admin_url( 'plugins.php' ) ),
            'puc_check_for_updates'
        );
        $links[] = '<a href="' . esc_url( $url ) . '">Nach Update suchen</a>';
    }
    return $links;
}, 20, 2 );

// ── MU-Plugin-Sync ────────────────────────────────────────────────────────────

if ( ! function_exists( 'fgr_mu_sync' ) ) {
    function fgr_mu_sync(): void {
        $url      = 'https://raw.githubusercontent.com/FreieGestalterischeRepublik/fgr-plugin-overview/main/fgr-plugin-overview.php';
        $dest_dir = WPMU_PLUGIN_DIR;
        $dest     = $dest_dir . '/fgr-plugin-overview.php';

        $response = wp_remote_get( $url, [
            'timeout'    => 15,
            'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
        ] );

        if ( is_wp_error( $response ) ) return;
        if ( 200 !== wp_remote_retrieve_response_code( $response ) ) return;

        $remote_content = wp_remote_retrieve_body( $response );
        if ( empty( $remote_content ) ) return;

        preg_match( '/\*\s+Version:\s+([\d.]+)/i', $remote_content, $matches );
        $remote_version = $matches[1] ?? '0';

        $installed_version = '0';
        if ( file_exists( $dest ) ) {
            $contents = file_get_contents( $dest );
            preg_match( '/\*\s+Version:\s+([\d.]+)/i', $contents, $m );
            $installed_version = $m[1] ?? '0';
        }

        if ( ! file_exists( $dest ) || version_compare( $remote_version, $installed_version, '>' ) ) {
            if ( ! is_dir( $dest_dir ) ) {
                wp_mkdir_p( $dest_dir );
            }
            file_put_contents( $dest, $remote_content );
            delete_transient( 'fgr_mu_update_info' );
        }
    }
}

register_activation_hook( __FILE__, 'fgr_mu_sync' );

add_action( 'upgrader_process_complete', function ( $upgrader, array $hook_extra ): void {
    if ( ( $hook_extra['type'] ?? '' ) !== 'plugin' ) return;
    if ( ( $hook_extra['action'] ?? '' ) !== 'update' ) return;

    $fgr_plugins = [
        'fgr-mail-smtp/fgr-mail-smtp.php',
        'fgr-hide-login/fgr-hide-login.php',
        'fgr-maintenance/fgr-maintenance.php',
        'fgr-email-encoder/fgr-email-encoder.php',
        'fgr-duplicate-post/fgr-duplicate-post.php',
    ];

    $updated = array_merge(
        isset( $hook_extra['plugin'] )  ? (array) $hook_extra['plugin']  : [],
        isset( $hook_extra['plugins'] ) ? (array) $hook_extra['plugins'] : []
    );

    foreach ( $updated as $plugin_file ) {
        if ( in_array( $plugin_file, $fgr_plugins, true ) ) {
            fgr_mu_sync();
            return;
        }
    }
}, 10, 2 );

// Warnung wenn Plugin im falschen Ordner installiert ist
if ( is_admin() && substr( untrailingslashit( plugin_dir_path( __FILE__ ) ), -5 ) === '-main' ) {
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-error"><p>'
            . '<strong>FGR Duplicate Post:</strong> Das Plugin ist im falschen Ordner installiert '
            . '(<code>' . esc_html( basename( plugin_dir_path( __FILE__ ) ) ) . '</code>). '
            . 'Bitte das Plugin <strong>deaktivieren → löschen → neu installieren</strong>.'
            . '</p></div>';
    } );
}

// Settings-Klasse im Admin laden
add_action( 'plugins_loaded', function () {
    if ( is_admin() ) {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-fgr-duplicate-post-settings.php';
        new FGR_Duplicate_Post_Settings();
    }
} );

// ── "Duplizieren"-Link in der Postliste ──────────────────────────────────────

add_filter( 'post_row_actions', 'fgr_dp_row_link', 10, 2 );
add_filter( 'page_row_actions', 'fgr_dp_row_link', 10, 2 );

function fgr_dp_row_link( array $actions, WP_Post $post ): array {
    // Interne WordPress-Post-Types ausschließen
    $always_excluded = [
        'acf-field-group', 'acf-field', 'oembed_cache',
        'wp_block', 'wp_template', 'wp_template_part',
        'wp_navigation', 'wp_global_styles', 'revision',
    ];
    if ( in_array( $post->post_type, $always_excluded, true ) ) {
        return $actions;
    }

    // Benutzer-konfigurierte Ausnahmen
    $opts     = get_option( 'fgr_duplicate_post', [] );
    $excluded = $opts['excluded_types'] ?? [];
    if ( in_array( $post->post_type, $excluded, true ) ) {
        return $actions;
    }

    if ( ! current_user_can( 'edit_post', $post->ID ) ) {
        return $actions;
    }

    $nonce             = wp_create_nonce( 'fgr_dp_' . $post->ID );
    $url               = admin_url( 'admin.php?action=fgr_duplicate_post&post=' . $post->ID . '&nonce=' . $nonce );
    $actions['fgr_dp'] = '<a href="' . esc_url( $url ) . '" title="Diesen Eintrag duplizieren">Duplizieren</a>';

    return $actions;
}

// ── Duplikation ausführen ─────────────────────────────────────────────────────

add_action( 'admin_action_fgr_duplicate_post', 'fgr_dp_execute' );

function fgr_dp_execute(): void {
    $post_id = isset( $_GET['post'] ) ? intval( $_GET['post'] ) : 0;
    $nonce   = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';

    if ( ! $post_id || ! wp_verify_nonce( $nonce, 'fgr_dp_' . $post_id ) ) {
        wp_die( 'Sicherheitsprüfung fehlgeschlagen. Bitte die Seite neu laden und erneut versuchen.' );
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        wp_die( 'Keine Berechtigung.' );
    }

    $new_id = fgr_dp_create( $post_id );

    if ( is_wp_error( $new_id ) ) {
        wp_die( 'Fehler beim Duplizieren: ' . esc_html( $new_id->get_error_message() ) );
    }

    $opts     = get_option( 'fgr_duplicate_post', [] );
    $redirect = $opts['redirect'] ?? 'list';
    $post     = get_post( $post_id );

    if ( 'edit' === $redirect ) {
        wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $new_id ) );
    } else {
        $return = ( $post && 'post' !== $post->post_type ) ? '?post_type=' . $post->post_type : '';
        wp_safe_redirect( admin_url( 'edit.php' . $return ) );
    }
    exit;
}

/** @return int|WP_Error */
function fgr_dp_create( int $post_id ) {
    $post = get_post( $post_id );
    if ( ! $post ) {
        return new WP_Error( 'not_found', 'Originalbeitrag nicht gefunden.' );
    }

    $opts   = get_option( 'fgr_duplicate_post', [] );
    $suffix = isset( $opts['suffix'] ) && '' !== $opts['suffix'] ? ' ' . $opts['suffix'] : '';
    $status = $opts['status'] ?? 'draft';

    // "same" bedeutet: gleicher Status wie Original
    if ( 'same' === $status ) {
        $status = $post->post_status;
    }

    $args = [
        'post_author'    => $post->post_author,
        'post_content'   => $post->post_content,
        'post_excerpt'   => $post->post_excerpt,
        'post_parent'    => $post->post_parent,
        'post_password'  => $post->post_password,
        'post_status'    => $status,
        'post_title'     => $post->post_title . $suffix,
        'post_type'      => $post->post_type,
        'comment_status' => $post->comment_status,
        'ping_status'    => $post->ping_status,
        'to_ping'        => $post->to_ping,
        'menu_order'     => $post->menu_order,
    ];

    // wp_slash sorgt dafür dass Slashes in WPBakery/Elementor-Shortcodes erhalten bleiben
    $new_id = wp_insert_post( wp_slash( $args ), true );

    if ( is_wp_error( $new_id ) ) {
        return $new_id;
    }

    // Alle Meta-Daten kopieren (Custom Fields, WPBakery, Elementor, ACF, etc.)
    $meta_keys = get_post_custom_keys( $post_id );
    if ( ! empty( $meta_keys ) ) {
        foreach ( $meta_keys as $key ) {
            $values = get_post_meta( $post_id, $key );
            foreach ( $values as $value ) {
                // update_post_meta serialisiert Arrays/Objekte automatisch
                update_post_meta( $new_id, $key, $value );
            }
        }
    }

    // Alle Taxonomien kopieren (Kategorien, Tags, Custom Taxonomies)
    $taxonomies = get_object_taxonomies( $post->post_type );
    foreach ( $taxonomies as $taxonomy ) {
        $terms = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'slugs' ] );
        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            wp_set_object_terms( $new_id, $terms, $taxonomy );
        }
    }

    // Elementor: CSS für den neuen Post neu generieren
    if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
        $css = \Elementor\Core\Files\CSS\Post::create( $new_id );
        $css->update();
    }

    return $new_id;
}
