<?php
/**
 * XLSX-Importer für Abonnenten (MailPoet-kompatibel)
 *
 * Keine externen Abhängigkeiten – nutzt ZipArchive + SimpleXML (PHP built-in).
 *
 * Unterstützte MailPoet-Spalten (Groß-/Kleinschreibung egal):
 *   Email | First name | Last name | Status | List / Segment / Gruppe
 *
 * @package VRW_Newsletter
 * @author  Kai Naumann <info@vereinsring-wasserlos.de>
 * @version 1.0.7
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AVN_XLSX_Importer {

    /**
     * Generische Sheet-Namen, die NICHT als Gruppe angelegt werden.
     */
    private static $generic_sheet_names = [
        'sheet1', 'sheet 1', 'sheet2', 'sheet 2',
        'tabelle1', 'tabelle 1', 'tabelle2', 'tabelle 2',
        'blatt1', 'blatt 1', 'feuil1', 'hoja1',
    ];

    /**
     * Status-Mapping: MailPoet-Werte → eigene DB-Werte
     */
    private static $status_map = [
        'subscribed'   => 'active',
        'active'       => 'active',
        'aktiv'        => 'active',
        'unsubscribed' => 'unsubscribed',
        'abgemeldet'   => 'unsubscribed',
        'inactive'     => 'unsubscribed',
        'inaktiv'      => 'unsubscribed',
        'bounced'      => 'unsubscribed',
        'unconfirmed'  => 'pending',
        'pending'      => 'pending',
        'ausstehend'   => 'pending',
    ];

    // ── XLSX-Parser ──────────────────────────────────────────────────────────

    /**
     * Liest alle Tabellenblätter einer XLSX-Datei.
     * Jedes Sheet wird als [ 'name' => string, 'rows' => array[][] ] zurückgegeben.
     *
     * @param  string $file  Absoluter Pfad zur XLSX-Datei
     * @return array|WP_Error
     */
    public static function read_xlsx_sheets( string $file ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new WP_Error( 'no_zip', 'ZipArchive ist auf diesem Server nicht verfügbar.' );
        }

        $zip = new ZipArchive();
        if ( $zip->open( $file ) !== true ) {
            return new WP_Error( 'zip_open', 'XLSX-Datei konnte nicht geöffnet werden.' );
        }

        // ── Shared Strings ────────────────────────────────────────────────
        $strings = [];
        $sst_xml = $zip->getFromName( 'xl/sharedStrings.xml' );
        if ( $sst_xml ) {
            $sst = simplexml_load_string( $sst_xml );
            if ( $sst ) {
                foreach ( $sst->si as $si ) {
                    if ( count( $si->r ) === 0 ) {
                        $strings[] = (string) ( $si->t ?? '' );
                    } else {
                        $val = '';
                        foreach ( $si->r as $r ) {
                            $val .= (string) ( $r->t ?? '' );
                        }
                        $strings[] = $val;
                    }
                }
            }
        }

        // ── Workbook: Sheet-Namen + rIds ──────────────────────────────────
        $sheet_info = []; // [ rId => sheet_name ]
        $wb_xml     = $zip->getFromName( 'xl/workbook.xml' );
        if ( $wb_xml ) {
            $wb = simplexml_load_string( $wb_xml );
            if ( $wb ) {
                $wb->registerXPathNamespace( 'r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' );
                foreach ( $wb->sheets->sheet ?? [] as $s ) {
                    $attrs = $s->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' );
                    $rid   = (string) ( $attrs['id'] ?? '' );
                    $name  = (string) ( $s['name'] ?? 'Sheet' );
                    if ( $rid ) {
                        $sheet_info[ $rid ] = $name;
                    }
                }
            }
        }

        // ── Workbook-Rels: rId → Dateipfad ───────────────────────────────
        $rid_to_path = []; // [ rId => 'xl/worksheets/sheetN.xml' ]
        $rels_xml    = $zip->getFromName( 'xl/_rels/workbook.xml.rels' );
        if ( $rels_xml ) {
            $rels = simplexml_load_string( $rels_xml );
            if ( $rels ) {
                foreach ( $rels->Relationship as $rel ) {
                    $type   = (string) ( $rel['Type'] ?? '' );
                    $target = (string) ( $rel['Target'] ?? '' );
                    $rid    = (string) ( $rel['Id'] ?? '' );
                    if ( str_contains( $type, 'worksheet' ) ) {
                        $path = str_starts_with( $target, 'xl/' ) ? $target : 'xl/' . $target;
                        $rid_to_path[ $rid ] = $path;
                    }
                }
            }
        }

        // Fallback: kein Workbook-XML → sheet1.xml direkt
        if ( empty( $sheet_info ) ) {
            $sheet_info['rId1']  = 'Sheet1';
            $rid_to_path['rId1'] = 'xl/worksheets/sheet1.xml';
        }

        // ── Alle Sheets lesen ─────────────────────────────────────────────
        $result = [];
        foreach ( $sheet_info as $rid => $sheet_name ) {
            $path      = $rid_to_path[ $rid ] ?? '';
            if ( ! $path ) { continue; }
            $sheet_xml = $zip->getFromName( $path );
            if ( ! $sheet_xml ) { continue; }
            $rows = self::parse_sheet( $sheet_xml, $strings );
            if ( ! empty( $rows ) ) {
                $result[] = [ 'name' => $sheet_name, 'rows' => $rows ];
            }
        }

        $zip->close();

        if ( empty( $result ) ) {
            return new WP_Error( 'no_sheet', 'Keine Tabellenblätter mit Daten gefunden.' );
        }

        return $result;
    }

    /**
     * Parst Sheet-XML und gibt Zeilen zurück.
     */
    private static function parse_sheet( string $sheet_xml, array $strings ): array {
        $sheet = simplexml_load_string( $sheet_xml );
        if ( ! $sheet || ! isset( $sheet->sheetData ) ) {
            return [];
        }

        $rows = [];
        foreach ( $sheet->sheetData->row as $row_el ) {
            $row_data = [];
            $last_col = 0;

            foreach ( $row_el->c as $cell ) {
                $ref = (string) $cell['r'];
                $col = self::col_index( $ref );

                while ( $last_col < $col ) {
                    $row_data[] = '';
                    $last_col++;
                }

                $type  = (string) ( $cell['t'] ?? '' );
                $value = (string) ( $cell->v ?? '' );

                if ( $type === 's' ) {
                    $row_data[] = $strings[ (int) $value ] ?? '';
                } elseif ( $type === 'inlineStr' ) {
                    $row_data[] = (string) ( $cell->is->t ?? '' );
                } else {
                    $row_data[] = $value;
                }

                $last_col = $col + 1;
            }

            $rows[] = $row_data;
        }

        return $rows;
    }

    /**
     * Wandelt Zelladresse (z.B. "AB12") in numerischen 0-basierten Spaltenindex um.
     */
    private static function col_index( string $ref ): int {
        preg_match( '/^([A-Z]+)/i', strtoupper( $ref ), $m );
        $letters = $m[1] ?? 'A';
        $idx     = 0;
        foreach ( str_split( $letters ) as $c ) {
            $idx = $idx * 26 + ( ord( $c ) - 64 );
        }
        return $idx - 1;
    }

    // ── Import-Logik ─────────────────────────────────────────────────────────

    /**
     * Importiert Abonnenten aus einer MailPoet-XLSX-Exportdatei.
     *
     * Jedes Tabellenblatt wird als Gruppe behandelt (außer generische Namen
     * wie "Sheet1"). Taucht dieselbe E-Mail auf mehreren Blättern auf,
     * werden die Gruppen zusammengeführt.
     *
     * @param  string $file     Absoluter Pfad zur XLSX-Datei
     * @param  array  $options  {
     *     @type string   $duplicate_handling  'skip'|'update'   (default: 'skip')
     *     @type string   $default_status      'active'|'pending' (default: 'active')
     *     @type int|null $default_group_id    Zusätzliche Gruppe für alle Importierten (default: null)
     * }
     * @return array { imported, updated, skipped, groups_created, sheets, errors[] }
     */
    public static function import( string $file, array $options = [] ): array {
        $opts = wp_parse_args( $options, [
            'duplicate_handling' => 'skip',
            'default_status'     => 'active',
            'default_group_id'   => null,
        ] );

        $sheets = self::read_xlsx_sheets( $file );

        if ( is_wp_error( $sheets ) ) {
            return [ 'error' => $sheets->get_error_message() ];
        }

        $result = [
            'imported'       => 0,
            'updated'        => 0,
            'skipped'        => 0,
            'groups_created' => 0,
            'sheets'         => count( $sheets ),
            'errors'         => [],
        ];

        // Gruppen-Cache (lowercase-Name → ID)
        $group_cache = [];
        foreach ( AVN_Database::get_groups() as $g ) {
            $group_cache[ strtolower( $g->name ) ] = (int) $g->id;
        }

        // E-Mails, die in diesem Import bereits verarbeitet wurden (Sheet-übergreifend)
        // lowercase-email → subscriber_id
        $processed = [];

        foreach ( $sheets as $sheet ) {
            $sheet_name = trim( $sheet['name'] );
            $rows       = $sheet['rows'];

            if ( empty( $rows ) ) {
                continue;
            }

            // Header-Zeile normalisieren
            $headers = array_map( fn( $h ) => strtolower( trim( (string) $h ) ), $rows[0] );
            $col_map = self::map_columns( $headers );

            if ( $col_map['email'] === false ) {
                $result['errors'][] = 'Blatt „' . esc_html( $sheet_name ) . '": Keine E-Mail-Spalte – übersprungen.';
                continue;
            }

            // Sheet-Name → Gruppe anlegen/finden (außer generische Namen)
            $sheet_group_id = null;
            $sheet_key      = strtolower( $sheet_name );
            if ( ! in_array( $sheet_key, self::$generic_sheet_names, true ) ) {
                if ( ! isset( $group_cache[ $sheet_key ] ) ) {
                    $new_id = AVN_Database::save_group( [
                        'name'       => $sheet_name,
                        'created_at' => current_time( 'mysql' ),
                    ] );
                    $group_cache[ $sheet_key ] = (int) $new_id;
                    $result['groups_created']++;
                }
                $sheet_group_id = $group_cache[ $sheet_key ];
            }

            // Datenzeilen verarbeiten (ab Zeile 2)
            $total_rows = count( $rows );
            for ( $i = 1; $i < $total_rows; $i++ ) {
                $row = $rows[ $i ];

                if ( empty( array_filter( $row, fn( $v ) => trim( (string) $v ) !== '' ) ) ) {
                    continue;
                }

                // E-Mail validieren
                $raw_email = trim( (string) ( $row[ $col_map['email'] ] ?? '' ) );
                $email     = sanitize_email( $raw_email );
                if ( ! is_email( $email ) ) {
                    if ( $raw_email !== '' ) {
                        $result['errors'][] = 'Blatt „' . esc_html( $sheet_name ) . '" Zeile ' . ( $i + 1 ) . ': Ungültige E-Mail „' . esc_html( $raw_email ) . '"';
                    }
                    $result['skipped']++;
                    continue;
                }

                // Status ermitteln
                $raw_status = $col_map['status'] !== false
                    ? strtolower( trim( (string) ( $row[ $col_map['status'] ] ?? '' ) ) )
                    : '';
                $status = self::$status_map[ $raw_status ] ?? $opts['default_status'];

                // Gruppen-IDs für diesen Abonnenten
                $group_ids = [];
                if ( $opts['default_group_id'] ) {
                    $group_ids[] = (int) $opts['default_group_id'];
                }
                if ( $sheet_group_id ) {
                    $group_ids[] = $sheet_group_id;
                }
                $group_ids = array_unique( $group_ids );

                $email_key = strtolower( $email );

                // ── In diesem Import bereits verarbeitet (anderes Sheet) ───
                if ( isset( $processed[ $email_key ] ) ) {
                    // Gruppen immer mergen, auch wenn duplicate_handling = 'skip'
                    if ( $group_ids ) {
                        $sub_id          = $processed[ $email_key ];
                        $existing_groups = array_map( 'intval', AVN_Database::get_subscriber_groups( $sub_id ) );
                        AVN_Database::set_subscriber_groups(
                            $sub_id,
                            array_unique( array_merge( $existing_groups, $group_ids ) )
                        );
                    }
                    continue; // Zähler nicht nochmal erhöhen
                }

                // ── In der DB vorhanden? ───────────────────────────────────
                $existing = AVN_Database::get_subscriber_by_email( $email );

                if ( $existing ) {
                    $processed[ $email_key ] = (int) $existing->id;

                    if ( $opts['duplicate_handling'] === 'update' ) {
                        $data = [ 'id' => (int) $existing->id, 'status' => $status ];
                        if ( $col_map['first_name'] !== false && trim( (string) ( $row[ $col_map['first_name'] ] ?? '' ) ) !== '' ) {
                            $data['first_name'] = sanitize_text_field( $row[ $col_map['first_name'] ] );
                        }
                        if ( $col_map['last_name'] !== false && trim( (string) ( $row[ $col_map['last_name'] ] ?? '' ) ) !== '' ) {
                            $data['last_name'] = sanitize_text_field( $row[ $col_map['last_name'] ] );
                        }
                        AVN_Database::save_subscriber( $data );
                        $result['updated']++;
                    } else {
                        $result['skipped']++;
                    }

                    // Gruppen immer mergen (auch bei 'skip' – neue Gruppe aus Sheet)
                    if ( $group_ids ) {
                        $existing_groups = array_map( 'intval', AVN_Database::get_subscriber_groups( $existing->id ) );
                        AVN_Database::set_subscriber_groups(
                            $existing->id,
                            array_unique( array_merge( $existing_groups, $group_ids ) )
                        );
                    }
                    continue;
                }

                // ── Neuen Abonnenten anlegen ───────────────────────────────
                $sub_id = AVN_Database::save_subscriber( [
                    'email'      => $email,
                    'first_name' => sanitize_text_field( $col_map['first_name'] !== false ? ( (string) ( $row[ $col_map['first_name'] ] ?? '' ) ) : '' ),
                    'last_name'  => sanitize_text_field( $col_map['last_name']  !== false ? ( (string) ( $row[ $col_map['last_name']  ] ?? '' ) ) : '' ),
                    'status'     => $status,
                    'token'      => bin2hex( random_bytes( 32 ) ),
                    'created_at' => current_time( 'mysql' ),
                ] );

                if ( $group_ids ) {
                    AVN_Database::set_subscriber_groups( $sub_id, $group_ids );
                }

                $processed[ $email_key ] = (int) $sub_id;
                $result['imported']++;
            }
        }

        return $result;
    }

    // ── Spalten-Mapping ──────────────────────────────────────────────────────

    /**
     * Mappt Header-Array → Spaltenindizes.
     * Gibt false zurück, wenn eine Spalte nicht erkannt wurde.
     *
     * @param  array $headers  Normalisiertes (lowercase) Header-Array
     * @return array
     */
    private static function map_columns( array $headers ): array {
        $map = [
            'email'      => false,
            'first_name' => false,
            'last_name'  => false,
            'status'     => false,
        ];

        foreach ( $headers as $idx => $h ) {
            if ( $map['email'] === false
                && ( str_contains( $h, 'email' ) || str_contains( $h, 'e-mail' ) || $h === 'emailadresse' )
            ) {
                $map['email'] = $idx;
            } elseif ( $map['first_name'] === false
                && ( str_contains( $h, 'first' ) || str_contains( $h, 'vorname' ) )
            ) {
                $map['first_name'] = $idx;
            } elseif ( $map['last_name'] === false
                && ( str_contains( $h, 'last' ) || str_contains( $h, 'nachname' ) || str_contains( $h, 'surname' ) )
            ) {
                $map['last_name'] = $idx;
            } elseif ( $map['status'] === false && $h === 'status' ) {
                $map['status'] = $idx;
            }
        }

        return $map;
    }
}
