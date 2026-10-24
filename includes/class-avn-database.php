<?php
/**
 * Datenbank-Methoden für Newsletter: Abonnenten, Gruppen, Mail-Templates, Log
 *
 * @package VRW_Newsletter
 * @author  Kai Naumann <info@vereinsring-wasserlos.de>
 * @version 1.0.7
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AVN_Database {

    // ── Abonnenten ───────────────────────────────────────────────────────────

    public static function get_subscribers( $args = [] ) {
        global $wpdb;
        $defaults = [ 'limit' => 100, 'offset' => 0, 'status' => null, 'group_id' => null, 'search' => null ];
        $args     = wp_parse_args( $args, $defaults );

        $where  = [];
        $params = [];

        $join = '';
        if ( ! empty( $args['group_id'] ) ) {
            $join     = " INNER JOIN {$wpdb->prefix}av_subscriber_groups sg ON sg.subscriber_id = s.id AND sg.group_id = %d";
            $params[] = (int) $args['group_id'];
        }

        if ( ! empty( $args['status'] ) ) {
            $where[]  = 's.status = %s';
            $params[] = $args['status'];
        }
        if ( ! empty( $args['search'] ) ) {
            $like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[]  = '(s.email LIKE %s OR s.first_name LIKE %s OR s.last_name LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = $where ? ' WHERE ' . implode( ' AND ', $where ) : '';
        $params[]  = (int) $args['limit'];
        $params[]  = (int) $args['offset'];

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.* FROM {$wpdb->prefix}av_subscribers s{$join}{$where_sql} ORDER BY s.created_at DESC LIMIT %d OFFSET %d",
                $params
            )
        );
    }

    public static function count_subscribers( $args = [] ) {
        global $wpdb;
        $where  = [];
        $params = [];

        if ( ! empty( $args['status'] ) ) {
            $where[]  = 'status = %s';
            $params[] = $args['status'];
        }
        $where_sql = $where ? ' WHERE ' . implode( ' AND ', $where ) : '';

        if ( $params ) {
            return (int) $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}av_subscribers{$where_sql}", $params )
            );
        }
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}av_subscribers" );
    }

    public static function get_subscriber( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}av_subscribers WHERE id = %d", (int) $id )
        );
    }

    public static function get_subscriber_by_email( $email ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}av_subscribers WHERE email = %s", sanitize_email( $email ) )
        );
    }

    public static function save_subscriber( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'av_subscribers';
        if ( ! empty( $data['id'] ) ) {
            $id = (int) $data['id'];
            unset( $data['id'] );
            $wpdb->update( $table, $data, [ 'id' => $id ], null, [ '%d' ] );
            return $id;
        }
        $wpdb->insert( $table, $data );
        return $wpdb->insert_id;
    }

    public static function delete_subscriber( $id ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'av_subscribers', [ 'id' => (int) $id ], [ '%d' ] );
        $wpdb->delete( $wpdb->prefix . 'av_subscriber_groups', [ 'subscriber_id' => (int) $id ], [ '%d' ] );
    }

    // ── Gruppen ──────────────────────────────────────────────────────────────

    public static function get_groups() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT g.*,
                    COUNT(CASE WHEN s.status = 'active' THEN 1 END) AS subscriber_count
             FROM {$wpdb->prefix}av_groups g
             LEFT JOIN {$wpdb->prefix}av_subscriber_groups sg ON sg.group_id = g.id
             LEFT JOIN {$wpdb->prefix}av_subscribers s ON s.id = sg.subscriber_id
             GROUP BY g.id
             ORDER BY g.name ASC"
        );
    }

    public static function get_group( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}av_groups WHERE id = %d", (int) $id )
        );
    }

    public static function save_group( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'av_groups';
        if ( ! empty( $data['id'] ) ) {
            $id = (int) $data['id'];
            unset( $data['id'] );
            $wpdb->update( $table, $data, [ 'id' => $id ], null, [ '%d' ] );
            return $id;
        }
        $wpdb->insert( $table, $data );
        return $wpdb->insert_id;
    }

    public static function delete_group( $id ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'av_groups', [ 'id' => (int) $id ], [ '%d' ] );
        $wpdb->delete( $wpdb->prefix . 'av_subscriber_groups', [ 'group_id' => (int) $id ], [ '%d' ] );
    }

    public static function get_subscriber_groups( $subscriber_id ) {
        global $wpdb;
        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT group_id FROM {$wpdb->prefix}av_subscriber_groups WHERE subscriber_id = %d",
                (int) $subscriber_id
            )
        );
    }

    public static function set_subscriber_groups( $subscriber_id, array $group_ids ) {
        global $wpdb;
        $table = $wpdb->prefix . 'av_subscriber_groups';
        $wpdb->delete( $table, [ 'subscriber_id' => (int) $subscriber_id ], [ '%d' ] );
        foreach ( $group_ids as $gid ) {
            $wpdb->replace( $table, [
                'subscriber_id' => (int) $subscriber_id,
                'group_id'      => (int) $gid,
            ] );
        }
    }

    public static function count_group_subscribers( $group_id ) {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}av_subscriber_groups WHERE group_id = %d",
                (int) $group_id
            )
        );
    }

    // ── Mail-Templates ──────────────────────────────────────────────────────

    public static function get_mail_templates( $form_id = null ) {
        global $wpdb;
        if ( $form_id !== null ) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}av_mail_templates WHERE form_id = %d OR form_id IS NULL ORDER BY created_at DESC",
                    (int) $form_id
                )
            );
        }
        return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}av_mail_templates ORDER BY created_at DESC" );
    }

    public static function get_mail_template( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}av_mail_templates WHERE id = %d", (int) $id )
        );
    }

    public static function save_mail_template( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'av_mail_templates';
        if ( ! empty( $data['id'] ) ) {
            $id = (int) $data['id'];
            unset( $data['id'] );
            $wpdb->update( $table, $data, [ 'id' => $id ], null, [ '%d' ] );
            return $id;
        }
        $wpdb->insert( $table, $data );
        return $wpdb->insert_id;
    }

    public static function delete_mail_template( $id ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'av_mail_templates', [ 'id' => (int) $id ], [ '%d' ] );
    }

    // ── Mail-Log ────────────────────────────────────────────────────────────

    public static function log_mail( $data ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'av_mail_log', $data );
        return $wpdb->insert_id;
    }

    public static function get_mail_log( $args = [] ) {
        global $wpdb;
        $defaults = [ 'limit' => 50, 'offset' => 0, 'status' => null ];
        $args     = wp_parse_args( $args, $defaults );

        $where  = [];
        $params = [];

        if ( ! empty( $args['status'] ) ) {
            $where[]  = 'status = %s';
            $params[] = $args['status'];
        }

        $where_sql = $where ? ' WHERE ' . implode( ' AND ', $where ) : '';
        $params[]  = (int) $args['limit'];
        $params[]  = (int) $args['offset'];

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}av_mail_log{$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $params
            )
        );
    }

    public static function update_mail_log_status( $id, $status, $error = '' ) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'av_mail_log',
            [
                'status'        => $status,
                'error_message' => $error,
                'sent_at'       => current_time( 'mysql' ),
            ],
            [ 'id' => (int) $id ],
            null,
            [ '%d' ]
        );
    }

    // ── Kampagnen ────────────────────────────────────────────────────────────

    public static function save_campaign( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'av_campaigns';
        if ( ! empty( $data['id'] ) ) {
            $id = (int) $data['id'];
            unset( $data['id'] );
            $wpdb->update( $table, $data, [ 'id' => $id ], null, [ '%d' ] );
            return $id;
        }
        $wpdb->insert( $table, $data );
        return $wpdb->insert_id;
    }

    public static function get_campaigns( $limit = 20 ) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.*,
                        t.name  AS template_name,
                        g.name  AS group_name
                 FROM {$wpdb->prefix}av_campaigns c
                 LEFT JOIN {$wpdb->prefix}av_mail_templates t ON t.id = c.template_id
                 LEFT JOIN {$wpdb->prefix}av_groups         g ON g.id = c.group_id
                 ORDER BY c.created_at DESC LIMIT %d",
                (int) $limit
            )
        );
    }

    /**
     * Kampagnen nach Status filtern.
     *
     * @param string|array $status  Ein Status-String oder Array von Status-Werten.
     * @param int          $limit   Maximale Anzahl Ergebnisse.
     * @return array
     */
    public static function get_campaigns_by_status( $status, $limit = 50 ) {
        global $wpdb;

        $statuses      = (array) $status;
        $placeholders  = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
        $args          = array_merge( $statuses, [ (int) $limit ] );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.*,
                        t.name  AS template_name,
                        g.name  AS group_name
                 FROM {$wpdb->prefix}av_campaigns c
                 LEFT JOIN {$wpdb->prefix}av_mail_templates t ON t.id = c.template_id
                 LEFT JOIN {$wpdb->prefix}av_groups         g ON g.id = c.group_id
                 WHERE c.status IN ( {$placeholders} )
                 ORDER BY c.created_at DESC LIMIT %d",
                ...$args
            )
        );
    }

    public static function get_campaign( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}av_campaigns WHERE id = %d", (int) $id )
        );
    }

    public static function get_all_campaigns( $limit = 200 ) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c.*,
                        t.name AS template_name,
                        g.name AS group_name
                 FROM {$wpdb->prefix}av_campaigns c
                 LEFT JOIN {$wpdb->prefix}av_mail_templates t ON t.id = c.template_id
                 LEFT JOIN {$wpdb->prefix}av_groups         g ON g.id = c.group_id
                 ORDER BY c.created_at DESC LIMIT %d",
                (int) $limit
            )
        );
    }

    public static function duplicate_campaign( $id ) {
        global $wpdb;
        $orig = self::get_campaign( (int) $id );
        if ( ! $orig ) { return null; }

        $data = [
            'template_id'  => $orig->template_id,
            'group_id'     => $orig->group_id,
            'subject'      => $orig->subject ? $orig->subject . ' (Kopie)' : '(Kopie)',
            'sender_name'  => $orig->sender_name,
            'sender_email' => $orig->sender_email,
            'content_html' => $orig->content_html,
            'content_json' => $orig->content_json,
            'status'       => 'draft',
            'created_at'   => current_time( 'mysql' ),
        ];
        $wpdb->insert( $wpdb->prefix . 'av_campaigns', $data );
        return $wpdb->insert_id;
    }

    /**
     * Prüft ob alle Mail-Log-Einträge einer Kampagne abgearbeitet sind
     * und gibt ['sent'=>int, 'failed'=>int, 'queued'=>int] zurück.
     */
    public static function get_campaign_mail_stats( int $campaign_id ): array {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT status, COUNT(*) AS cnt FROM {$wpdb->prefix}av_mail_log WHERE campaign_id = %d GROUP BY status",
                $campaign_id
            )
        );
        $stats = [ 'sent' => 0, 'failed' => 0, 'queued' => 0 ];
        foreach ( $rows as $r ) {
            if ( isset( $stats[ $r->status ] ) ) {
                $stats[ $r->status ] = (int) $r->cnt;
            }
        }
        return $stats;
    }

    public static function update_campaign_status( $id, $status, $sent_at = null ) {
        global $wpdb;
        $data = [ 'status' => $status ];
        if ( $sent_at ) {
            $data['sent_at'] = $sent_at;
        }
        $wpdb->update( $wpdb->prefix . 'av_campaigns', $data, [ 'id' => (int) $id ], null, [ '%d' ] );
    }

    public static function delete_campaign( $id ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'av_campaigns', [ 'id' => (int) $id ], [ '%d' ] );
    }

    /**
     * Anzahl der Kampagnen mit einem bestimmten Status.
     *
     * @param string $status
     * @return int
     */
    public static function count_campaigns_by_status( string $status ): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}av_campaigns WHERE status = %s",
                $status
            )
        );
    }

    public static function get_scheduled_campaigns() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}av_campaigns
             WHERE status = 'scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= NOW()"
        );
    }
}
