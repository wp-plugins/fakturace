<?php

/*
 * All right reserved to: Jan Cejka <posta@jancejka.cz>, http://jancejka.cz
 */

/**
 * Description of PlatbaGoPay
 *
 * @author Merlin
 */

require_once( 'includes/GoPay/api/gopay_soap.php' );
require_once( 'includes/GoPay/api/gopay_config.php' );
require_once( 'includes/GoPay/api/gopay_helper.php' );

use \SoapClient;

class PlatbaGoPay {

    static public function createPayment( 
            $GoID, $secureKey, 
            $cislo_faktury, 
            $cena, $mena, 
            $nazev_produktu, 
            $zakaznik, 
            $successURL, $failedURL, 
            $testGate = false,
            $fakturaModel = null,
            $fakturaID = null) {
        
        try {
            $totalPrice = $cena * 100;
            
//            $paymentMethods = \GopaySoap::paymentMethodList();
//            $paymentChannels = array();
//            foreach ($paymentMethods as $paymentMethod) {
//                array_push( $paymentChannels, $paymentMethod->code );
//            }
//            $paymentChannelsStr = implode(',', $paymentChannels);
            $paymentChannelsStr = "";
            
            $encryptedSignature1 = \GopayHelper::encrypt( \GopayHelper::hash( \GopayHelper::concatPaymentCommand(
                    (float) $GoID,
                    $nazev_produktu,
                    (float) $totalPrice, $mena,
                    $cislo_faktury,
                    $failedURL, $successURL,
                    false, false, null, null, null, $paymentChannelsStr,
                    $secureKey)
                ), $secureKey);

            $paymentCommand = array(
                "targetGoId" => (float) $GoID,
                "productName" => trim($nazev_produktu),
                "totalPrice" => (int) $totalPrice, "currency" => trim($mena),
                "orderNumber" => trim($cislo_faktury),
                "failedURL" => trim($failedURL), "successURL" => trim($successURL),
                "preAuthorization" => false, "recurrentPayment" => false,
                "paymentChannels" => $paymentChannelsStr,
                "defaultPaymentChannel" => "",
                "encryptedSignature" => $encryptedSignature1,
                "customerData" => $zakaznik,
                "p1" => null, "p2" => null, "p3" => null, "p4" => null
            );

            $go_config = new \GopayConfig();
            $go_config->init( $testGate ? \GopayConfig::TEST : \GopayConfig::PROD );
            $go_client = new \SoapClient( \GopayConfig::ws(), array() );
            
            $payment_status = $go_client->__call('createPayment', array('paymentCommand' => $paymentCommand));
            
            if( $payment_status->result == "CALL_COMPLETED" ) {

                if( $fakturaModel ) {
                    $fakturaModel->setPaymentSessID($fakturaID, $payment_status->paymentSessionId);
                }

                $encryptedSignature2 = \GopayHelper::encrypt(
                    \GopayHelper::hash(
                        \GopayHelper::concatPaymentSession(
                            (float)$GoID,
                            (float)$payment_status->paymentSessionId, 
                            $secureKey)
                        ), $secureKey);			

                /*
                 * Presmerovani na platebni branu GoPay s predvybranou platebni metodou GoPay penezenka ($defaultPaymentChannel)
                 */
                header('Location: ' . \GopayConfig::fullIntegrationURL() . "?sessionInfo.targetGoId=" . $GoID . "&sessionInfo.paymentSessionId=" . $payment_status->paymentSessionId . "&sessionInfo.encryptedSignature=" . $encryptedSignature2);
                exit;            
            }

            return array( 'result' => true, 'status' => $payment_status );
        } catch (SoapFault $f) {
            return array( 'result' => false, 'status' => $f );
        }
    }

