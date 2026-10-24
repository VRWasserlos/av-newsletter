<?php
/**
 * Admin-Klasse: Menü, Scripts, AJAX-Handler für av-newsletter
 *
 * @package VRW_Newsletter
 * @author  Kai Naumann <info@vereinsring-wasserlos.de>
 * @version 1.0.7
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AVN_Admin {

    private $version;

    public function __construct( string $version ) {
        $this->version = $version;
    }

    public function init_hooks() {
        add_action( 'admin_menu',            [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_init',            [ $this, 'maybe_upgrade' ] );
        add_action( 'admin_head',            [ $this, 'hide_menu_items' ] );

        // AJAX: Abonnenten
        add_action( 'wp_ajax_avn_save_subscriber',      [ $this, 'ajax_save_subscriber' ] );
        add_action( 'wp_ajax_avn_delete_subscriber',    [ $this, 'ajax_delete_subscriber' ] );
        add_action( 'wp_ajax_avn_import_subscribers',   [ $this, 'ajax_import_subscribers' ] );

        // AJAX: Gruppen
        add_action( 'wp_ajax_avn_save_group',        [ $this, 'ajax_save_group' ] );
        add_action( 'wp_ajax_avn_delete_group',      [ $this, 'ajax_delete_group' ] );

        // AJAX: Mail-Templates
        add_action( 'wp_ajax_avn_save_mail_template',   [ $this, 'ajax_save_mail_template' ] );
        add_action( 'wp_ajax_avn_delete_mail_template', [ $this, 'ajax_delete_mail_template' ] );
        add_action( 'wp_ajax_avn_get_mail_template',    [ $this, 'ajax_get_mail_template' ] );

        // AJAX: Kampagne
        add_action( 'wp_ajax_avn_create_campaign',  [ $this, 'ajax_create_campaign' ] );
        add_action( 'wp_ajax_avn_send_test_mail',   [ $this, 'ajax_send_test_mail' ] );
        add_action( 'wp_ajax_avn_save_draft',       [ $this, 'ajax_save_draft' ] );
        add_action( 'wp_ajax_avn_delete_draft',       [ $this, 'ajax_delete_draft' ] );
        add_action( 'wp_ajax_avn_duplicate_campaign',  [ $this, 'ajax_duplicate_campaign' ] );

        // AJAX: Einstellungen
        add_action( 'wp_ajax_avn_save_settings',    [ $this, 'ajax_save_settings' ] );
        add_action( 'wp_ajax_avn_send_smtp_test',   [ $this, 'ajax_send_smtp_test' ] );
        add_action( 'wp_ajax_avn_test_mail_settings', [ $this, 'ajax_test_mail_settings' ] );
        add_action( 'wp_ajax_avn_cron_run_now',      [ $this, 'ajax_cron_run_now' ] );
        add_action( 'wp_ajax_avn_cron_retry_failed',  [ $this, 'ajax_cron_retry_failed' ] );
        add_action( 'wp_ajax_avn_log_delete_entry',   [ $this, 'ajax_log_delete_entry' ] );
        add_action( 'wp_ajax_avn_log_clear',          [ $this, 'ajax_log_clear' ] );
    }

    public function maybe_upgrade() {
        if ( get_option( 'avn_db_version' ) !== AVN_VERSION ) {
            AVN_Activator::activate();
        }
    }

    // ── Menü ───────────────────────────────────────────────────────────────

    public function register_menu() {
        add_menu_page(
            __( 'Newsletter', 'av-newsletter' ),
            __( 'Newsletter',  'av-newsletter' ),
            'manage_options',
            'avn-dashboard',
            [ $this, 'page_dashboard' ],
            'dashicons-email-alt',
            57
        );

        add_submenu_page( 'avn-dashboard', __( 'Dashboard',           'av-newsletter' ), __( 'Dashboard',           'av-newsletter' ), 'manage_options', 'avn-dashboard',             [ $this, 'page_dashboard' ] );
        add_submenu_page( 'avn-dashboard', __( 'Abonnenten',           'av-newsletter' ), __( 'Abonnenten',           'av-newsletter' ), 'manage_options', 'avn-abonnenten',            [ $this, 'page_abonnenten' ] );
        add_submenu_page( 'avn-dashboard', __( 'Gruppen',              'av-newsletter' ), __( 'Gruppen',              'av-newsletter' ), 'manage_options', 'avn-gruppen',               [ $this, 'page_gruppen' ] );
        add_submenu_page( 'avn-dashboard', __( 'Templates',            'av-newsletter' ), __( 'Templates',            'av-newsletter' ), 'manage_options', 'avn-mail-templates',        [ $this, 'page_mail_templates' ] );
        add_submenu_page( 'avn-dashboard', __( 'Mails',                'av-newsletter' ), __( 'Mails',                'av-newsletter' ), 'manage_options', 'avn-mail-senden',           [ $this, 'page_mail_senden' ] );
        add_submenu_page( 'avn-dashboard', __( 'Kampagnen-Historie',   'av-newsletter' ), __( 'Kampagnen-Historie',   'av-newsletter' ), 'manage_options', 'avn-kampagnen-historie',    [ $this, 'page_kampagnen_historie' ] );
        add_submenu_page( 'avn-dashboard', __( 'Einstellungen',        'av-newsletter' ), __( 'Einstellungen',        'av-newsletter' ), 'manage_options', 'avn-einstellungen',         [ $this, 'page_einstellungen' ] );

        // Mail-Builder: registriert, damit die Seite per URL erreichbar bleibt
        add_submenu_page( 'avn-dashboard', __( 'Mail-Builder', 'av-newsletter' ), __( 'Mail-Builder', 'av-newsletter' ), 'manage_options', 'avn-mail-builder', [ $this, 'page_mail_builder' ] );
        // Menü-Eintrag wird per CSS in hide_menu_items() ausgeblendet
    }

    // ── Assets ──────────────────────────────────────────────────────────────

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'avn-' ) === false ) {
            return;
        }

        wp_enqueue_script( 'jquery-ui-sortable' );
        wp_enqueue_script( 'jquery-ui-draggable' );
        wp_enqueue_script( 'jquery-ui-droppable' );

        wp_enqueue_style(
            'avn-admin',
            AVN_PLUGIN_URL . 'assets/css/avn-admin.css',
            [],
            $this->version
        );

        wp_enqueue_script(
            'avn-admin',
            AVN_PLUGIN_URL . 'assets/js/avn-admin.js',
            [ 'jquery' ],
            $this->version,
            true
        );

        wp_enqueue_style(
            'quill-snow',
            'https://cdn.quilljs.com/1.3.7/quill.snow.css',
            [],
            '1.3.7'
        );

        wp_enqueue_script(
            'quill',
            'https://cdn.quilljs.com/1.3.7/quill.min.js',
            [],
            '1.3.7',
            true
        );

        wp_enqueue_script(
            'avn-mail-builder',
            AVN_PLUGIN_URL . 'assets/js/avn-mail-builder.js',
            [ 'jquery', 'jquery-ui-sortable', 'jquery-ui-draggable', 'quill' ],
            $this->version,
            true
        );

        wp_localize_script( 'avn-admin', 'avnAdmin', [
            'ajaxurl'  => admin_url( 'admin-ajax.php' ),
            'adminurl' => admin_url( '' ),
            'nonce'    => wp_create_nonce( 'avn_admin_nonce' ),
            'i18n'    => [
                'confirm_delete'   => __( 'Wirklich löschen?',                           'av-newsletter' ),
                'saved'            => __( 'Gespeichert!',                                'av-newsletter' ),
                'error'            => __( 'Fehler beim Speichern.',                       'av-newsletter' ),
                'send_confirm'     => __( 'Kampagne an alle gewählten Empfänger senden?', 'av-newsletter' ),
                'campaign_sent'    => __( 'Kampagne wurde gestartet!',                   'av-newsletter' ),
                'campaign_sched'   => __( 'Kampagne wurde geplant.',                     'av-newsletter' ),
                'test_sent'        => __( 'Test-Mail gesendet!',                         'av-newsletter' ),
                'select_template'  => __( 'Bitte zuerst ein Template wählen.',           'av-newsletter' ),
                'copied'           => __( 'kopiert!',                                    'av-newsletter' ),
                'import_no_file'   => __( 'Bitte eine XLSX-Datei auswählen.',              'av-newsletter' ),
                'importing'        => __( 'Importiere…',                                   'av-newsletter' ),
                'import_btn'       => __( 'Importieren',                                   'av-newsletter' ),
            ],
            'site_name' => get_bloginfo( 'name' ),
            'site_url'  => home_url( '/' ),
        ] );
    }

    // ── Pages ───────────────────────────────────────────────────────────────

    /**
     * Blendet Menüeinträge aus, die per URL erreichbar sein sollen,
     * aber nicht im Navigationsmenü erscheinen dürfen.
     */
    public function hide_menu_items() {
        echo '<style>#adminmenu a[href="admin.php?page=avn-mail-builder"] { display: none !important; }
#adminmenu a[href="admin.php?page=avn-kampagnen-historie"] { display: none !important; }</style>';
    }

    public function page_dashboard()           { include AVN_PLUGIN_PATH . 'admin/views/dashboard.php'; }
    public function page_abonnenten()          { include AVN_PLUGIN_PATH . 'admin/views/abonnenten.php'; }
    public function page_gruppen()             { include AVN_PLUGIN_PATH . 'admin/views/gruppen.php'; }
    public function page_mail_templates()      { include AVN_PLUGIN_PATH . 'admin/views/mail-templates.php'; }
    public function page_mail_builder()        { include AVN_PLUGIN_PATH . 'admin/views/mail-builder.php'; }
    public function page_mail_senden()         { include AVN_PLUGIN_PATH . 'admin/views/mail-senden.php'; }
    public function page_kampagnen_historie()  { include AVN_PLUGIN_PATH . 'admin/views/kampagnen-historie.php'; }
    public function page_einstellungen()       { include AVN_PLUGIN_PATH . 'admin/views/einstellungen.php'; }

    // ── AJAX: Abonnenten ────────────────────────────────────────────────────

    public function ajax_save_subscriber() {
        check_ajax_referer( 'avn_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }

        $email = sanitize_email( $_POST['email'] ?? '' );
        if ( ! is_email( $email ) ) {
            wp_send_json_error( 'invalid_email' );
        }

        $data = [
            'email'      => $email,
            'first_name' => sanitize_text_field( $_POST['first_name'] ?? '' ),
            'last_name'  => sanitize_text_field( $_POST['last_name']  ?? '' ),
            'status'     => sanitize_key( $_POST['status'] ?? 'active' ),
        ];

        if ( ! empty( $_POST['id'] ) ) {
            $data['id'] = (int) $_POST['id'];
        } else {
            $data['token']      = bin2hex( random_bytes( 32 ) );
            $data['created_at'] = current_time( 'mysql' );
        }

        $id = AVN_Database::save_subscriber( $data );

        $groups = array_map( 'intval', (array) ( $_POST['groups'] ?? [] ) );
        AVN_Database::set_subscriber_groups( $id, $groups );

        wp_send_json_success( [ 'id' => $id ] );
    }

    public function ajax_delete_subscriber() {
        check_ajax_referer( 'avn_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }
        AVN_Database::delete_subscriber( (int) ( $_POST['id'] ?? 0 ) );
        wp_send_json_success();
    }

    public function ajax_import_subscribers() {
        check_ajax_referer( 'avn_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }

        if ( empty( $_FILES['xlsx_file'] ) || (int) $_FILES['xlsx_file']['error'] !== UPLOAD_ERR_OK ) {
            wp_send_json_error( 'Keine Datei hochgeladen oder Fehler beim Upload.' );
        }

        $ext = strtolower( pathinfo( sanitize_file_name( $_FILES['xlsx_file']['name'] ), PATHINFO_EXTENSION ) );
        if ( $ext !== 'xlsx' ) {
            wp_send_json_error( 'Nur XLSX-Dateien (Excel) sind erlaubt.' );
        }

        $options = [
            'duplicate_handling' => sanitize_key( $_POST['duplicate_handling'] ?? 'skip' ),
            'default_status'     => sanitize_key( $_POST['default_status']     ?? 'active' ),
            'default_group_id'   => ! empty( $_POST['default_group_id'] ) ? (int) $_POST['default_group_id'] : null,
        ];

        $result = AVN_XLSX_Importer::import( $_FILES['xlsx_file']['tmp_name'], $options );

        if ( isset( $result['error'] ) ) {
            wp_send_json_error( $result['error'] );
        }

        wp_send_json_success( $result );
    }

    // ── AJAX: Gruppen ───────────────────────────────────────────────────────

    public function ajax_save_group() {
        check_ajax_referer( 'avn_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }

        $data = [
            'name'        => sanitize_text_field( $_POST['name'] ?? '' ),
            'description' => sanitize_textarea_field( $_POST['description'] ?? '' ),
        ];
        if ( ! empty( $_POST['id'] ) ) {
            $data['id'] = (int) $_POST['id'];
        } else {
            $data['created_at'] = current_time( 'mysql' );
        }

        $id = AVN_Database::save_group( $data );
        wp_send_json_success( [ 'id' => $id ] );
    }

    public function ajax_delete_group() {
        check_ajax_referer( 'avn_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }
        AVN_Database::delete_group( (int) ( $_POST['id'] ?? 0 ) );
        wp_send_json_success();
    }

    // ── AJAX: Mail-Templates ────────────────────────────────────────────────

    public function ajax_save_mail_template() {
        check_ajax_referer( 'avn_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }

        $data = [
            'name'           => sanitize_text_field( $_POST['name'] ?? '' ),
            'subject'        => sanitize_text_field( $_POST['subject'] ?? '' ),
            'body_json'      => wp_unslash( $_POST['body_json'] ?? '[]' ),
            'body_html'      => wp_kses_post( wp_unslash( $_POST['body_html'] ?? '' ) ),
            'trigger_type'   => sanitize_key( $_POST['trigger_type'] ?? 'manual' ),
            'recipient'      => sanitize_key( $_POST['recipient'] ?? 'subscriber' ),
            'recipient_meta' => sanitize_text_field( $_POST['recipient_meta'] ?? '' ),
            'status'         => sanitize_key( $_POST['status'] ?? 'active' ),
        ];

        if ( ! empty( $_POST['id'] ) ) {
            $data['id'] = (int) $_POST['id'];
        } else {
            $data['created_at'] = current_time( 'mysql' );
        }

        $id = AVN_Database::save_mail_template( $data );

        if ( ! $id ) {
            global $wpdb;
            wp_send_json_error( [ 'message' => $wpdb->last_error ?: __( 'Datenbankfehler beim Speichern.', 'av-newsletter' ) ] );
            return;
        }

        wp_send_json_success( [ 'id' => $id ] );
    }

    public function ajax_delete_mail_template() {
        check_ajax_referer( 'avn_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }
        AVN_Database::delete_mail_template( (int) ( $_POST['id'] ?? 0 ) );
        wp_send_json_success();
    }

    public function ajax_get_mail_template() {
        check_ajax_referer( 'avn_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }
        $tpl = AVN_Database::get_mail_template( (int) ( $_GET['id'] ?? 0 ) );
        $tpl ? wp_send_json_success( $tpl ) : wp_send_json_error( 'not_found' );
    }

    // ── AJAX: Kampagne ───────────────────────────────────────────────────────

    public function ajax_create_campaign() {
        check_ajax_referer( 'avn_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }

        $template_id  = (int) ( $_POST['template_id'] ?? 0 );
        $group_id     = ! empty( $_POST['group_id'] )     ? (int) $_POST['group_id'] : null;
        $subject      = sanitize_text_field( $_POST['subject']      ?? '' );
        $sender_name  = sanitize_text_field( $_POST['sender_name']  ?? '' );
        $sender_email = sanitize_email(      $_POST['sender_email'] ?? '' );
        $scheduled_at = sanitize_text_field( $_POST['scheduled_at'] ?? '' );
        $content_html = wp_kses_post( wp_unslash( $_POST['content_html'] ?? '' ) );
        $content_json = wp_unslash( $_POST['content_json'] ?? '' );
        $draft_id     = (int) ( $_POST['draft_id'] ?? 0 );

        if ( ! $template_id ) {
            wp_send_json_error( __( 'Bitte ein Template wählen.', 'av-newsletter' ) );
        }

        // Empfängeranzahl ermitteln
        $args  = [ 'status' => 'active', 'limit' => 99999 ];
        if ( $group_id ) {
            $args['group_id'] = $group_id;
        }
        $count = count( AVN_Database::get_subscribers( $args ) );

        $campaign_data = [
            'template_id'  => $template_id,
            'group_id'     => $group_id,
            'subject'      => $subject,
            'sender_name'  => $sender_name,
            'sender_email' => $sender_email,
            'content_html' => $content_html,
            'content_json' => $content_json,
            'recipient_count' => $count,
            'created_at'   => current_time( 'mysql' ),
        ];

        // Draft löschen, wenn Kampagne aus einem Entwurf gestartet wird
        if ( $draft_id ) {
            $existing = AVN_Database::get_campaign( $draft_id );
            if ( $existing && $existing->status === 'draft' ) {
                AVN_Database::delete_campaign( $draft_id );
            }
        }

        if ( $scheduled_at ) {
            // Geplant: Kampagne speichern, Cron sendet sie
            $send_ts = strtotime( $scheduled_at );
            if ( ! $send_ts || $send_ts <= time() ) {
                wp_send_json_error( __( 'Ungültiger Versandzeitpunkt.', 'av-newsletter' ) );
            }
            $campaign_data['scheduled_at'] = gmdate( 'Y-m-d H:i:s', $send_ts );
            $campaign_data['status']       = 'scheduled';
            $id = AVN_Database::save_campaign( $campaign_data );
            wp_send_json_success( [ 'status' => 'scheduled', 'id' => $id, 'count' => $count ] );
        } else {
            // Sofort senden
            $campaign_data['status'] = 'sending';
            $id = AVN_Database::save_campaign( $campaign_data );
            AVN_Mail_Handler::send_campaign(
                $template_id, $group_id, $subject, $sender_name, $sender_email, $id
            );
            AVN_Database::update_campaign_status( $id, 'sent', current_time( 'mysql' ) );
            wp_send_json_success( [ 'status' => 'sent', 'id' => $id, 'count' => $count ] );
        }
    }

    public function ajax_send_test_mail() {
        check_ajax_referer( 'avn_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }

        $template_id  = (int) ( $_POST['template_id'] ?? 0 );
        $test_email   = sanitize_email( $_POST['test_email'] ?? '' );
        $content_html = wp_kses_post( wp_unslash( $_POST['content_html'] ?? '' ) );
        $content_json = wp_unslash( $_POST['content_json'] ?? '' );

        if ( ! $template_id ) {
            wp_send_json_error( __( 'Bitte ein Template wählen.', 'av-newsletter' ) );
        }
        if ( ! is_email( $test_email ) ) {
            wp_send_json_error( __( 'Ungültige E-Mail-Adresse.', 'av-newsletter' ) );
        }

        $ok = AVN_Mail_Handler::send_test_mail( $template_id, $test_email, $content_html, $content_json );
        $ok ? wp_send_json_success() : wp_send_json_error( __( 'Senden fehlgeschlagen.', 'av-newsletter' ) );
    }

    // ── AJAX: Draft speichern / löschen ──────────────────────────────────────

    public function ajax_save_draft() {
        check_ajax_referer( 'avn_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }

        $template_id    = (int) ( $_POST['template_id']  ?? 0 );
        $group_id_raw   = sanitize_text_field( $_POST['group_id'] ?? '' );
        // 'all' = alle Abonnenten → NULL in DB; leerer Wert = kein Empfänger gewählt
        $group_id       = ( $group_id_raw === 'all' || $group_id_raw === '' ) ? null : (int) $group_id_raw;
        $group_id_is_all = ( $group_id_raw === 'all' );
        $subject        = sanitize_text_field( $_POST['subject']      ?? '' );
        $sender_name    = sanitize_text_field( $_POST['sender_name']  ?? '' );
        $sender_email   = sanitize_email(      $_POST['sender_email'] ?? '' );
        $content_html   = wp_kses_post( wp_unslash( $_POST['content_html'] ?? '' ) );
        $content_json   = wp_unslash( $_POST['content_json'] ?? '' );
        $scheduled_at   = sanitize_text_field( $_POST['scheduled_at'] ?? '' );
        $draft_id       = (int) ( $_POST['draft_id'] ?? 0 );

        $args = [ 'status' => 'active', 'limit' => 99999 ];
        if ( ! $group_id_is_all && $group_id ) { $args['group_id'] = $group_id; }
        $count = count( AVN_Database::get_subscribers( $args ) );

        $send_ts = $scheduled_at ? strtotime( $scheduled_at ) : null;

        $data = [
            'template_id'     => $template_id,
            'group_id'        => $group_id,
            'subject'         => $subject,
            'sender_name'     => $sender_name,
            'sender_email'    => $sender_email,
            'content_html'    => $content_html,
            'content_json'    => $content_json,
            'recipient_count' => $count,
            'scheduled_at'    => ( $send_ts && $send_ts > time() ) ? gmdate( 'Y-m-d H:i:s', $send_ts ) : null,
            'status'          => 'draft',
        ];

        if ( $draft_id ) {
            $data['id'] = $draft_id;
        } else {
            $data['created_at'] = current_time( 'mysql' );
        }

        $id = AVN_Database::save_campaign( $data );
        wp_send_json_success( [ 'id' => $id ] );
    }

    public function ajax_delete_draft() {
        check_ajax_referer( 'avn_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }

        $id       = (int) ( $_POST['id'] ?? 0 );
        $campaign = $id ? AVN_Database::get_campaign( $id ) : null;

        if ( ! $campaign || $campaign->status !== 'draft' ) {
            wp_send_json_error( 'not_found' );
        }

        AVN_Database::delete_campaign( $id );
        wp_send_json_success();
    }

    public function ajax_duplicate_campaign() {
        check_ajax_referer( 'avn_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }

        $id     = (int) ( $_POST['id'] ?? 0 );
        $new_id = $id ? AVN_Database::duplicate_campaign( $id ) : null;

        if ( ! $new_id ) {
            wp_send_json_error( 'not_found' );
        }

        wp_send_json_success( [ 'id' => $new_id ] );
    }

    // ── AJAX: Einstellungen ──────────────────────────────────────────────────

    public function ajax_save_settings() {
        check_ajax_referer( 'avn_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }

        $options = [
            'avn_sender_name'      => sanitize_text_field( $_POST['avn_sender_name']      ?? '' ),
            'avn_sender_email'     => sanitize_email(       $_POST['avn_sender_email']     ?? '' ),
            'avn_unsubscribe_page' => (int)                 ( $_POST['avn_unsubscribe_page'] ?? 0 ),
            'avn_doi_enabled'      => isset( $_POST['avn_doi_enabled'] ) ? '1' : '0',
            'avn_log_retention'    => max( 1, min( 365, (int) ( $_POST['avn_log_retention'] ?? 90 ) ) ),
            // E-Mail-Versand
            'avn_mail_method'     => in_array( $_POST['avn_mail_method'] ?? '', [ 'wp_mail', 'smtp' ], true )
                                     ? $_POST['avn_mail_method'] : 'wp_mail',
            'avn_smtp_host'       => sanitize_text_field( $_POST['avn_smtp_host']      ?? '' ),
            'avn_smtp_port'       => max( 1, min( 65535, (int) ( $_POST['avn_smtp_port'] ?? 587 ) ) ),
            'avn_smtp_encryption' => in_array( $_POST['avn_smtp_encryption'] ?? '', [ 'tls', 'ssl', 'none' ], true )
                                     ? $_POST['avn_smtp_encryption'] : 'tls',
            'avn_smtp_auth'       => isset( $_POST['avn_smtp_auth'] ) ? '1' : '0',
            'avn_smtp_user'       => sanitize_text_field( $_POST['avn_smtp_user'] ?? '' ),
        ];

        foreach ( $options as $key => $value ) {
            update_option( $key, $value );
        }

        // Passwort nur überschreiben wenn ein neues eingegeben wurde
        if ( ! empty( $_POST['avn_smtp_pass'] ) ) {
            update_option( 'avn_smtp_pass', $_POST['avn_smtp_pass'] );
        }

        wp_send_json_success();
    }

    public function ajax_log_delete_entry() {
        check_ajax_referer( 'avn_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'unauthorized', 403 ); }
        global $wpdb;
        $id = (int) ( $_POST['id'] ?? 0 );
        if ( ! $id ) { wp_send_json_error( 'invalid_id' ); }
        $wpdb->delete( $wpdb->prefix . 'av_mail_log', [ 'id' => $id ], [ '%d' ] );
        wp_send_json_success();
    }

    public function ajax_log_clear() {
        check_ajax_referer( 'avn_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'unauthorized', 403 ); }
        global $wpdb;
        $scope = sanitize_key( $_POST['scope'] ?? 'all' );
        if ( $scope === 'failed' ) {
            $wpdb->delete( $wpdb->prefix . 'av_mail_log', [ 'status' => 'failed' ], [ '%s' ] );
        } else {
            $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}av_mail_log" );
        }
        wp_send_json_success( [ 'deleted' => $wpdb->rows_affected ] );
    }

    public function ajax_cron_run_now() {
        check_ajax_referer( 'avn_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }
        AVN_Mail_Handler::process_queue();
        global $wpdb;
        $queued  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}av_mail_log WHERE status = 'queued'" );
        $failed  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}av_mail_log WHERE status = 'failed'" );
        $sent_today = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}av_mail_log WHERE status = 'sent' AND created_at >= %s",
            gmdate( 'Y-m-d 00:00:00' )
        ) );
        wp_send_json_success( [ 'queued' => $queued, 'failed' => $failed, 'sent_today' => $sent_today ] );
    }

    public function ajax_cron_retry_failed() {
        check_ajax_referer( 'avn_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'av_mail_log',
            [ 'status' => 'queued', 'error_message' => null ],
            [ 'status' => 'failed' ],
            [ '%s', '%s' ],
            [ '%s' ]
        );
        $requeued = $wpdb->rows_affected;
        wp_send_json_success( [ 'requeued' => $requeued ] );
    }

    public function ajax_send_smtp_test() {
        check_ajax_referer( 'avn_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }

        $test_email = sanitize_email( $_POST['test_email'] ?? '' );
        if ( ! is_email( $test_email ) ) {
            wp_send_json_error( 'Ungültige E-Mail-Adresse.' );
        }

        $sender_name  = sanitize_text_field( get_option( 'avn_sender_name',  get_bloginfo( 'name' ) ) );
        // Entferne Zeilenumbrüche aus dem Namen, damit Header nicht gebrochen werden
        $sender_name  = preg_replace( "/[\r\n]+/", ' ', $sender_name );
        $sender_email = sanitize_email( get_option( 'avn_sender_email', get_option( 'admin_email' ) ) );
        if ( ! is_email( $sender_email ) ) {
            $sender_email = sanitize_email( get_option( 'admin_email' ) );
        }
        $from_header  = 'From: ' . $sender_name . ' <' . $sender_email . '>';

        $sent = wp_mail(
            $test_email,
            '[Test] AV Newsletter – SMTP-Konfiguration',
            '<p>Diese Test-Mail wurde von <strong>AV Newsletter</strong> versendet, um die E-Mail-Konfiguration zu prüfen.</p>',
            [ 'Content-Type: text/html; charset=UTF-8', $from_header ]
        );

        if ( $sent ) {
            $debug = '';
            if ( method_exists( 'AVN_Mail_Handler', 'get_smtp_debug_output' ) ) {
                $debug = AVN_Mail_Handler::get_smtp_debug_output();
            }
            wp_send_json_success( [ 'debug' => trim( $debug ) ] );
        } else {
            global $phpmailer;
            $err = isset( $phpmailer ) && ! empty( $phpmailer->ErrorInfo )
                ? $phpmailer->ErrorInfo
                : 'wp_mail() hat false zurückgegeben.';
            $debug = '';
            if ( method_exists( 'AVN_Mail_Handler', 'get_smtp_debug_output' ) ) {
                $debug = AVN_Mail_Handler::get_smtp_debug_output();
                if ( $debug !== '' ) {
                    $err .= ' | SMTP Debug: ' . trim( $debug );
                }
            }
            wp_send_json_error( [ 'message' => $err, 'debug' => trim( $debug ) ] );
        }
    }

    /**
     * Universeller Test-Mail-Handler: unterstützt beide Versandmethoden (wp_mail + SMTP)
     * AJAX-Endpunkt: avn_test_mail_settings
     */
    public function ajax_test_mail_settings() {
        check_ajax_referer( 'avn_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized', 403 );
        }

        $test_email = sanitize_email( $_POST['test_email'] ?? '' );
        if ( ! is_email( $test_email ) ) {
            wp_send_json_error( __( 'Ungültige E-Mail-Adresse.', 'av-newsletter' ) );
        }

        // Dummy-Template für Test (da send_test_mail ein Template braucht, nutzen wir die Standard-Einstellung)
        $templates = AVN_Database::get_mail_templates( 0 );
        $template_id = ! empty( $templates ) ? (int) $templates[0]->id : 0;

        if ( ! $template_id ) {
            wp_send_json_error( __( 'Kein Mail-Template verfügbar. Bitte erstellen Sie zuerst ein Template.', 'av-newsletter' ) );
        }

        $ok = AVN_Mail_Handler::send_test_mail( $template_id, $test_email, '', '' );

        if ( $ok ) {
            $method = get_option( 'avn_mail_method', 'wp_mail' );
            $msg = $method === 'smtp'
                ? __( 'Test-Mail über SMTP versendet!', 'av-newsletter' )
                : __( 'Test-Mail über WordPress versendet!', 'av-newsletter' );
            wp_send_json_success( [ 'message' => $msg ] );
        } else {
            wp_send_json_error( __( 'Versand fehlgeschlagen.', 'av-newsletter' ) );
        }
    }
}

