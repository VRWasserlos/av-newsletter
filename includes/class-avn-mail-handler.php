<?php
/**
 * Mail-Handler: Template rendern und versenden, Queue verarbeiten
 *
 * @package VRW_Newsletter
 * @author  Kai Naumann <info@vereinsring-wasserlos.de>
 * @version 1.0.7
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AVN_Mail_Handler {

    /**
     * SMTP-Debug-Ausgabe für fehlgeschlagene Versandversuche.
     *
     * @var string
     */
    private static $smtp_debug_output = '';

    /**
     * SMTP-Konfiguration via phpmailer_init-Hook, wenn SMTP als Methode gewählt.
     * Wird bei Plugin-Init einmalig registriert.
     */
    public static function init_smtp_hook() {
        if ( get_option( 'avn_mail_method', 'wp_mail' ) === 'smtp' ) {
            add_action( 'phpmailer_init', [ self::class, 'configure_phpmailer' ] );
        }
    }

    /**
     * Konfiguriert PHPMailer mit den gespeicherten SMTP-Einstellungen.
     *
     * @param PHPMailer\PHPMailer\PHPMailer $phpmailer
     */
    public static function configure_phpmailer( $phpmailer ) {
        $host       = get_option( 'avn_smtp_host',       '' );
        $port       = (int) get_option( 'avn_smtp_port',       587 );
        $encryption = get_option( 'avn_smtp_encryption',  'tls' );
        $auth       = get_option( 'avn_smtp_auth',         '1' );
        $user       = get_option( 'avn_smtp_user',         '' );
        $pass       = get_option( 'avn_smtp_pass',         '' );

        if ( ! $host ) {
            return; // nicht konfiguriert – nichts ändern
        }

        self::$smtp_debug_output = '';
        $phpmailer->SMTPDebug   = 2;
        $phpmailer->Debugoutput = [ self::class, 'phpmailer_debug_output' ];

        $phpmailer->isSMTP();
        $phpmailer->Host = $host;
        $phpmailer->Port = $port;

        if ( $encryption === 'ssl' ) {
            $phpmailer->SMTPSecure = 'ssl';
        } elseif ( $encryption === 'tls' ) {
            $phpmailer->SMTPSecure = 'tls';
        } else {
            $phpmailer->SMTPSecure = '';
            $phpmailer->SMTPAutoTLS = false;
        }

        if ( $auth === '1' && $user ) {
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = $user;
            $phpmailer->Password = $pass;
        } else {
            $phpmailer->SMTPAuth = false;
        }
    }

    /**
     * Konvertiert ein Block-Array (JSON) in fertiges HTML für den Versand.
     *
     * @param string $blocks_json  JSON-String des Block-Arrays
     * @return string              Vollständige HTML-E-Mail
     */
    public static function blocks_to_html( string $blocks_json ): string {
        $blocks = json_decode( $blocks_json, true );
        if ( ! is_array( $blocks ) ) {
            return '';
        }

        $css  = 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;margin:0;background:#f0f0f0}';
        $css .= '.wrapper{max-width:600px;margin:0 auto;background:#fff;padding:32px 24px}';
        $css .= 'h1,h2,h3{margin:0 0 12px}p{margin:0 0 10px}a{color:#3b82f6}';
        $css .= '.cols2{display:table;width:100%}.cols2 > div{display:table-cell;width:50%;padding:0 8px;vertical-align:top}';
        $css .= '.social a{display:inline-block;margin:0 6px;color:#3b82f6;text-decoration:none}';
        $css .= '.footer{font-size:12px;color:#9ca3af;text-align:center;padding-top:16px}';

        $html  = '<!DOCTYPE html><html><head><meta charset="utf-8">';
        $html .= '<meta name="viewport" content="width=device-width,initial-scale=1">';
        $html .= '<style>' . $css . '</style></head><body><div class="wrapper">';

        foreach ( $blocks as $block ) {
            $html .= self::render_block_html( $block );
        }

        $html .= '</div></body></html>';
        return $html;
    }

    /**
     * Einen einzelnen Block als HTML rendern (serverseitig, spiegelt die JS-Logik).
     *
     * @param array $block
     * @return string
     */
    public static function render_block_html( array $block ): string {
        $type = $block['type'] ?? '';
        switch ( $type ) {
            case 'content_area':
                return '{{mail_content}}';
            case 'heading':
                $tag   = preg_replace( '/[^a-z0-9]/i', '', $block['level'] ?? 'h2' );
                $align = esc_attr( $block['align'] ?? 'left' );
                $color = esc_attr( $block['color'] ?? '#1f2937' );
                return "<{$tag} style=\"text-align:{$align};color:{$color}\">" . esc_html( $block['text'] ?? '' ) . "</{$tag}>";
            case 'text':
                $align = esc_attr( $block['align'] ?? 'left' );
                $color = esc_attr( $block['color'] ?? '#374151' );
                return "<p style=\"text-align:{$align};color:{$color}\">" . nl2br( esc_html( $block['text'] ?? '' ) ) . '</p>';
            case 'image':
                if ( empty( $block['url'] ) ) { return ''; }
                $url   = esc_url( $block['url'] );
                $alt   = esc_attr( $block['alt']   ?? '' );
                $width = (int) ( $block['width'] ?? 100 );
                return "<div style=\"text-align:center\"><img src=\"{$url}\" alt=\"{$alt}\" style=\"max-width:{$width}%;height:auto\"></div>";
            case 'button':
                $url   = esc_url(  $block['url']        ?? '#' );
                $text  = esc_html( $block['text']       ?? 'Button' );
                $align = esc_attr( $block['align']      ?? 'center' );
                $bg    = esc_attr( $block['bg_color']   ?? '#3b82f6' );
                $tc    = esc_attr( $block['text_color'] ?? '#ffffff' );
                return "<div style=\"text-align:{$align}\"><a href=\"{$url}\" style=\"display:inline-block;padding:12px 24px;background:{$bg};color:{$tc};text-decoration:none;border-radius:6px\">{$text}</a></div>";
            case 'divider':
                $color = esc_attr( $block['color'] ?? '#e5e7eb' );
                return "<hr style=\"border:none;border-top:1px solid {$color};margin:16px 0\">";
            case 'spacer':
                $h = (int) ( $block['height'] ?? 24 );
                return "<div style=\"height:{$h}px\"></div>";
            case 'columns2':
                $c1 = esc_html( $block['col1'] ?? '' );
                $c2 = esc_html( $block['col2'] ?? '' );
                return "<div class=\"cols2\"><div>{$c1}</div><div>{$c2}</div></div>";
            case 'social':
                $links = '';
                foreach ( (array) ( $block['links'] ?? [] ) as $l ) {
                    $href  = esc_url(  $l['url']   ?? '#' );
                    $label = esc_html( $l['label'] ?? '' );
                    $links .= "<a href=\"{$href}\">{$label}</a>";
                }
                return "<div class=\"social\">{$links}</div>";
            case 'footer':
                return '<div class="footer">' . wp_kses_post( $block['text'] ?? '' ) . '</div>';
            case 'dynamic':
                return esc_html( $block['placeholder'] ?? '' );
            default:
                return '';
        }
    }

    /**
     * Hook-Callback: wird vom av-forms Plugin nach einer Formular-Einreichung aufgerufen.
     * 3. Parameter ist $post_data (Array der gesendeten Formularfelder)
     */
    public static function on_submission_from_hook( $submission_id, $form_id, $post_data ) {
        // Subscriber aus Post-Daten anlegen/updaten
        $email = sanitize_email( $post_data['email'] ?? '' );
        if ( ! $email ) {
            return;
        }

        $subscriber_id = AVN_Subscriber::upsert( [
            'email'      => $email,
            'first_name' => $post_data['first_name'] ?? ( $post_data['vorname'] ?? '' ),
            'last_name'  => $post_data['last_name']  ?? ( $post_data['nachname'] ?? '' ),
        ] );

        // Optional: form_id-abhängige Gruppe zuweisen
        $form_group = (int) get_option( 'avn_form_group_' . (int) $form_id, 0 );
        if ( $form_group && $subscriber_id ) {
            $existing_groups = AVN_Database::get_subscriber_groups( $subscriber_id );
            if ( ! in_array( (string) $form_group, $existing_groups, true ) ) {
                $existing_groups[] = $form_group;
                AVN_Database::set_subscriber_groups( $subscriber_id, $existing_groups );
            }
        }

        self::on_submission( $submission_id, $form_id, $subscriber_id );
    }

    /**
     * Mail-Vorlagen für diesen Trigger in Queue stellen
     */
    public static function on_submission( $submission_id, $form_id, $subscriber_id ) {
        $templates = AVN_Database::get_mail_templates( $form_id );
        foreach ( $templates as $tpl ) {
            if ( $tpl->status !== 'active' ) {
                continue;
            }
            if ( $tpl->trigger_type !== 'on_submit' ) {
                continue;
            }
            self::queue_template( $tpl, $submission_id, $subscriber_id );
        }
    }

    /**
     * Mail in die Queue stellen
     */
    public static function queue_template( $tpl, $submission_id = null, $subscriber_id = null ) {
        $recipient_email = self::resolve_recipient( $tpl, $subscriber_id );
        if ( ! $recipient_email ) {
            return;
        }

        AVN_Database::log_mail( [
            'template_id'     => (int) $tpl->id,
            'submission_id'   => $submission_id ? (int) $submission_id : null,
            'recipient_email' => $recipient_email,
            'subject'         => $tpl->subject,
            'status'          => 'queued',
            'created_at'      => current_time( 'mysql' ),
        ] );
    }

    /**
     * Cron: Queue abarbeiten (max. 20 Mails pro Lauf)
     */
    public static function process_queue() {
        global $wpdb;
        $batch = max( 1, (int) get_option( 'avn_cron_batch_size', 20 ) );
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}av_mail_log WHERE status = 'queued' ORDER BY created_at ASC LIMIT %d",
                $batch
            )
        );

        $sender_name  = sanitize_text_field( get_option( 'avn_sender_name',  get_bloginfo( 'name' ) ) );
        $sender_name  = preg_replace( "/[\r\n]+/", ' ', $sender_name );
        $sender_email = sanitize_email( get_option( 'avn_sender_email', get_option( 'admin_email' ) ) );
        if ( ! is_email( $sender_email ) ) {
            $sender_email = sanitize_email( get_option( 'admin_email' ) );
        }

        foreach ( $items as $item ) {
            // Per-Item Sender-Overrides (aus Kampagne gespeichert)
            $item_sender_name  = ! empty( $item->sender_name )  ? sanitize_text_field( $item->sender_name )  : $sender_name;
            $item_sender_name  = preg_replace( "/[\r\n]+/", ' ', $item_sender_name );
            $item_sender_email = ! empty( $item->sender_email ) ? sanitize_email( $item->sender_email ) : $sender_email;
            if ( ! is_email( $item_sender_email ) ) {
                $item_sender_email = $sender_email;
            }
            $from_header       = 'From: ' . $item_sender_name . ' <' . $item_sender_email . '>';

            $tpl       = $item->template_id ? AVN_Database::get_mail_template( (int) $item->template_id ) : null;
            $body_html = $tpl ? $tpl->body_html : '';

            // Inhalt aus zugehöriger Kampagne einsetzen
            if ( ! empty( $item->campaign_id ) ) {
                $campaign = AVN_Database::get_campaign( (int) $item->campaign_id );
                if ( $campaign ) {
                    if ( ! empty( $campaign->content_json ) ) {
                        // Neue Builder-basierte Kampagne: HTML aus Blöcken generieren
                        $body_html = self::blocks_to_html( $campaign->content_json );
                        $body_html = str_replace( '{{mail_content}}', '', $body_html ); // Fallback-Platzhalter entfernen
                    } elseif ( ! empty( $campaign->content_html ) ) {
                        // Legacy: Template + content_html Platzhalter
                        $body_html = str_replace( '{{mail_content}}', $campaign->content_html, $body_html );
                    }
                }
            }
            // Verbleibenden Platzhalter entfernen, falls kein Inhalt vorhanden
            $body_html = str_replace( '{{mail_content}}', '', $body_html );

            $subscriber = AVN_Database::get_subscriber_by_email( $item->recipient_email );
            $body_html  = self::replace_placeholders( $body_html, $subscriber, $item->recipient_email );
            $subject    = self::replace_placeholders( $item->subject, $subscriber, $item->recipient_email );

            $sent = wp_mail(
                $item->recipient_email,
                $subject,
                $body_html,
                [ 'Content-Type: text/html; charset=UTF-8', $from_header ]
            );

            if ( $sent ) {
                AVN_Database::update_mail_log_status( $item->id, 'sent' );
            } else {
                global $phpmailer;
                $error_message = 'wp_mail() returned false';
                if ( isset( $phpmailer ) && ! empty( $phpmailer->ErrorInfo ) ) {
                    $error_message .= ' | ErrorInfo: ' . $phpmailer->ErrorInfo;
                }
                if ( self::$smtp_debug_output !== '' ) {
                    $error_message .= ' | SMTP Debug: ' . trim( self::$smtp_debug_output );
                }
                AVN_Database::update_mail_log_status( $item->id, 'failed', $error_message );
            }
        }

        // Kampagnen-Status aktualisieren wenn Queue leer
        $campaign_ids = array_unique( array_filter( array_column( $items, 'campaign_id' ) ) );
        foreach ( $campaign_ids as $cid ) {
            $stats = AVN_Database::get_campaign_mail_stats( (int) $cid );
            if ( $stats['queued'] === 0 ) {
                // Alle abgearbeitet
                if ( $stats['failed'] > 0 && $stats['sent'] === 0 ) {
                    // Alle fehlgeschlagen
                    AVN_Database::update_campaign_status( $cid, 'failed', current_time( 'mysql' ) );
                } elseif ( $stats['failed'] > 0 ) {
                    // Teils fehlgeschlagen
                    AVN_Database::update_campaign_status( $cid, 'sent_partial', current_time( 'mysql' ) );
                } else {
                    // Alle erfolgreich
                    AVN_Database::update_campaign_status( $cid, 'sent', current_time( 'mysql' ) );
                }
            }
        }
    }

    /**
     * Manuell eine Kampagne an eine Gruppe oder alle Abonnenten senden
     *
     * @param int    $template_id   Mail-Template-ID
     * @param int    $group_id      Gruppen-ID (null = alle aktiven)
     * @param string $subject       Betreff-Überschreibung (leer = aus Template)
     * @param string $sender_name   Absendername (leer = aus Einstellungen)
     * @param string $sender_email  Absender-E-Mail (leer = aus Einstellungen)
     * @param int    $campaign_id   Zugehörige Kampagnen-ID (0 = keine)
     */
    public static function send_campaign(
        int $template_id,
        $group_id     = null,
        string $subject      = '',
        string $sender_name  = '',
        string $sender_email = '',
        int $campaign_id = 0
    ) {
        $tpl = AVN_Database::get_mail_template( $template_id );
        if ( ! $tpl ) {
            return 0;
        }

        $args = [ 'status' => 'active', 'limit' => 5000 ];
        if ( $group_id ) {
            $args['group_id'] = (int) $group_id;
        }

        $subscribers     = AVN_Database::get_subscribers( $args );
        $subject_to_use  = $subject      ?: $tpl->subject;
        $s_name_to_use   = $sender_name  ?: get_option( 'avn_sender_name',  get_bloginfo( 'name' ) );
        $s_name_to_use   = sanitize_text_field( $s_name_to_use );
        $s_name_to_use   = preg_replace( "/[\r\n]+/", ' ', $s_name_to_use );
        $s_email_to_use  = $sender_email ?: get_option( 'avn_sender_email', get_option( 'admin_email' ) );
        $s_email_to_use  = sanitize_email( $s_email_to_use );
        if ( ! is_email( $s_email_to_use ) ) {
            $s_email_to_use = sanitize_email( get_option( 'admin_email' ) );
        }
        $count           = 0;

        foreach ( $subscribers as $sub ) {
            AVN_Database::log_mail( [
                'campaign_id'     => $campaign_id ?: null,
                'template_id'     => (int) $template_id,
                'submission_id'   => null,
                'recipient_email' => $sub->email,
                'subject'         => $subject_to_use,
                'sender_name'     => $s_name_to_use,
                'sender_email'    => $s_email_to_use,
                'status'          => 'queued',
                'created_at'      => current_time( 'mysql' ),
            ] );
            $count++;
        }

        return $count;
    }

    /**
     * Test-Mail an eine einzelne Adresse schicken
     */
    public static function send_test_mail( int $template_id, string $test_email, string $content_html = '', string $content_json = '' ): bool {
        $tpl = AVN_Database::get_mail_template( $template_id );
        if ( ! $tpl || ! is_email( $test_email ) ) {
            return false;
        }

        $sender_name  = sanitize_text_field( get_option( 'avn_sender_name',  get_bloginfo( 'name' ) ) );
        $sender_name  = preg_replace( "/[\r\n]+/", ' ', $sender_name );
        $sender_email = sanitize_email( get_option( 'avn_sender_email', get_option( 'admin_email' ) ) );
        if ( ! is_email( $sender_email ) ) {
            $sender_email = sanitize_email( get_option( 'admin_email' ) );
        }
        $from_header  = 'From: ' . $sender_name . ' <' . $sender_email . '>';

        // Temporär SMTP-Hook registrieren, nur für diesen Testversand (wenn SMTP konfiguriert)
        $use_smtp = get_option( 'avn_mail_method', 'wp_mail' ) === 'smtp';
        if ( $use_smtp ) {
            self::$smtp_debug_output = '';
            add_action( 'phpmailer_init', [ self::class, 'configure_phpmailer' ] );
        }

        if ( $content_json !== '' ) {
            // Builder-Blöcke → HTML
            $body_html = self::blocks_to_html( $content_json );
            $body_html = str_replace( '{{mail_content}}', '', $body_html );
        } else {
            $body_html = $tpl->body_html ?: '';
            if ( $content_html !== '' ) {
                $body_html = str_replace( '{{mail_content}}', $content_html, $body_html );
            }
            $body_html = str_replace( '{{mail_content}}', '', $body_html );
        }
        $body_html = self::replace_placeholders( $body_html, null, $test_email );
        $subject   = '[TEST] ' . self::replace_placeholders( $tpl->subject, null, $test_email );

        $result = (bool) wp_mail(
            $test_email,
            $subject,
            $body_html,
            [ 'Content-Type: text/html; charset=UTF-8', $from_header ]
        );

        if ( $use_smtp ) {
            remove_action( 'phpmailer_init', [ self::class, 'configure_phpmailer' ] );
        }

        return $result;
    }

    /**
     * Geplante Kampagnen feuern (wird via Cron aufgerufen)
     */
    public static function fire_scheduled_campaigns() {
        $campaigns = AVN_Database::get_scheduled_campaigns();
        foreach ( $campaigns as $campaign ) {
            AVN_Database::update_campaign_status( $campaign->id, 'sending' );
            self::send_campaign(
                (int) $campaign->template_id,
                $campaign->group_id ? (int) $campaign->group_id : null,
                $campaign->subject        ?? '',
                $campaign->sender_name    ?? '',
                $campaign->sender_email   ?? '',
                (int) $campaign->id
            );
            // Status wird durch process_queue() gesetzt sobald Queue leer ist
        }
    }

    // ── Hilfsmethoden ────────────────────────────────────────────────────────

    private static function replace_placeholders( string $text, $subscriber, string $email ): string {
        $token = $subscriber ? ( $subscriber->token ?? '' ) : '';

        $unsubscribe_url = add_query_arg( [
            'avn_action' => 'unsubscribe',
            'avn_token'  => $token,
        ], home_url( '/' ) );

        $confirm_url = add_query_arg( [
            'avn_action' => 'confirm',
            'avn_token'  => $token,
        ], home_url( '/' ) );

        $page_id = (int) get_option( 'avn_unsubscribe_page', 0 );
        if ( $page_id ) {
            $unsubscribe_url = add_query_arg( [
                'avn_action' => 'unsubscribe',
                'avn_token'  => $token,
            ], get_permalink( $page_id ) );
        }

        $replacements = [
            '{{first_name}}'       => $subscriber ? esc_html( $subscriber->first_name ?? '' ) : '',
            '{{last_name}}'        => $subscriber ? esc_html( $subscriber->last_name  ?? '' ) : '',
            '{{email}}'            => esc_html( $email ),
            '{{site_name}}'        => esc_html( get_bloginfo( 'name' ) ),
            '{{site_url}}'         => esc_url( home_url( '/' ) ),
            '{{unsubscribe_link}}' => '<a href="' . esc_url( $unsubscribe_url ) . '">'
                                    . esc_html__( 'Abmelden', 'av-newsletter' )
                                    . '</a>',
            '{{confirm_link}}'     => '<a href="' . esc_url( $confirm_url ) . '">'
                                    . esc_html__( 'Anmeldung bestätigen', 'av-newsletter' )
                                    . '</a>',
        ];

        return str_replace( array_keys( $replacements ), array_values( $replacements ), $text );
    }

    public static function phpmailer_debug_output( $message, $level = 0 ) {
        self::$smtp_debug_output .= trim( $message ) . "\n";
    }

    public static function get_smtp_debug_output(): string {
        return self::$smtp_debug_output;
    }

    private static function resolve_recipient( $tpl, $subscriber_id ): string {
        switch ( $tpl->recipient ) {
            case 'subscriber':
                if ( $subscriber_id ) {
                    $sub = AVN_Database::get_subscriber( (int) $subscriber_id );
                    return $sub ? $sub->email : '';
                }
                return '';

            case 'admin':
                return get_option( 'admin_email', '' );

            case 'custom':
                return sanitize_email( $tpl->recipient_meta ?? '' );

            case 'group':
                return '';

            default:
                return '';
        }
    }
}

// Hook: Integration mit av-forms (optional – funktioniert nur wenn av-forms aktiv)
add_action( 'av_form_submitted', [ 'AVN_Mail_Handler', 'on_submission_from_hook' ], 10, 3 );
