<?php
/*
Plugin Name: Fakturace
Plugin URI: http://jancejka.cz/plugin-fakturace-pro-wordpress/
Description: Vystavování faktur, platby přes GoPay, následná aktivace účtů a přiřazování uživatelských rolí.
Version: 1.2.15
Author: Jan Čejka
Author URI: http://jancejka.cz
Author Email: posta@jancejka.cz
License:

  Copyright 2011 Jan Čejka (posta@jancejka.cz)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2 (GPLv2),
  as published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
  
*/

include_once( 'dbStructUpdate.php' );
require_once( 'Faktura.php' ); 

require_once( 'installDependencies.php' );

class Fakturace {

    /*--------------------------------------------*
     * Constants
     *--------------------------------------------*/
    const name = 'Fakturace';
    const slug = 'fakturace';
    
    /**
     *
     * @var string URL adresa pro notifikace z brany GoPay
     */
    var $notify_url = '';
    
    /**
     *
     * @var Faktura datovy model faktury
     */
    var $fakturaModel;

    /**
     * Constructor
     */
    function __construct() {
        global $wpdb;
        
        $this->notify_url = home_url() . '/?akce_fakturace=gopay-notify';
        $this->fakturaModel = new Faktura($wpdb->prefix . "mw_faktury");

        //register an activation hook for the plugin
        register_activation_hook( __FILE__, array( &$this, 'install_fakturace' ) );

        //Hook up to the init action
        add_action( 'init', array( &$this, 'init_fakturace' ) );
        add_action( 'admin_init', array( &$this, 'admin_init' ) );
        add_action( 'admin_menu', array( &$this, 'add_menu') );

        add_action( 'tf_create_options', array( $this, 'createMyOptions' ) );

        add_filter('query_vars', array($this, 'plugin_add_trigger'));
        add_action('template_redirect', array($this, 'plugin_trigger_check'));

	    add_action('wp_footer', array($this, 'footerTrackingCode'));
    }

    /**
     * Menu item will allow us to load the page to display the table
     */
    public function add_menu()
    {
        // zarazeno pod polozku "fakturace" vytvorenou Titan frameworkem
        add_submenu_page( 'fakturace', 'Seznam vystavených faktur',   'Seznam faktur',   'manage_options', 'faktury-seznam', array($this, 'seznam_faktur') );
    }
    
    public function seznam_faktur() {
        global $wpdb;
        
        $stavy = array(
            'nová',
            'autorizována',
            'zaplacena',
            'čas vypršel',
            'zrušena',
            'refundována',
            'čekáme na platbu',
        );
        
        $query = "SELECT id, cislo, uzivatel_email, vystaveno, cena_s_dph, stav FROM {$wpdb->prefix}mw_faktury";
        
        wp_enqueue_style( 'faktury-seznam', plugins_url( 'css/faktury-seznam.css', __FILE__ ) );
        
        $getPdfUrl = home_url();
        
        $faktury = $wpdb->get_results($query);
        ?>
            <div class="wrap">
                <div id="icon-users" class="icon32"></div>
                <h2>Seznam vydaných faktur</h2>
                <table class="wp-list-table widefat fixed">
                    <thead><tr><th>číslo (VS)</th><th>email</th><th>vystavena</th><th>částka s DPH</th><th>stav</th><th>stáhnout</th></tr></thead>
                    <?php
                        $radek = 0;
                        foreach ($faktury as $faktura) {
                            $row_class = (($radek++ % 2) == 1 ? ' alternate' : '');
                            echo("<tr id=\"faktura-{$faktura->id}\" class=\"faktura-stav-{$faktura->stav}{$row_class}\">");
                            echo("<td>{$faktura->cislo}</td>");
                            echo("<td>{$faktura->uzivatel_email}</td>");
                            echo("<td>{$faktura->vystaveno}</td>");
                            echo("<td>{$faktura->cena_s_dph} Kč</td>");
                            echo("<td>{$stavy[$faktura->stav]}</td>");
                            echo("<td><a href=\"{$getPdfUrl}?akce_fakturace=getpdf&id={$faktura->id}&key={$this->fakturaModel->getKeyByID($faktura->id)}\" target=\"_blank\">PDF</a></td>");
                            echo("</tr>");
                        }
                    ?>
                </table>
            </div>
        <?php
    }
    
