<?php
/**
 * Haupt-Plugin-Klasse für av-newsletter
 *
 * @package VRW_Newsletter
 * @author  Kai Naumann <info@vereinsring-wasserlos.de>
 * @version 1.0.7
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AVN_Plugin {

    private $version;

    public function __construct( string $version ) {
        $this->version = $version;
    }

    public function run() {
        load_plugin_textdomain( 'av-newsletter', false, dirname( plugin_basename( AVN_PLUGIN_FILE ) ) . '/languages' );

        // Admin
        if ( is_admin() ) {
            require_once AVN_PLUGIN_PATH . 'admin/class-avn-admin.php';
            $admin = new AVN_Admin( $this->version );
            $admin->init_hooks();
        }

        // Frontend: Token-Aktionen (Opt-In / Abmeldung)
        add_action( 'init', [ $this, 'handle_token_actions' ] );

        // SMTP-Hook wird gezielt vor einzelnen Plugin-Sendevorgängen registriert,
        // damit andere Plugins / WP-Kerneinstellungen nicht beeinflusst werden.

        // Cron: Mail-Queue alle 5 Minuten
        add_filter( 'cron_schedules', [ $this, 'add_cron_schedule' ] );
        add_action( 'avn_mail_queue_cron', [ 'AVN_Mail_Handler', 'process_queue' ] );
        add_action( 'avn_mail_queue_cron', [ 'AVN_Mail_Handler', 'fire_scheduled_campaigns' ] );

        if ( ! wp_next_scheduled( 'avn_mail_queue_cron' ) ) {
            wp_schedule_event( time(), 'every_5_minutes', 'avn_mail_queue_cron' );
        }
    }

    public function add_cron_schedule( $schedules ) {
        $schedules['every_5_minutes'] = [
            'interval' => 300,
            'display'  => __( 'Alle 5 Minuten', 'av-newsletter' ),
        ];
        return $schedules;
    }

    public function handle_token_actions() {
        $action = sanitize_key( $_GET['avn_action'] ?? '' );
        $token  = sanitize_text_field( $_GET['avn_token']  ?? '' );

        if ( ! $action || ! $token ) {
            return;
        }

        if ( $action === 'confirm' ) {
            $ok = AVN_Subscriber::confirm_by_token( $token );
            wp_safe_redirect( add_query_arg( 'avn_confirmed', $ok ? '1' : '0', home_url( '/' ) ) );
            exit;
        }

        if ( $action === 'unsubscribe' ) {
            $ok = AVN_Subscriber::unsubscribe_by_token( $token );
            wp_safe_redirect( add_query_arg( 'avn_unsubscribed', $ok ? '1' : '0', home_url( '/' ) ) );
            exit;
        }
    }
}