    static public function processNotify( $paymentSessionId, $paymentGoId, $orderNumber, $parentPaymentSessionId, $encryptedSignature,
            $p1, $p2, $p3, $p4,
            $fakturyModel ) {
        
        require_once 'Faktura.php';
        require_once 'FakturaceFunkce.php';
        
        $titan = TitanFramework::getInstance( 'mw-fakturace' );

        $faktura = $fakturyModel->getByPaymentSessionID( $paymentSessionId );

        if( $faktura ) {
            $goid           = $titan->getOption( 'gopay-goid' );
            $seckey         = $titan->getOption( 'gopay-seckey' );
            $testGate       = $titan->getOption( 'gopay-test' );
            $nazev_produktu = $titan->getOption( 'fakt-produkt-nazev' );
            $mena           = 'CZK';

            /*
             * Kontrola validity parametru v http notifikaci, opatreni proti podvrzeni potvrzeni platby (notifikace)
             */
            try {

                $go_config = new \GopayConfig();
                $go_config->init( $testGate ? \GopayConfig::TEST : \GopayConfig::PROD );
                $go_client = new \SoapClient( \GopayConfig::ws(), array() );
            
                \GopayHelper::checkPaymentIdentity(
                            (float)$paymentGoId,
                            (float)$paymentSessionId,
                            (float)$parentPaymentSessionId,
                            $orderNumber,
                            $encryptedSignature,
                            (float)$goid,
                            $faktura->cislo,
                            $seckey);

                /*
                 * Kontrola zaplacenosti objednavky na strane GoPay
                 */
                $result = \GopaySoap::isPaymentDone(
                            (float)$paymentSessionId,
                            (float)$goid,
                            $faktura->cislo,
                            (int)$faktura->cena_s_dph * 100,
                            $mena,
                            trim($nazev_produktu),
                            $seckey);

                $fakturaUrl = home_url() . "?akce_fakturace=getpdf&id={$faktura->id}&key={$fakturyModel->getKeyByID($faktura->id)}";
                        
                $replaces = array(
                    'email' => $faktura->uzivatel_email,
                    'vs' => $faktura->cislo,
                    'faktura-url' => $fakturaUrl,
                    'login-url' => wp_login_url( home_url() ),
                );

                if ($result["sessionState"] == \GopayHelper::PAID) {
                    /*
                     * Zpracovat pouze objednavku, ktera jeste nebyla zaplacena 
                     */
                    if (empty($parentPaymentSessionId)) {
                        // notifikace o bezne platbe

                        if ($faktura->stav != Faktura::STAV_ZAPLACENA) {
                
                            /*
                             *  Zpracovani objednavky
                             */
                            $fakturyModel->setState( $faktura->id, Faktura::STAV_ZAPLACENA );


                            //TODO: aktivovat ucet a zaslat email

//                            foreach (unserialize($faktura->polozky) as $polozka) {
//                                if( $polozka['id_oblasti'] ) {
//                                    $oblasti->pripisKredit( $polozka['id_oblasti'], $polozka['cena_s_dph'], $context ); 
//                                }
//                            }

                            $odberatel = unserialize( $faktura->odberatel );
                            
                            $replaces['password'] = FakturaceFunkce::activateUser(
                                    $faktura->uzivatel_email, 
                                    $titan->getOption( 'user-activate-role' ),
                                    $odberatel['firstName'],
                                    $odberatel['lastName']
                                    );

                            self::sendEmail($faktura->uzivatel_email, 'pay', $replaces);

                        }
                
                    } else {
                        // notifikace o rekurentni platbe

                        /*
                         * Je potreba kontrolovat, jestli jiz toto returnedPaymentSessionId neni zaplaceno, aby pri 
                         * opakovane notifikaci nedoslo k duplicitnimu zaznamu o zaplaceni 
                         * a nasledne zaznamenat $returnedPaymentSessionId pro kontroly u dalsich opakovanych plateb
                         */
                        // if ($faktura->isPaidRecurrentPayment($returnedPaymentSessionId) != true) {
                
                            /*
                             *  pridani returnedPaymentSessionId do seznamu uhrazenych opakovanych plateb
                             */
                            // $faktura->addPaidRecurrentPayment($returnedPaymentSessionId);
                        // }

                    }
                
                } else if ( $result["sessionState"] == \GopayHelper::CANCELED) {
                    /* Platba byla zrusena objednavajicim */
                    if( $faktura->stav != Faktura::STAV_ZRUSENA )
                        self::sendEmail($faktura->uzivatel_email, 'error', $replaces);
                    $fakturyModel->setState( $faktura->id, Faktura::STAV_ZRUSENA );

                } else if ( $result["sessionState"] == \GopayHelper::TIMEOUTED) {
                    /* Platnost platby vyprsela  */
                    if( $faktura->stav != Faktura::STAV_TIMEOUT )
                        self::sendEmail($faktura->uzivatel_email, 'error', $replaces);
                    $fakturyModel->setState( $faktura->id, Faktura::STAV_TIMEOUT );

                } else if ( $result["sessionState"] == \GopayHelper::REFUNDED) {
                    /* Platba byla vracena - refundovana */
                    $fakturyModel->setState( $faktura->id, Faktura::STAV_REFUNDED );

                } else if ( $result["sessionState"] == \GopayHelper::AUTHORIZED) {
                    /* Platba byla autorizovana, ceka se na dokonceni  */
                    if( $faktura->stav != Faktura::STAV_AUTORIZOVANA )
                        self::sendEmail($faktura->uzivatel_email, 'order', $replaces);
                    $fakturyModel->setState( $faktura->id, Faktura::STAV_AUTORIZOVANA );

                } else if ( $result["sessionState"] == \GopayHelper::PAYMENT_METHOD_CHOSEN) {
                    /* Platebni metoda byla vybrana, ceka se na platbu  */
                    if( $faktura->stav != Faktura::STAV_CEKAME )
                        self::sendEmail($faktura->uzivatel_email, 'order', $replaces);
                    $fakturyModel->setState( $faktura->id, Faktura::STAV_CEKAME );

                } else {
                    header("HTTP/1.1 500 Internal Server Error");
                    exit(0);
                
                }

            } catch (Exception $e) {
                /*
                 * Nevalidni informace z http notifikaci - prevdepodobne pokus o podvodne zaslani notifikace
                 */
                header("HTTP/1.1 500 Internal Server Error");
                exit(0);
            }
            
        } else {
            /*
             * Nevalidni informace z http notifikaci - neexistujici faktura
             */
            header("HTTP/1.1 500 Internal Server Error");
            exit(0);
        }

    }
    
    private static function textReplace( $text, $replaces ) {
        foreach ($replaces as $klic => $hodnota) {
                $text = str_replace('{'.$klic.'}', $hodnota, $text);
        }
        return $text;
    }

    private static function sendEmail($email, $type, $replaces) {
        $titan = TitanFramework::getInstance( 'mw-fakturace' );
        
        $emailText = self::textReplace($titan->getOption( 'email-template-'.$type ), $replaces);
        $emailSubject = self::textReplace($titan->getOption( 'email-subject-'.$type ), $replaces);
        $emailSender = $titan->getOption( 'email-sender' );

        $headers = "$emailSender\r\n";
        add_filter('wp_mail_content_type', create_function('', 'return "text/html"; '));
        wp_mail($email, $emailSubject, $emailText, $headers);

    }
}