    /**
     * hook into WP's admin_init action hook
     */
    public function admin_init()
    {
       // Set up the settings for this plugin
       $this->init_settings();
       // Possibly do additional admin_init tasks
    } // END public static function activate

    function createMyOptions() {
        // Initialize Titan & options here
        $titan = TitanFramework::getInstance( 'mw-fakturace' );

        $panel = $titan->createAdminPanel( array(
            'name' => 'Fakturace',
//            'id' => 'fakturace',
//            'parent' => 'fakturace'
        ) );
        
        // =====
        
        $tab_info = $panel->createTab( array(
            'name' => 'Informace',
            'desc' => 'Informace o pluginu Fakturace'
        ) );
        
        // -----
        
        $tab_info->createOption( array(
            'name' => 'Co Fakturace umí?',
            'type' => 'heading',
        ) );

        $tab_info->createOption( array(
            'type' => 'note',
            'desc' => "<p><a style=\"float: right;\" href=\"http://jancejka.cz/plugin-fakturace-premium/\" target=\"_blank\"><button class=\"button button-primary\">Fakturace PREMIUM</button></a>Generovat a zasílat <b>faktury</b> emailem, umožnit <b>online platby</b> přes GoPay a následně <b>aktivovat uživatelské účty</b> a nastavit jim vybranou uživatelskou roli.</p>"
            . "<p>V základní verzi umí plugin obsluhovat jeden formulář s jedním produktem. Pokud potřebujete víc, <b>přečtěte si o rozšířené verzi <a href=\"http://jancejka.cz/plugin-fakturace-premium/\" target=\"_blank\">Fakturace PREMIUM</a></b>.<p>"
            . "<p>Do <b style=\"color: green; font-size: 120%;\">25.3.2015</b> možnost <b style=\"color: green;\">upgrade za poloviční cenu!</b></p>"
        ) );
            
        // -----
        
        $tab_info->createOption( array(
            'name' => 'Autor',
            'type' => 'heading',
        ) );

        $tab_info->createOption( array(
            'type' => 'note',
            'desc' => "<p><div style=\"float: left; margin-right: 2em\"><img src=\"" . plugins_url( 'assets/author.jpg', __FILE__ ) . "\" /></div>Jan Čejka</p><p><em>čarotvůrce - otec tří dětí, čaroděj, šaman, terapeut, fotograf, designer, malíř a webový architekt</em></p><p><a href=\"http://jancejka.cz\" target=\"_blank\">jancejka.cz</a></p><div style=\"clear: both;\"></div>"
        ) );
            
        // -----
        
        $tab_info->createOption( array(
            'name' => 'Jak vytvořit formulář?',
            'type' => 'heading',
        ) );

        $tab_info->createOption( array(
            'type' => 'note',
            'desc' => "<p>Do textu stránky vložte zkratku <strong>[<span></span>fakturace_formular]</strong>. A je to :-)</p>"
        ) );
            
        // -----

        $tab_info->createOption( array(
            'name' => 'Podpořte další vývoj pluginu',
            'type' => 'heading',
        ) );

        $tab_info->createOption( array(
            'type' => 'note',
            'desc' => "<p>" .
                        "<div style=\"float: right; margin-left: 2em;\"><script id='fb2orrt'>(function(i){var f,s=document.getElementById(i);f=document.createElement('iframe');f.src='//api.flattr.com/button/view/?uid=Tancici-Orel&url=http%3A%2F%2Fjancejka.cz%2Fplugin-fakturace-pro-wordpress%2F&title=WP+plugin+Fakturace';f.title='Flattr';f.height=62;f.width=55;f.style.borderWidth=0;s.parentNode.insertBefore(f,s);})('fb2orrt');</script></div>" .
						"Tato verze pluginu je poskytována zdarma bez nároku na odměnu.</p><p>Pokud Vám udělala radost, ušetřila peníze a chcete mě <strong>motivovat v jejím vylepšování</strong>, pošlete mi dárek dle svého uvážení na účet č. <span style=\"color: #008000;\"><strong>670100-2200018458/6210</strong></span> u mBank.</p><p>Pokud do poznámky připíšete své jméno, nebo název firmy, zveřejním ho i se zaslanou částkou v seznamu podporovatelů.<br />" .
						"Nebo přes službu <b>Flattr</b> tlačítkem vpravo." .
						"<div style=\"float: none; clear: both;\"></div>" .
						"</p>"
        ) );

        // =====
        
        $tab_gopay = $panel->createTab( array(
            'name' => 'GoPay',
            'desc' => 'Nastavení přístupových údajů do platební brány GoPay'
        ) );
        
        // -----
        
        $tab_gopay->createOption( array(
            'name' => 'Přístupové údaje k platební bráně',
            'type' => 'heading',
        ) );

        $tab_gopay->createOption( array(
            'name' => 'Gopay GOID',
            'id' => 'gopay-goid',
            'type' => 'text',
            'desc' => 'Identifikátor obchodníka v systému GoPay'
        ) );
        
        $tab_gopay->createOption( array(
            'name' => 'Gopay Secure Key',
            'id' => 'gopay-seckey',
            'type' => 'text',
            'desc' => 'Tajný klíč obchodníka v systému GoPay'
        ) );
        
        $tab_gopay->createOption( array(
            'name' => 'Testovací brána',
            'id' => 'gopay-test',
            'type' => 'checkbox',
            'desc' => 'Pracovat v testovacím (implementačním) režimu',
            'default' => true,
        ) );
        
        // -----
        
        $tab_gopay->createOption( array(
            'name' => 'Notifikační URL pro integraci platební brány GoPay',
            'type' => 'heading',
        ) );

        $tab_gopay->createOption( array(
            'type' => 'note',
            'desc' => "<a href=\"{$this->notify_url}\" target=\"blank\">{$this->notify_url}</a>"
        ) );

        $tab_gopay->createOption( array(
            'type' => 'save',
            'save' => 'Uložit',
            'reset' => 'Vrátit výchozí hodnoty'
        ) );

        // =====
            
        $tab_produkt = $panel->createTab( array(
            'name' => 'Produkt',
            'desc' => 'Nastavení akcí a produktu'
        ) );
        
        // -----

        $tab_produkt->createOption( array(
            'name' => 'Co prodáváte',
            'type' => 'heading',
        ) );

        $tab_produkt->createOption( array(
            'name' => 'Název produktu',
            'id' => 'fakt-produkt-nazev',
            'type' => 'text',
            'desc' => 'Název produktu, který se zobrazí na faktuře a platební bráně'
        ) );

        $tab_produkt->createOption( array(
            'name' => 'Cena produktu',
            'id' => 'fakt-produkt-cena',
            'type' => 'number',
            'desc' => 'Cena produktu včetně DPH',
            'min' => '1',
            'max' => '10000',
            'unit' => 'Kč'
        ) );

        $tab_produkt->createOption( array(
            'name' => 'Sazba DPH',
            'id' => 'fakt-produkt-dph',
            'type' => 'number',
            'desc' => 'Sazba DPH v procentech',
            'min' => '0',
            'max' => '100',
            'unit' => '%'
        ) );

        // -----

        $tab_produkt->createOption( array(
            'name' => 'Aktivace',
            'type' => 'heading',
        ) );

        $roles = $this->members_roles();
        
        $tab_produkt->createOption( array(
            'name' => 'Aktivovat roli',
            'id' => 'user-activate-role',
            'type' => 'select',
            'desc' => 'Jakou uživatelskou roli přiřadit po zaplacení',
            'options' => $roles,
            'default' => 'subscriber',
        ) );

        // -----

        $tab_produkt->createOption( array(
            'name' => 'Přesměrování',
            'type' => 'heading',
        ) );
        
        $tab_produkt->createOption( array(
            'name' => 'Děkovací stránka',
            'id' => 'page-success',
            'type' => 'select-pages',
            'desc' => 'Kam přesměrovat po úspěšné platbě (objednávce)'
        ) );

        $tab_produkt->createOption( array(
            'name' => 'Stránka po chybě',
            'id' => 'page-error',
            'type' => 'select-pages',
            'desc' => 'Kam přesměrovat po neúspěšné platbě (objednávce)'
        ) );

        // -----

        $tab_produkt->createOption( array(
            'name' => 'Šablony emailů',
            'type' => 'heading',
        ) );

        $tab_produkt->createOption( array(
            'name' => 'Odesilatel emailu',
            'id' => 'email-sender',
            'type' => 'text',
            'desc' => 'Emailová adresa odesilatele emailu (případně i se jménem ve formátu <code>jméno &lt;emailová@adresa&gt;</code>)'
        ) );
        
        $tab_produkt->createOption( array(
            'name' => 'Předmět emailu po vytvoření objednávky',
            'id' => 'email-subject-order',
            'type' => 'text',
            'desc' => 'Jak pojmenovat email, který se zašle po vytvoření objednávky, která ještě nebyla zaplacena?'
        ) );
        
        $tab_produkt->createOption( array(
            'name' => 'Po vytvoření objednávky',
            'id' => 'email-template-order',
            'type' => 'editor',
            'desc' => 'Text emailu po objednávce, která zatím nebyla zaplacena. Použijte následující značky pro automatické doplnění údajů:<br />'
            . '<pre>{email} - emailová adresa a současně přihlašovací jméno<br />'
            . '{vs} - variabilní symbol<br />'
            . '{faktura-url} - adresa pro stažení faktury v PDF'
            . '</pre>'
        ) );

        $tab_produkt->createOption( array(
            'name' => 'Předmět emailu po úspěšné platbě',
            'id' => 'email-subject-pay',
            'type' => 'text',
            'desc' => 'Jak pojmenovat email, který se zašle po provedení platby a vytvoření uživatelského účtu?'
        ) );
        
        $tab_produkt->createOption( array(
            'name' => 'Po úspěšné platbě',
            'id' => 'email-template-pay',
            'type' => 'editor',
            'desc' => 'Text emailu po úspěšné platbě. Použijte následující značky pro automatické doplnění údajů:<br />'
            . '<pre>{email} - emailová adresa a současně přihlašovací jméno<br />'
            . '{password} - přihlašovací heslo<br />'
            . '{vs} - variabilní symbol<br />'
            . '{login-url} - přihlašovací adresa<br />'
            . '{faktura-url} - adresa pro stažení faktury v PDF'
            . '</pre>'
        ) );

        $tab_produkt->createOption( array(
            'name' => 'Předmět emailu po neúspěšné platbě',
            'id' => 'email-subject-error',
            'type' => 'text',
            'desc' => 'Jak pojmenovat email, který se zašle po nerealizované platbě <i>(například zrušené)</i>?'
        ) );
        
        $tab_produkt->createOption( array(
            'name' => 'Po neúspěšné platbě',
            'id' => 'email-template-error',
            'type' => 'editor',
            'desc' => 'Text emailu po neúspěšné platbě. Použijte následující značky pro automatické doplnění údajů:<br />'
            . '<pre>{email} - emailová adresa a současně přihlašovací jméno<br />'
            . '{vs} - variabilní symbol<br />'
            . '{faktura-url} - adresa pro stažení faktury v PDF'
            . '</pre>'
        ) );

        $tab_produkt->createOption( array(
            'type' => 'save',
            'save' => 'Uložit',
            'reset' => 'Vrátit výchozí hodnoty'
        ) );

        // =====

        $tab_faktury = $panel->createTab( array(
            'name' => 'Faktury',
            'desc' => 'Nastavení číselných řad a fakturačních údajů'
        ) );

        // -----

        $tab_faktury->createOption( array(
            'name' => 'Číselné řady dokladů',
            'type' => 'heading',
        ) );

        $tab_faktury->createOption( array(
            'name' => 'Vydané faktury',
            'id' => 'fakt-rada-fv',
            'type' => 'text',
            'default' => 'RRRR1CCCCCCC',
            'desc' => 'Číselná řada pro faktury vydané (R = rok, C = číslo faktury, např. RRRR1CCCCCCC)'
        ) );

//        $tab_faktury->createOption( array(
//            'name' => 'Dobropisy',
//            'id' => 'fakt-rada-dob',
//            'type' => 'text',
//            'default' => 'RRRR2CCCCCCC',
//            'desc' => 'Číselná řada pro dobropisy (R = rok, C = číslo faktury, např. RRRR2CCCCCCC)'
//        ) );

        // -----

        $tab_faktury->createOption( array(
            'name' => 'Údaje o dodavateli',
            'type' => 'heading',
        ) );
        
        $tab_faktury->createOption( array(
            'name' => 'Název',
            'id' => 'fakt-dodavatel-nazev',
            'type' => 'text',
            'desc' => 'Jméno firmy nebo dodavatele'
        ) );

        $tab_faktury->createOption( array(
            'name' => 'Ulice a číslo',
            'id' => 'fakt-dodavatel-ulice',
            'type' => 'text',
            'desc' => 'Ulice a č.p.'
        ) );

        $tab_faktury->createOption( array(
            'name' => 'Město',
            'id' => 'fakt-dodavatel-mesto',
            'type' => 'text',
            'desc' => 'Město nebo obec'
        ) );

        $tab_faktury->createOption( array(
            'name' => 'PSČ',
            'id' => 'fakt-dodavatel-psc',
            'type' => 'text',
            'desc' => 'Poštovní směrovací číslo'
        ) );

        $tab_faktury->createOption( array(
            'name' => 'IČ',
            'id' => 'fakt-dodavatel-ic',
            'type' => 'text',
            'desc' => 'Identifikační číslo (IČO)'
        ) );

        $tab_faktury->createOption( array(
            'name' => 'DIČ',
            'id' => 'fakt-dodavatel-dic',
            'type' => 'text',
            'desc' => 'Daňové identifikační číslo'
        ) );

        $tab_faktury->createOption( array(
            'name' => 'Účet',
            'id' => 'fakt-dodavatel-ucet',
            'type' => 'text',
            'desc' => 'Číslo účtu i s lomítkem a číselným kódem banky'
        ) );

        // -----

        $tab_faktury->createOption( array(
            'name' => 'Další údaje',
            'type' => 'heading',
        ) );
        
        
        $tab_faktury->createOption( array(
            'name' => 'Splatnost faktury',
            'id' => 'fakt-splatnost',
            'type' => 'number',
            'desc' => 'Počet dnů splatnosti faktury',
            'default' => '14',
            'unit' => 'dnů',
//            'min' => '1',
//            'max' => '30',
        ) );

        $tab_faktury->createOption( array(
            'name' => 'Podpis',
            'id' => 'fakt-podpis',
            'type' => 'text',
            'desc' => 'Text (jméno) podpisu na faktuře'
        ) );
        

        $tab_faktury->createOption( array(
            'name' => 'Razítko a podpis',
            'id' => 'fakt-podpis-img',
            'type' => 'upload',
            'desc' => 'Obrázek s podpisem, případně i s razítkem'
        ) );

        $tab_faktury->createOption( array(
            'type' => 'save',
            'save' => 'Uložit',
            'reset' => 'Vrátit výchozí hodnoty'
        ) );

	    // =====

	    $tab_affilbox = $panel->createTab( array(
		    'name' => 'AffilBox',
		    'desc' => 'Nastavení propojení s <a href="http://www.affilbox.cz/?a_box=jmweajqf" target="_blank">AffilBoxem</a>'
	    ) );

	    // -----

	    $tab_affilbox->createOption( array(
		    'name' => 'Sledovací a konverzní kódy',
		    'type' => 'heading',
	    ) );


	    $tab_affilbox->createOption( array(
		    'name' => 'Sledovací kód',
		    'id' => 'affilbox-tracking',
		    'type' => 'textarea',
		    'desc' => 'Sem vložte <b>Tracking kód</b> z nastavení své kampaně',
		    'is_code' => true,
	    ) );

	    $tab_affilbox->createOption( array(
		    'name' => 'Konverzní kód',
		    'id' => 'affilbox-konverze',
		    'type' => 'textarea',
		    'desc' => 'Sem vložte <b>Konverzní kód</b> z nastavení své kampaně',
		    'is_code' => true,
	    ) );

	    $tab_affilbox->createOption( array(
		    'type' => 'save',
		    'save' => 'Uložit',
		    'reset' => 'Vrátit výchozí hodnoty'
	    ) );

    }
    
