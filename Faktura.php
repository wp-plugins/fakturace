<?php

/*
 * All right reserved to: Jan Cejka <posta@jancejka.cz>, http://jancejka.cz
 */

/**
 * Description of Faktura
 *
 * @author Merlin
 */

use Nette\Framework;
use Nette\Templating\FileTemplate;

class Faktura {

    const   VYDANA = 1,
            ZALOHOVA = 2,
            DOBROPIS = 3;
    
    const   STAV_NOVA = 0,
            STAV_AUTORIZOVANA = 1,
            STAV_ZAPLACENA = 2,
            STAV_TIMEOUT = 3,
            STAV_ZRUSENA = 4,
            STAV_REFUNDED = 5,
            STAV_CEKAME = 6;
    
    /**
     *
     * @var string nazev tabulky s fakturami v databazi
     */
    var $tableName;

    /**
     * 
     * @param string $tableName nazev tabulky v databazi
     */
    public function __construct($tableName) {
        $this->tableName = $tableName;
    }
    
    public function getByID($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->tableName} WHERE id=%d",
                        $id));
    }

    public function getByPaymentSessionID($payment_sess_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->tableName} WHERE payment_sess_id=%s",
                        $payment_sess_id));
    }

    public function getKeyByID($id) {
        $faktura = $this->getByID($id);
        
        return sha1($faktura->id.$faktura->cislo.$faktura->vystaveno);
    }

    private function getInvoiceNumber($format, $poradi, $typ = 1) {
        $format = str_replace('RRRR', 'Y', $format);
        $format = str_replace('RR', 'y', $format);

        $cislo = date($format);

        if (preg_match('/C+/', $cislo, $nalezy)) {
            if (isset($nalezy[0])) {
                $maska = '%0' . strlen($nalezy[0]) . 'u';
                $cislo = str_replace($nalezy[0], sprintf($maska, $poradi), $cislo);
            }
        }

        if (preg_match('/T+/', $cislo, $nalezy)) {
            if (isset($nalezy[0])) {
                $maska = '%0' . strlen($nalezy[0]) . 'u';
                $cislo = str_replace($nalezy[0], sprintf($maska, $typ), $cislo);
            }
        }

        return $cislo;
    }

    public function vytvorFakturu($polozky, $odberatel, $email, $typ = self::VYDANA) {
        global $wpdb;
        
        $titan = TitanFramework::getInstance( 'mw-fakturace' );
        
        $odesilatel = array(
            'nazev'         => $titan->getOption( 'fakt-dodavatel-nazev' ),
            'street'        => $titan->getOption( 'fakt-dodavatel-ulice' ),
            'city'          => $titan->getOption( 'fakt-dodavatel-mesto' ),
            'postalCode'    => $titan->getOption( 'fakt-dodavatel-psc' ),
            'IC'            => $titan->getOption( 'fakt-dodavatel-ic' ),
            'DIC'           => $titan->getOption( 'fakt-dodavatel-dic' ),
            'ucet'          => $titan->getOption( 'fakt-dodavatel-ucet' ),
        );

        $cena_s_dph = 0;
        foreach ($polozky as $polozka) {
            $cena_s_dph += $polozka['cena_s_dph'];
        }

        switch ($typ) {
            case self::VYDANA:
                $rada = $titan->getOption( 'fakt-rada-fv' );
                break;

            case self::DOBROPIS:
                $rada = $titan->getOption( 'fakt-rada-dob' );
                break;

            default:
                $rada = 'C';
                break;
        }
        
        $rok = strpos($rada, 'R') !== FALSE ?
                date('Y') : null;
        
        $wpdb->insert( 
            $this->tableName, 
            array( 
                'vystaveno' => date('Y-m-d H:i:s'),
                'uzivatel_email' => $email,
                'splatnost_dnu' => $titan->getOption( 'fakt-splatnost' ),
                'odesilatel' => serialize($odesilatel),
                'odberatel' => serialize($odberatel),
                'typ' => $typ,
                'polozky' => serialize($polozky),
                'cena_s_dph' => $cena_s_dph,
                'id_rada' => $rada,
                'id_rok' => $rok,
            ), 
            array( 
                '%s', 
                '%s', 
                '%d', 
                '%s', 
                '%s', 
                '%d', 
                '%s', 
                '%d', 
                '%s', 
                '%d' 
            ) 
        );
        
        $fakturaId = $wpdb->insert_id;
        $faktura = $this->getByID($fakturaId);

        $wpdb->update( 
            $this->tableName, 
            array( 
                'cislo' => $this->getInvoiceNumber($rada, $faktura->id_cislo, $typ)
            ), 
            array( 'id' => $fakturaId ), 
            array( 
                '%s'
            ), 
            array( '%d' ) 
        );
        
        return $this->getByID($fakturaId);
    }

    private function textNumberFormat($pocet, $text1, $text2, $text3) {
        if ($pocet == 0) {
            return "$pocet $text3";
        } elseif ($pocet == 1) {
            return "$pocet $text1";
        } elseif ($pocet < 5) {
            return "$pocet $text2";
        } else {
            return "$pocet $text3";
        }
    }

    public function setPaymentSessID($id, $payment_sess_id) {
        global $wpdb;
        
        $wpdb->update( 
            $this->tableName, 
            array( 
                'payment_sess_id' => $payment_sess_id
            ), 
            array( 'id' => $id ), 
            array( 
                '%s'
            ), 
            array( '%d' ) 
        );
        
        return $this->getByID($id);
    }

    public function setState($id, $state) {
        global $wpdb;
        
        $wpdb->update( 
            $this->tableName, 
            array( 
                'stav' => $state
            ), 
            array( 'id' => $id ), 
            array( 
                '%d'
            ), 
            array( '%d' ) 
        );
        
        return $this->getByID($id);
    }

    public function processPayment() {
        
    }

    public function cancelPayment() {
        
    }

    public function timeoutPayment() {
        
    }

    public function refundePayment() {
        
    }

    public function autorizePayment() {
        
    }

    public function generatePDF($faktura_id, $klic) {

        if ($this->getKeyByID($faktura_id) == $klic) {
            $faktura = $this->getByID($faktura_id);
            
            $this->zapisFakturuDoPDF($faktura);
            
            exit(0);
            
        } else {
            
            header("HTTP/1.1 500 Internal Server Error");
            exit(0);
            
        }
    }
    
    private function zapisFakturuDoPDF($faktura) {
        require_once( 'includes/MPDF57/mpdf.php' );
//        include_once( 'includes/Nette/loader.php' );
        include_once( 'includes/vendor/autoload.php' );

        $mpdf = new \mPDF('utf-8');

        // Exporting prepared invoice to PDF.
        // To save the invoice into a file just use the second and the third parameter, equally as it's described in the documentation of mPDF->Output().
//        $eciovni = $this->createComponentEciovni($faktura);
//        $eciovni->exportToPdf($mpdf, $tmpfilename, "F");
        
        $this->exportToPdf($faktura, $mpdf);
    }
    
    /**
     * Exports Invoice template via passed mPDF.
     *
     * @param mPDF $mpdf
     * @return string|NULL
     */
    public function exportToPdf($faktura, mPDF $mpdf) {
        include_once 'includes/vendor/autoload.php';
        
        $titan = TitanFramework::getInstance( 'mw-fakturace' );

        $template = new Nette\Templating\FileTemplate(plugin_dir_path( __FILE__ ) . 'Eciovni.latte' );
        
        $template->onPrepareFilters[] = function ($template) {
            $template->registerFilter(new Nette\Latte\Engine);
        };

        $template->registerHelper('round', function($value, $precision = 2) {
            return number_format(round($value, $precision), $precision, ',', '');
        });

        $dodavatel = unserialize($faktura->odesilatel);
        $odberatel = unserialize($faktura->odberatel);
        
        $dateNow = new \Nette\DateTime($faktura->vystaveno);
        $dateExp = new \Nette\DateTime($faktura->vystaveno);
        $dateExp->modify(sprintf('+%d days', $faktura->splatnost_dnu));

        $template->title = $faktura->typ == self::VYDANA ? 'Faktura' : 'Dobropis';
        $template->id = $faktura->cislo;
        $template->variableSymbol = $faktura->cislo;
        
        $template->dateOfIssuance = $dateNow;
        $template->expirationDate = $dateExp;
        $template->dateOfVatRevenueRecognition = $dateNow;
        
        $template->supplierName = $dodavatel['nazev'];
        $template->supplierStreet = $dodavatel['street'];
        $template->supplierCity = $dodavatel['city'];
        $template->supplierZip = $dodavatel['postalCode'];
        $template->supplierIn = $dodavatel['IC'];
        $template->supplierTin = $dodavatel['DIC'];
        $template->supplierAccountNumber = $dodavatel['ucet'];
        
        $template->customerName = ( !empty($odberatel['nazev'])
                ? $odberatel['nazev'] 
                : $odberatel['firstName'] . ' ' . $odberatel['lastName'] );
        $template->customerStreet = $odberatel['street'];
        $template->customerCity = $odberatel['city'];
        $template->customerZip = $odberatel['postalCode'];
        $template->customerIn = $odberatel['IC'];
        $template->customerTin = $odberatel['DIC'];
        $template->customerAccountNumber = $odberatel['ucet'];
        
        $template->items = unserialize($faktura->polozky);
        
        $imageID = $titan->getOption( 'fakt-podpis-img' );
        $imageSrc = $imageID; // For the default value
        if ( is_numeric( $imageID ) ) {
            $imageAttachment = wp_get_attachment_image_src( $imageID );
            $imageSrc = $imageAttachment[0];
        }
        $template->signatureImgSrc = $imageSrc;
        $template->signatureText = $titan->getOption( 'fakt-podpis' );
        
        $mpdf->WriteHTML((string) $template);

        $result = $mpdf->Output('faktura-'.$faktura->cislo.'.pdf', 'I');
        return $result;
    }
    
}
