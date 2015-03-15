<?php

/*
 * All right reserved to: Jan Cejka <posta@jancejka.cz>, http://jancejka.cz
 */

global $wpdb, $mw_fakturace_db_version, $mw_tablename_faktury;
$mw_fakturace_db_version = '1.2';
$mw_tablename_faktury = $wpdb->prefix . "mw_faktury"; 

function mw_fakt_update_db_table_structure() {
    global $wpdb, $mw_tablename_faktury, $mw_fakturace_db_version;

    $installed_ver = get_option("mw_fakturace_db_version");

    if ($installed_ver != $mw_fakturace_db_version) {
        $charset_collate = $wpdb->get_charset_collate();

        $sql_fakt = "CREATE TABLE {$mw_tablename_faktury} (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                vystaveno datetime NOT NULL,
                splatnost_dnu int(11) NOT NULL,
                uzivatel_id int(11) DEFAULT NULL,
                uzivatel_email varchar(250) DEFAULT NULL,
                cislo varchar(50) COLLATE utf8_czech_ci NOT NULL,
                odesilatel text COLLATE utf8_czech_ci NOT NULL,
                odberatel text COLLATE utf8_czech_ci NOT NULL,
                zaplaceno decimal(10,0) NOT NULL DEFAULT '0',
                vazba_id int(11) NOT NULL,
                typ smallint(6) NOT NULL DEFAULT '1',
                polozky text COLLATE utf8_czech_ci NOT NULL,
                cena_s_dph decimal(10,0) NOT NULL,
                id_rada varchar(50) COLLATE utf8_czech_ci NOT NULL,
                id_rok int(11) DEFAULT NULL,
                id_cislo int(11) DEFAULT NULL,
                stav tinyint(4) NOT NULL DEFAULT '0',
                payment_sess_id varchar(20) COLLATE utf8_czech_ci NOT NULL,
                UNIQUE KEY id (id)
               ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        dbDelta($sql_fakt);
        
        $wpdb->query("DROP TRIGGER IF EXISTS {$mw_tablename_faktury}_faktura_id");
        $wpdb->query("CREATE TRIGGER {$mw_tablename_faktury}_faktura_id BEFORE INSERT ON {$mw_tablename_faktury}
                 FOR EACH ROW begin
                    declare v_id int unsigned default 0;

                    select coalesce(max(id_cislo),0)
                        into v_id
                        from {$mw_tablename_faktury}
                        where typ = new.typ
                                and id_rok = new.id_rok;

                    set new.id_cislo = v_id + 1;
                end;");

        update_option("mw_fakturace_db_version", $mw_fakturace_db_version);
    }
}

function mw_fakt_update_db_check() {
    global $mw_fakturace_db_version;
    if (get_site_option('mw_fakturace_db_version') != $mw_fakturace_db_version) {
        mw_fakt_update_db_table_structure();
    }
}

register_activation_hook( __FILE__, 'mw_fakt_update_db_table_structure' );
add_action( 'plugins_loaded', 'mw_fakt_update_db_check' );