    /**
     * Initialize some custom settings
     */     
    public function init_settings()
    {
        // register the settings for this plugin
    } // END public function init_custom_settings()

    /**
     * Runs when the plugin is activated
     */  
    function install_fakturace() {
        // do not generate any output here
    }

    /**
     * Runs when the plugin is initialized
     */
    function init_fakturace() {
        $this->nette_form_post();
        
        // Load JavaScript and stylesheets
        $this->register_scripts_and_styles();

        // Register the shortcode [fakturace_formular]
        add_shortcode( 'fakturace_formular', array( &$this, 'render_shortcode' ) );

        if ( is_admin() ) {
                //this will run when in the WordPress admin
        } else {
                //this will run when on the frontend
        }

        /*
         * TODO: Define custom functionality for your plugin here
         *
         * For more information: 
         * http://codex.wordpress.org/Plugin_API#Hooks.2C_Actions_and_Filters
         */
        add_action( 'your_action_here', array( &$this, 'action_callback_method_name' ) );
        add_filter( 'your_filter_here', array( &$this, 'filter_callback_method_name' ) );    
    }

    function action_callback_method_name() {
        // TODO define your action method here
    }

    function filter_callback_method_name() {
        // TODO define your filter method here
    }

    function render_shortcode($atts) {
        // Extract the attributes
        extract(shortcode_atts(array(
            'attr1' => 'foo', //foo is a default value
            'attr2' => 'bar'
            ), $atts));
        // you can now access the attribute values using $attr1 and $attr2

        return $this->createNetteForm();
    }

