<?php
/**
 * Plugin Name: VR Wasserlos Newsletter
 * Plugin URI:  https://vereinsring-wasserlos.de
 * Description: Newsletter-Verwaltung für den Vereinsring Wasserlos und seine Vereine. Verwaltet Abonnenten, Gruppen, Mail-Templates und Kampagnen.
 * Version:     1.0.7
 * Author:      Kai Naumann
 * Author URI:  mailto:info@vereinsring-wasserlos.de
 * Owner:       Vereinsring Wasserlos e.V.
 * Text Domain: av-newsletter
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'AVN_VERSION',     '1.0.7' );
define( 'AVN_PLUGIN_FILE', __FILE__ );
define( 'AVN_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'AVN_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

/* ── Autoload ───────────────────────────────────────────────────────────── */

require_once AVN_PLUGIN_PATH . 'includes/class-avn-database.php';
require_once AVN_PLUGIN_PATH . 'includes/class-avn-activator.php';
require_once AVN_PLUGIN_PATH . 'includes/class-avn-deactivator.php';
require_once AVN_PLUGIN_PATH . 'includes/class-avn-subscriber.php';
require_once AVN_PLUGIN_PATH . 'includes/class-avn-mail-handler.php';
require_once AVN_PLUGIN_PATH . 'includes/class-avn-xlsx-importer.php';
require_once AVN_PLUGIN_PATH . 'includes/class-avn-plugin.php';

/* ── Aktivierung / Deaktivierung ────────────────────────────────────────── */

register_activation_hook(   AVN_PLUGIN_FILE, 'avn_activate' );
register_deactivation_hook( AVN_PLUGIN_FILE, 'avn_deactivate' );

function avn_activate() {
    AVN_Activator::activate();
}

function avn_deactivate() {
    AVN_Deactivator::deactivate();
}

/* ── Plugin starten ─────────────────────────────────────────────────────── */

function avn_run() {
    $plugin = new AVN_Plugin( AVN_VERSION );
    $plugin->run();
}
add_action( 'plugins_loaded', 'avn_run' );
