<?php
/**
 * Deaktivierung
 *
 * @package VRW_Newsletter
 * @author  Kai Naumann <info@vereinsring-wasserlos.de>
 * @version 1.0.7
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AVN_Deactivator {

    public static function deactivate() {
        wp_clear_scheduled_hook( 'avn_mail_queue_cron' );
    }
}