    /**
     * Registers and enqueues stylesheets for the administration panel and the
     * public facing site.
     */
    private function register_scripts_and_styles() {
        if ( is_admin() ) {
            $this->load_file( self::slug . '-admin-script', '/js/admin.js', true );
            $this->load_file( self::slug . '-admin-style', '/css/admin.css' );
        } else {
            $this->load_file( self::slug . '-script', '/js/widget.js', true );
            $this->load_file( self::slug . '-nette-forms-script', 'js/netteForms.js', true );
            $this->load_file( self::slug . '-style', '/css/widget.css' );
        } // end if/else
    } // end register_scripts_and_styles

    /**
     * Helper function for registering and enqueueing scripts and styles.
     *
     * @name	The 	ID to register with WordPress
     * @file_path		The path to the actual file
     * @is_script		Optional argument for if the incoming file_path is a JavaScript source file.
     */
    private function load_file( $name, $file_path, $is_script = false ) {

        $url = plugins_url($file_path, __FILE__);
        $file = plugin_dir_path(__FILE__) . $file_path;

        if( file_exists( $file ) ) {
            if( $is_script ) {
                wp_register_script( $name, $url, array('jquery') ); //depends on jquery
                wp_enqueue_script( $name );
            } else {
                wp_register_style( $name, $url );
                wp_enqueue_style( $name );
            } // end if
        } // end if

    } // end load_file

