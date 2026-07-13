<?php
// Nur ausführen wenn WordPress das Deinstallieren aufgerufen hat
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'fgr_duplicate_post' );
