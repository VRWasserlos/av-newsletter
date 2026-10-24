<?php
/**
 * Abonnenten-Verwaltung: Upsert, Opt-In, Opt-Out
 *
 * @package VRW_Newsletter
 * @author  Kai Naumann <info@vereinsring-wasserlos.de>
 * @version 1.0.7
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AVN_Subscriber {

    /**
     * Subscriber anlegen oder updaten, Token generieren.
     *
     * @param  array $data  keys: email, first_name, last_name
     * @return int  Subscriber-ID
     */
    public static function upsert( array $data ): int {
        $email    = sanitize_email( $data['email'] );
        $existing = AVN_Database::get_subscriber_by_email( $email );

        if ( $existing ) {
            $update = [];
            if ( ! empty( $data['first_name'] ) && $existing->first_name === '' ) {
                $update['first_name'] = sanitize_text_field( $data['first_name'] );
            }
            if ( ! empty( $data['last_name'] ) && $existing->last_name === '' ) {
                $update['last_name'] = sanitize_text_field( $data['last_name'] );
            }
            if ( $update ) {
                $update['id'] = $existing->id;
                AVN_Database::save_subscriber( $update );
            }
            return (int) $existing->id;
        }

        $token = bin2hex( random_bytes( 32 ) );
        return AVN_Database::save_subscriber( [
            'email'      => $email,
            'first_name' => sanitize_text_field( $data['first_name'] ?? '' ),
            'last_name'  => sanitize_text_field( $data['last_name']  ?? '' ),
            'status'     => 'pending',
            'token'      => $token,
            'created_at' => current_time( 'mysql' ),
        ] );
    }

    /**
     * Double-Opt-In bestätigen
     */
    public static function confirm_by_token( string $token ): bool {
        global $wpdb;
        $sub = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}av_subscribers WHERE token = %s AND status = 'pending'",
                $token
            )
        );

        if ( ! $sub ) {
            return false;
        }

        AVN_Database::save_subscriber( [
            'id'     => (int) $sub->id,
            'status' => 'active',
        ] );
        return true;
    }

    /**
     * Abmelden per Token
     */
    public static function unsubscribe_by_token( string $token ): bool {
        global $wpdb;
        $sub = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}av_subscribers WHERE token = %s",
                $token
            )
        );

        if ( ! $sub ) {
            return false;
        }

        AVN_Database::save_subscriber( [
            'id'     => (int) $sub->id,
            'status' => 'unsubscribed',
        ] );
        return true;
    }

    /**
     * Status-Label für Anzeige
     */
    public static function status_label( string $status ): string {
        $map = [
            'active'       => __( 'Aktiv',       'av-newsletter' ),
            'pending'      => __( 'Ausstehend',  'av-newsletter' ),
            'unsubscribed' => __( 'Abgemeldet',  'av-newsletter' ),
            'bounced'      => __( 'Bounce',      'av-newsletter' ),
        ];
        return $map[ $status ] ?? esc_html( $status );
    }
}