    function createNetteForm() {
//        include_once 'includes/Nette/loader.php';
        include_once 'includes/vendor/autoload.php';

        $form = new Nette\Forms\Form;

//        $form->addGroup('Kontaktní údaje');
        $form->addText('firstName', 'Jméno', 25, 50)
                ->setAttribute('placeholder', 'zadejte své křestní jméno')
                ->addRule(Nette\Application\UI\Form::FILLED, 'Je nutné zadat křestní jméno.');
        $form->addText('lastName', 'Příjmení', 25, 50)
                ->setAttribute('placeholder', 'zadejte své příjmení')
                ->addRule(Nette\Application\UI\Form::FILLED, 'Je nutné zadat příjmení.');
        $form->addText('email', 'E-mail', 25, 250)
                ->setType('email')
                ->setAttribute('placeholder', 'zadejte emailovou adresu')
                ->addRule(Nette\Application\UI\Form::EMAIL, 'Zadaná hodnota neodpovídá formátu emailové adresy.')
                ->addRule(Nette\Application\UI\Form::FILLED, 'Je nutné zadat e-mailovou adresu.');
        $form->addText('phoneNumber', 'Telefonní číslo', 25, 50);

//        $form->addGroup('Firemní údaje');
        $form->addText('nazev', 'Název', 50, 50);
        $form->addText('city', 'Město', 25, 50);
        $form->addText('street', 'Ulice', 25, 50);
        $form->addText('postalCode', 'PSČ', 25, 50);
        $form->addText('IC', 'IČ', 20, 20);
        $form->addText('DIC', 'DIČ', 20, 20);
        
        $form->addSelect('countryCode', 'Země', array(
            'CZE' => 'Česká republika',
            'SVK' => 'Slovensko',
        ));

//        $form->addGroup(NULL);
        $form->addSubmit('pay', 'Zaplatit');

        return $form;
    }
    
