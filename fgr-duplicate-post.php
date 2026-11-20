<?php
/**
 * Plugin Name:  FGR Duplicate Post
 * Description:  Ein Plugin der Freien Gestalterischen Republik. Dupliziert Posts, Seiten und Custom Post Types mit einem Klick – inklusive aller Meta-Daten, Taxonomien und Page-Builder-Inhalte (WPBakery, Elementor).
 * Version:      1.1.0
 * Author:       Freie Gestalterische Republik
 * Author URI:   https://fgr.design
 * License:      GPL-2.0-or-later
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * Text Domain:  fgr-duplicate-post
 */

defined( 'ABSPATH' ) || exit;

define( 'FGR_DUPLICATE_POST_VERSION', '1.1.0' );

require_once plugin_dir_path( __FILE__ ) . 'lib/plugin-update-checker/plugin-update-checker.php';
$fgr_duplicate_post_updater = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://fgr-plugins-api.fgr.design/fgr-duplicate-post.json',
    __FILE__,
    'fgr-duplicate-post'
);

// Auto-Update: WordPress' täglicher Update-Cron installiert neue Versionen
// dieses Plugins automatisch, kein manueller Klick auf jeder Seite nötig.
add_filter( 'auto_update_plugin', function ( $update, $item ) {
    if ( isset( $item->slug ) && $item->slug === 'fgr-duplicate-post' ) {
        return true;
    }
    return $update;
}, 10, 2 );

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
        // An den jeweils aktuellen GitHub-Release gepinnt statt an den beweglichen main-Branch:
        // ein Release ist ein bewusster, protokollierter Veröffentlichungsschritt, kein einzelner Push.
        $release = wp_remote_get(
            'https://api.github.com/repos/FreieGestalterischeRepublik/fgr-plugin-overview/releases/latest',
            [
                'timeout'    => 10,
                'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
            ]
        );
        if ( is_wp_error( $release ) || 200 !== wp_remote_retrieve_response_code( $release ) ) return;
        $release_data = json_decode( wp_remote_retrieve_body( $release ), true );
        $tag          = (string) ( $release_data['tag_name'] ?? '' );
        if ( '' === $tag || ! preg_match( '/^v?[\d.]+$/', $tag ) ) return;

        $url      = 'https://raw.githubusercontent.com/FreieGestalterischeRepublik/fgr-plugin-overview/' . rawurlencode( $tag ) . '/fgr-plugin-overview.php';
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

/** Post-Type darf nicht dupliziert werden (fest oder vom Admin ausgeschlossen)? */
function fgr_dp_is_excluded_type( string $post_type ): bool {
    // Interne WordPress-Post-Types ausschließen
    $always_excluded = [
        'acf-field-group', 'acf-field', 'oembed_cache',
        'wp_block', 'wp_template', 'wp_template_part',
        'wp_navigation', 'wp_global_styles', 'revision',
    ];
    if ( in_array( $post_type, $always_excluded, true ) ) {
        return true;
    }

    // Benutzer-konfigurierte Ausnahmen
    $opts     = get_option( 'fgr_duplicate_post', [] );
    $excluded = $opts['excluded_types'] ?? [];
    return in_array( $post_type, $excluded, true );
}

