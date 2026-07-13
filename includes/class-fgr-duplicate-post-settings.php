<?php
defined( 'ABSPATH' ) || exit;

class FGR_Duplicate_Post_Settings {

    public function __construct() {
        add_action( 'admin_menu',   [ $this, 'register_submenu' ] );
        add_action( 'admin_init',   [ $this, 'register_settings' ] );
    }

    public function register_submenu(): void {
        add_submenu_page(
            'fgr-plugins',
            'FGR Duplicate Post',
            'Duplicate Post',
            'manage_options',
            'fgr-duplicate-post',
            [ $this, 'render_page' ]
        );
    }

    public function register_settings(): void {
        register_setting(
            'fgr_duplicate_post_group',
            'fgr_duplicate_post',
            [
                'sanitize_callback' => [ $this, 'sanitize_options' ],
                'default'           => [
                    'status'         => 'draft',
                    'redirect'       => 'list',
                    'suffix'         => '',
                    'excluded_types' => [],
                ],
            ]
        );
    }

    public function sanitize_options( $input ): array {
        $clean = [];

        $clean['status'] = in_array( $input['status'] ?? '', [ 'draft', 'same', 'pending' ], true )
            ? $input['status']
            : 'draft';

        $clean['redirect'] = in_array( $input['redirect'] ?? '', [ 'list', 'edit' ], true )
            ? $input['redirect']
            : 'list';

        $clean['suffix'] = sanitize_text_field( $input['suffix'] ?? '' );

        $excluded = $input['excluded_types'] ?? [];
        if ( ! is_array( $excluded ) ) {
            $excluded = [];
        }
        $clean['excluded_types'] = array_map( 'sanitize_key', $excluded );

        return $clean;
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Keine Berechtigung.' );
        }

        $opts     = get_option( 'fgr_duplicate_post', [] );
        $status   = $opts['status']   ?? 'draft';
        $redirect = $opts['redirect'] ?? 'list';
        $suffix   = $opts['suffix']   ?? '';
        $excluded = $opts['excluded_types'] ?? [];

        // Alle öffentlichen Post Types ermitteln (außer immer ausgeschlossene interne)
        $all_types = get_post_types( [ 'public' => true ], 'objects' );
        $always_excluded = [
            'acf-field-group', 'acf-field', 'oembed_cache',
            'wp_block', 'wp_template', 'wp_template_part',
            'wp_navigation', 'wp_global_styles', 'revision',
        ];
        ?>
        <div class="wrap">
            <h1>
                <span class="dashicons dashicons-admin-page" style="font-size:28px;height:28px;margin-right:6px;vertical-align:middle;color:#2271b1;"></span>
                FGR Duplicate Post
            </h1>
            <p style="color:#646970;">Dupliziert Posts, Seiten und Custom Post Types mit einem Klick. Der Duplikat-Link erscheint in der jeweiligen Listen-Ansicht im Backend.</p>

            <form method="post" action="options.php">
                <?php settings_fields( 'fgr_duplicate_post_group' ); ?>

                <table class="form-table" role="presentation">

                    <tr>
                        <th scope="row">Status des Duplikats</th>
                        <td>
                            <fieldset>
                                <label style="display:block;margin-bottom:8px;">
                                    <input type="radio" name="fgr_duplicate_post[status]" value="draft" <?php checked( $status, 'draft' ); ?>>
                                    <strong>Entwurf</strong>
                                    <span style="color:#646970;margin-left:6px;">– Duplikat wird als Entwurf gespeichert (empfohlen)</span>
                                </label>
                                <label style="display:block;margin-bottom:8px;">
                                    <input type="radio" name="fgr_duplicate_post[status]" value="same" <?php checked( $status, 'same' ); ?>>
                                    <strong>Gleicher Status</strong>
                                    <span style="color:#646970;margin-left:6px;">– Duplikat bekommt denselben Status wie das Original</span>
                                </label>
                                <label style="display:block;">
                                    <input type="radio" name="fgr_duplicate_post[status]" value="pending" <?php checked( $status, 'pending' ); ?>>
                                    <strong>Ausstehend</strong>
                                    <span style="color:#646970;margin-left:6px;">– Duplikat wartet auf Überprüfung</span>
                                </label>
                            </fieldset>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Nach dem Duplizieren</th>
                        <td>
                            <fieldset>
                                <label style="display:block;margin-bottom:8px;">
                                    <input type="radio" name="fgr_duplicate_post[redirect]" value="list" <?php checked( $redirect, 'list' ); ?>>
                                    <strong>Zurück zur Liste</strong>
                                    <span style="color:#646970;margin-left:6px;">– Bleibt in der Übersicht (empfohlen)</span>
                                </label>
                                <label style="display:block;">
                                    <input type="radio" name="fgr_duplicate_post[redirect]" value="edit" <?php checked( $redirect, 'edit' ); ?>>
                                    <strong>Duplikat direkt bearbeiten</strong>
                                    <span style="color:#646970;margin-left:6px;">– Öffnet den Bearbeitungsbildschirm des Duplikats</span>
                                </label>
                            </fieldset>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Titel-Suffix</th>
                        <td>
                            <input type="text"
                                   name="fgr_duplicate_post[suffix]"
                                   value="<?php echo esc_attr( $suffix ); ?>"
                                   placeholder="z. B. (Kopie)"
                                   style="width:280px;">
                            <p class="description">
                                Wird an den Titel des Duplikats angehängt. Leer lassen für keinen Zusatz.<br>
                                Beispiel: „Über uns" → „Über uns (Kopie)"
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Ausgeschlossene Post-Types</th>
                        <td>
                            <fieldset>
                                <?php foreach ( $all_types as $type ) : ?>
                                    <?php if ( in_array( $type->name, $always_excluded, true ) ) continue; ?>
                                    <label style="display:block;margin-bottom:6px;">
                                        <input type="checkbox"
                                               name="fgr_duplicate_post[excluded_types][]"
                                               value="<?php echo esc_attr( $type->name ); ?>"
                                               <?php checked( in_array( $type->name, $excluded, true ) ); ?>>
                                        <strong><?php echo esc_html( $type->label ); ?></strong>
                                        <code style="color:#646970;font-size:11px;"><?php echo esc_html( $type->name ); ?></code>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description">Für angehakte Post-Types wird der Duplizieren-Link nicht angezeigt.</p>
                        </td>
                    </tr>

                </table>

                <?php submit_button( 'Einstellungen speichern' ); ?>
            </form>
        </div>
        <?php
    }
}