    function nette_form_post() {
        $form = $this->createNetteForm();

        if ($form->isSuccess()) {
            $data = $form->getValues();
            
            $titan = TitanFramework::getInstance( 'mw-fakturace' );

            // ulozit data atd. 
            $faktura = $this->fakturaModel->vytvorFakturu(
                    array(
                        array(
                            'nazev'         => $titan->getOption( 'fakt-produkt-nazev' ),
                            'cena_s_dph'    => $titan->getOption( 'fakt-produkt-cena' ),
                            'sazba_dph'     => $titan->getOption( 'fakt-produkt-dph' ),
                            'pocet'         => 1
                        )
                    ),
                    (array)$data,
                    $data->email
                );

            $successURL = home_url() . '/?akce_fakturace=gopay-success';
            $failedURL  = home_url() . '/?akce_fakturace=gopay-error';

            require_once 'PlatbaGoPay.php';
            
            $vysledek = PlatbaGoPay::createPayment(
                    $titan->getOption( 'gopay-goid' ), 
                    $titan->getOption( 'gopay-seckey' ),
                    $faktura->cislo,
                    $faktura->cena_s_dph, 'CZK',
                    $titan->getOption( 'fakt-produkt-nazev' ),
                    (array)$form->values,
                    $successURL, $failedURL,
                    $titan->getOption( 'gopay-test' ),
                    $this->fakturaModel,
                    $faktura->id
                );
                
//            wp_redirect('/');
//            exit;
        }
        
    }