function fgr_dp_row_link( array $actions, WP_Post $post ): array {
    if ( fgr_dp_is_excluded_type( $post->post_type ) ) {
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

    $post = get_post( $post_id );
    if ( ! $post || fgr_dp_is_excluded_type( $post->post_type ) ) {
        wp_die( 'Dieser Inhaltstyp ist von der Duplizieren-Funktion ausgeschlossen.' );
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

    // WooCommerce-Produkte über die WooCommerce-eigene Kopierfunktion duplizieren
    if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
        return fgr_dp_create_product( $post, $post->post_title . $suffix, $status );
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

// ── WooCommerce-Produkte ──────────────────────────────────────────────────────

/**
 * Meta-Schlüssel, die beim Kopieren eines Produkts nicht übernommen werden.
 * Sie gehören zum Original (Zähler, Sync-Status externer Dienste) und würden
 * in der Kopie falsche Werte oder Verknüpfungen erzeugen.
 */
function fgr_dp_product_meta_excluded( string $key ): bool {
    $exact = [
        '_WooCommerceEventsCounter', // FooEvents: fortlaufende Ticketnummer des Events
        '_edit_lock',
        '_edit_last',
    ];
    if ( in_array( $key, $exact, true ) ) {
        return true;
    }

    $prefixes = [
        '_wc_gla_',               // Google for WooCommerce: Verknüpfung zum Google-Produkt
        '_wc_push_notification_', // WooCommerce-App: Lagerwarnungen bereits gesendet
    ];
    foreach ( $prefixes as $prefix ) {
        if ( 0 === strpos( $key, $prefix ) ) {
            return true;
        }
    }

    return (bool) apply_filters( 'fgr_duplicate_post_product_meta_excluded', false, $key );
}

/**
 * Bereinigt ein Produkt (oder eine Variante) vor dem Speichern der Kopie:
 * Lagerbestand auf 0, ausgeschlossene Meta-Daten entfernen.
 */
function fgr_dp_clean_product_copy( WC_Product $copy ): void {
    if ( $copy->get_manage_stock( 'edit' ) ) {
        $copy->set_stock_quantity( 0 );
    }
    foreach ( $copy->get_meta_data() as $meta ) {
        if ( fgr_dp_product_meta_excluded( (string) $meta->key ) ) {
            $copy->delete_meta_data( $meta->key );
        }
    }
}

/**
 * Dupliziert ein WooCommerce-Produkt mit der WooCommerce-eigenen Funktion.
 * Diese vergibt eine neue Artikelnummer (SKU), setzt Verkaufszahl und Bewertungen
 * zurück und kopiert auch die Varianten. Bestellungen und Tickets (FooEvents)
 * hängen nicht am Produkt-Datensatz und werden nicht kopiert.
 *
 * @return int|WP_Error
 */
function fgr_dp_create_product( WP_Post $post, string $title, string $status ) {
    $product = wc_get_product( $post->ID );
    if ( ! $product ) {
        return new WP_Error( 'not_found', 'Produkt nicht gefunden.' );
    }

    if ( ! class_exists( 'WC_Admin_Duplicate_Product' ) && defined( 'WC_ABSPATH' ) ) {
        $file = WC_ABSPATH . 'includes/admin/class-wc-admin-duplicate-product.php';
        if ( file_exists( $file ) ) {
            include_once $file;
        }
    }
    if ( ! class_exists( 'WC_Admin_Duplicate_Product' ) ) {
        return new WP_Error( 'wc_missing', 'Die WooCommerce-Kopierfunktion ist nicht verfügbar.' );
    }

    $before_save = function ( $copy, $original ) use ( $post, $title, $status ) {
        if ( ! $copy instanceof WC_Product ) {
            return;
        }
        fgr_dp_clean_product_copy( $copy );
        // Nur das Hauptprodukt bekommt Titel und Status aus den Plugin-Einstellungen
        if ( $original instanceof WC_Product && $original->get_id() === $post->ID ) {
            $copy->set_name( $title );
            $copy->set_status( $status );
        }
    };

    add_action( 'woocommerce_product_duplicate_before_save', $before_save, 10, 2 );
    try {
        $duplicator = new WC_Admin_Duplicate_Product();
        $copy       = $duplicator->product_duplicate( $product );
    } catch ( Throwable $e ) {
        return new WP_Error( 'wc_duplicate_failed', $e->getMessage() );
    } finally {
        remove_action( 'woocommerce_product_duplicate_before_save', $before_save, 10 );
    }

    if ( ! $copy instanceof WC_Product || ! $copy->get_id() ) {
        return new WP_Error( 'wc_duplicate_failed', 'Das Produkt konnte nicht kopiert werden.' );
    }
    $new_id = $copy->get_id();

    // Weitere Taxonomien (z. B. Marken, eigene Taxonomien) übernehmen, die WooCommerce nicht selbst kopiert
    $skip = [ 'product_type', 'product_visibility', 'product_shipping_class', 'product_cat', 'product_tag' ];
    foreach ( get_object_taxonomies( 'product' ) as $taxonomy ) {
        if ( in_array( $taxonomy, $skip, true ) || 0 === strpos( $taxonomy, 'pa_' ) ) {
            continue;
        }
        $terms = wp_get_object_terms( $post->ID, $taxonomy, [ 'fields' => 'slugs' ] );
        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            wp_set_object_terms( $new_id, $terms, $taxonomy );
        }
    }

    // Elementor: CSS für das neue Produkt neu generieren
    if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
        $css = \Elementor\Core\Files\CSS\Post::create( $new_id );
        $css->update();
    }

    return $new_id;
}