    function members_roles() {
            global $wp_roles;
  
            if ( !empty( $wp_roles->role_names ) )
                    return $wp_roles->role_names;

            return false;
    }

    function plugin_add_trigger($vars) {
        $vars[] = 'akce_fakturace';
        return $vars;
    }
    
    function plugin_trigger_check() {
        
        switch ( get_query_var('akce_fakturace') ) {
            
            case 'gopay-notify':
                require_once 'PlatbaGoPay.php';

				$params = $this->getGoPayParams();

                PlatbaGoPay::processNotify(
                        $params['paymentSessionId'], $params['targetGoId'],
                        $params['orderNumber'], $params['parentPaymentSessionId'], $params['encryptedSignature'],
                        $params['p1'], $params['p2'], $params['p3'], $params['p4'],
                        $this->fakturaModel
                    );

                header("HTTP/1.1 200 Payment is OK");
                exit(0);

                break;

            case 'gopay-success':
                require_once 'PlatbaGoPay.php';

	            $params = $this->getGoPayParams();

	            PlatbaGoPay::processNotify(
		            $params['paymentSessionId'], $params['targetGoId'],
		            $params['orderNumber'], $params['parentPaymentSessionId'], $params['encryptedSignature'],
		            $params['p1'], $params['p2'], $params['p3'], $params['p4'],
		            $this->fakturaModel
	            );

	            $faktura = $this->fakturaModel->getByPaymentSessionID( $params['paymentSessionId'] );
                $titan = TitanFramework::getInstance( 'mw-fakturace' );
                $konverzniKod = $titan->getOption( 'affilbox-konverze' );

				if( $faktura && !empty($konverzniKod) ) {
					$params = '?akce_fakturace=konverze' .
					          "&email={$faktura->uzivatel_email}" .
					          "&cena={$faktura->cena_s_dph}";
				} else {
					$params = '';
				}

                $titan = TitanFramework::getInstance( 'mw-fakturace' );
                $successURL = get_permalink( $titan->getOption( 'page-success' ) ) . $params;

                header("Location: $successURL");
                exit(0);

                break;

            case 'gopay-error':
                require_once 'PlatbaGoPay.php';

	            $params = $this->getGoPayParams();

	            PlatbaGoPay::processNotify(
		            $params['paymentSessionId'], $params['targetGoId'],
		            $params['orderNumber'], $params['parentPaymentSessionId'], $params['encryptedSignature'],
		            $params['p1'], $params['p2'], $params['p3'], $params['p4'],
		            $this->fakturaModel
	            );

                $titan = TitanFramework::getInstance( 'mw-fakturace' );
                $errorURL = get_permalink( $titan->getOption( 'page-error' ) );

                header("Location: $errorURL");
                exit(0);

                break;

            case 'getpdf':
                $this->fakturaModel->generatePDF(
                    filter_input(INPUT_GET, 'id'), 
                    filter_input(INPUT_GET, 'key')
                    );

                break;

	        case 'konverze':
		        add_action('wp_footer', array($this, 'footerConversionCode'));

		        break;

            default:
                break;
        }
        
    }

	public function footerTrackingCode() {
		$titan = TitanFramework::getInstance( 'mw-fakturace' );
		$code = $titan->getOption( 'affilbox-tracking' );

		echo $code;
	}

    public function footerConversionCode() {
		$titan = TitanFramework::getInstance( 'mw-fakturace' );
		$code = $titan->getOption( 'affilbox-konverze' );

        if( !empty($code) ) {
            $email = filter_input(INPUT_GET, 'email');
            $cena = filter_input(INPUT_GET, 'cena');

            if ( !empty($email) ){
                $code = str_replace("ID_TRANSAKCE", $email, $code);
            }

            if ( !empty($cena) ){
                $code = str_replace("CENA", $cena, $code);
            }

            echo $code;
        } else {
            echo '';
        }
	}

	private function getGoPayParams() {
		$paramNames = array(
			array('paymentSessionId','paymentSessionId'),
			array('parentPaymentSessionId','parentPaymentSessionId'),
			array('targetGoId','targetGoId'),
			array('orderNumber','orderNumber'),
			array('encryptedSignature','encryptedSignature'),
			array('p1'),
			array('p2'),
			array('p3'),
			array('p4')
		);

		$paramValues = array();

		foreach ($paramNames as $paramName) {
			$value = filter_input(INPUT_GET, $paramName[0]);
			if( !$value && isset($paramName[1]) ) {
				$value = filter_input(INPUT_GET, $paramName[1]);
			}
			$paramValues[$paramName[0]] = $value;
		}

		return $paramValues;
	}

} // end class

new Fakturace();

function fakturace_uninstall () {
    global $wpdb;

    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mw_faktury" );

    delete_option( 'mw-fakturace_options' );
    delete_option( 'mw_fakturace_db_version' );
}

register_uninstall_hook( __FILE__, 'fakturace_uninstall' );

?>