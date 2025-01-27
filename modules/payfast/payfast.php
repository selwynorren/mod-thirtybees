<?php
/**
 * payfast.php
 *
 * Copyright (c) 2008 PayFast (Pty) Ltd
 * You (being anyone who is not PayFast (Pty) Ltd) may download and use this plugin / code in your own website in conjunction with a registered and active PayFast account. If your PayFast account is terminated for any reason, you may not use this plugin / code or part thereof.
 * Except as expressly indicated in this licence, you may not use, copy, modify or distribute this plugin / code or part thereof in any way.
 * 
 * @author     Ron Darby<ron.darby@payfast.co.za>
 * @Modified   Selwyn Orren<selwyn@linuxweb.co.za>
 * @version    0.1.0
 * @date       12/09/2022
 *
 * @link       http://www.payfast.co.za/help/prestashop
 */

if (!defined('_PS_VERSION_'))
    exit;

class PayFast extends PaymentModule
{
    const LEFT_COLUMN = 0;
    const RIGHT_COLUMN = 1;
    const FOOTER = 2;
    const DISABLE = -1;
    const SANDBOX_MERCHANT_KEY = '46f0cd694581a';
    const SANDBOX_MERCHANT_ID = '10000100';
    const SANDBOX_PASSPHASE = 'jt7NOE43FZPn';
    
    public function __construct()
    {
        $this->name = 'payfast';
        $this->tab = 'payments_gateways';
        $this->version = '2.1.1';  
        $this->currencies = true;
        $this->currencies_mode = 'radio';
        
        parent::__construct();       
       
        $this->author  = 'PayFast';
        $this->page = basename(__FILE__, '.php');

        $this->displayName = $this->l('PayFast');
        $this->description = $this->l('Accept payments by credit card, EFT and cash from both local and international buyers, quickly and securely with PayFast.');
        $this->confirmUninstall = $this->l('Are you sure you want to delete your details ?');
 
        

        /* For 1.4.3 and less compatibility */
        $updateConfig = array('PS_OS_CHEQUE' => 1, 'PS_OS_PAYMENT' => 2, 'PS_OS_PREPARATION' => 3, 'PS_OS_SHIPPING' => 4, 'PS_OS_DELIVERED' => 5, 'PS_OS_CANCELED' => 6,
                      'PS_OS_REFUND' => 7, 'PS_OS_ERROR' => 8, 'PS_OS_OUTOFSTOCK' => 9, 'PS_OS_BANKWIRE' => 10, 'PS_OS_PAYPAL' => 11, 'PS_OS_WS_PAYMENT' => 12);
        foreach ($updateConfig as $u => $v)
            if (!Configuration::get($u) || (int)Configuration::get($u) < 1)
            {
                if (defined('_'.$u.'_') && (int)constant('_'.$u.'_') > 0)
                    Configuration::updateValue($u, constant('_'.$u.'_'));
                else
                    Configuration::updateValue($u, $v);
            }

    }

    public function install()
    {
        unlink(dirname(__FILE__).'/../../cache/class_index.php');
        if ( !parent::install() 
            OR !$this->registerHook('payment') 
            OR !$this->registerHook('paymentReturn') 
            OR !Configuration::updateValue('PAYFAST_MERCHANT_ID', '') 
            OR !Configuration::updateValue('PAYFAST_MERCHANT_KEY', '') 
            OR !Configuration::updateValue('PAYFAST_LOGS', '1') 
            OR !Configuration::updateValue('PAYFAST_MODE', 'test')
            OR !Configuration::updateValue('PAYFAST_PAYNOW_TEXT', 'Pay now with PayFast (Debit Card, Credit Card and Instant EFT)')
            OR !Configuration::updateValue('PAYFAST_PAYNOW_LOGO', 'on')  
            OR !Configuration::updateValue('PAYFAST_PAYNOW_ALIGN', 'right')
            OR !Configuration::updateValue('PAYFAST_PASSPHRASE', '')  )
        {            
            return false;
        }
            

        return true;
    }

    public function uninstall()
    {
        unlink(dirname(__FILE__).'/../../cache/class_index.php');
        return ( parent::uninstall() 
            AND Configuration::deleteByName('PAYFAST_MERCHANT_ID') 
            AND Configuration::deleteByName('PAYFAST_MERCHANT_KEY') 
            AND Configuration::deleteByName('PAYFAST_MODE') 
            AND Configuration::deleteByName('PAYFAST_LOGS')
            AND Configuration::deleteByName('PAYFAST_PAYNOW_TEXT') 
            AND Configuration::deleteByName('PAYFAST_PAYNOW_LOGO')            
            AND Configuration::deleteByName('PAYFAST_PAYNOW_ALIGN')
            AND Configuration::deleteByName('PAYFAST_PASSPHRASE')
            );

    }

    public function getContent()
    {
        global $cookie;
        $errors = array();
        $html = '<div style="width:550px">
            <p style="text-align:center;"><a href="https://www.payfast.co.za" target="_blank"><img src="'.__PS_BASE_URI__.'modules/payfast/secure_logo.png" alt="PayFast" boreder="0" /></a></p><br />';

             

        /* Update configuration variables */
        if ( Tools::isSubmit( 'submitPayfast' ) )
        {
            if( $paynow_text =  Tools::getValue( 'payfast_paynow_text' ) )
            {
                 Configuration::updateValue( 'PAYFAST_PAYNOW_TEXT', $paynow_text );
            }

            if( $paynow_logo =  Tools::getValue( 'payfast_paynow_logo' ) )
            {
                 Configuration::updateValue( 'PAYFAST_PAYNOW_LOGO', $paynow_logo );
            }
            if( $paynow_align =  Tools::getValue( 'payfast_paynow_align' ) )
            {
                 Configuration::updateValue( 'PAYFAST_PAYNOW_ALIGN', $paynow_align );
            }
            if( $passPhrase =  Tools::getValue( 'payfast_passphrase' ) )
            {
                 Configuration::updateValue( 'PAYFAST_PASSPHRASE', $passPhrase );
            }
            
            $mode = ( Tools::getValue( 'payfast_mode' ) == 'live' ? 'live' : 'test' ) ;
            Configuration::updateValue('PAYFAST_MODE', $mode );
            if( $mode != 'test' )
            {
                if( ( $merchant_id = Tools::getValue( 'payfast_merchant_id' ) ) AND preg_match('/[0-9]/', $merchant_id ) )
                {
                    Configuration::updateValue( 'PAYFAST_MERCHANT_ID', $merchant_id );
                }           
                else
                {
                    $errors[] = '<div class="warning warn"><h3>'.$this->l( 'Merchant ID seems to be wrong' ).'</h3></div>';
                }

                if( ( $merchant_key = Tools::getValue( 'payfast_merchant_key' ) ) AND preg_match('/[a-zA-Z0-9]/', $merchant_key ) )
                {
                    Configuration::updateValue( 'PAYFAST_MERCHANT_KEY', $merchant_key );
                }
                else
                {
                    $errors[] = '<div class="warning warn"><h3>'.$this->l( 'Merchant key seems to be wrong' ).'</h3></div>';
                }                  

                if( !sizeof( $errors ) )
                {
                    //Tools::redirectAdmin( $currentIndex.'&configure=payfast&token='.Tools::getValue( 'token' ) .'&conf=4' );
                }
                
            }
            if( Tools::getValue( 'payfast_logs' ) )
            {
                Configuration::updateValue( 'PAYFAST_LOGS', 1 );
            }
            else
            {
                Configuration::updateValue( 'PAYFAST_LOGS', 0 );
            } 
            foreach(array('displayLeftColumn', 'displayRightColumn', 'displayFooter') as $hookName)
                if ($this->isRegisteredInHook($hookName))
                    $this->unregisterHook($hookName);
            if (Tools::getValue('logo_position') == self::LEFT_COLUMN)
                $this->registerHook('displayLeftColumn');
            else if (Tools::getValue('logo_position') == self::RIGHT_COLUMN)
                $this->registerHook('displayRightColumn'); 
             else if (Tools::getValue('logo_position') == self::FOOTER)
                $this->registerHook('displayFooter'); 
            if( method_exists ('Tools','clearSmartyCache') )
            {
                Tools::clearSmartyCache();
            } 
            
        }      
        
       

        /* Display errors */
        if (sizeof($errors))
        {
            $html .= '<ul style="color: red; font-weight: bold; margin-bottom: 30px; width: 506px; background: #FFDFDF; border: 1px dashed #BBB; padding: 10px;">';
            foreach ($errors AS $error)
                $html .= '<li>'.$error.'</li>';
            $html .= '</ul>';
        }



        $blockPositionList = array(
            self::DISABLE => $this->l('Disable'),
            self::LEFT_COLUMN => $this->l('Left Column'),
            self::RIGHT_COLUMN => $this->l('Right Column'),
            self::FOOTER => $this->l('Footer'));

        if( $this->isRegisteredInHook('displayLeftColumn') )
        {
            $currentLogoBlockPosition = self::LEFT_COLUMN ;
        }
        elseif( $this->isRegisteredInHook('displayRightColumn') )
        {
            $currentLogoBlockPosition = self::RIGHT_COLUMN; 
        }
        elseif( $this->isRegisteredInHook('displayFooter'))
        {
            $currentLogoBlockPosition = self::FOOTER;
        }
        else
        {
            $currentLogoBlockPosition = -1;
        }
        

    /* Display settings form */
        $html .= '
        <form action="'.$_SERVER['REQUEST_URI'].'" method="post">
          <fieldset>
          <legend><img src="'.__PS_BASE_URI__.'modules/payfast/logo.gif" />'.$this->l('Settings').'</legend>
            <p>'.$this->l('Use the "Test" mode to test out the module then you can use the "Live" mode if no problems arise. Remember to insert your merchant key and ID for the live mode.').'</p>
            <label>
              '.$this->l('Mode').'
            </label>
            <div class="margin-form" style="width:110px;">
              <select name="payfast_mode">
                <option value="live"'.(Configuration::get('PAYFAST_MODE') == 'live' ? ' selected="selected"' : '').'>'.$this->l('Live').'&nbsp;&nbsp;</option>
                <option value="test"'.(Configuration::get('PAYFAST_MODE') == 'test' ? ' selected="selected"' : '').'>'.$this->l('Test').'&nbsp;&nbsp;</option>
              </select>
            </div>
            <p>'.$this->l('You can find your ID and Key in your PayFast account > My Account > Integration.').'</p>
            <label>
              '.$this->l('Merchant ID').'
            </label>
            <div class="margin-form">
              <input type="text" name="payfast_merchant_id" value="'.Tools::getValue('payfast_merchant_id', Configuration::get('PAYFAST_MERCHANT_ID')).'" />
            </div>
            <label>
              '.$this->l('Merchant Key').'
            </label>
            <div class="margin-form">
              <input type="text" name="payfast_merchant_key" value="'.trim(Tools::getValue('payfast_merchant_key', Configuration::get('PAYFAST_MERCHANT_KEY'))).'" />
            </div> 
            <p>'.$this->l('ONLY INSERT A VALUE INTO THE SECURE PASSPHRASE IF YOU HAVE SET THIS ON THE INTEGRATION PAGE OF THE LOGGED IN AREA OF THE PAYFAST WEBSITE!!!!!').'</p>'.           
            '<label>
              '.$this->l('Secure Passphrase').'
            </label>
            <div class="margin-form">
              <input type="text" name="payfast_passphrase" value="'.trim(Tools::getValue('payfast_passphrase', Configuration::get('PAYFAST_PASSPHRASE'))).'" />
            </div>
            <p>'.$this->l('You can log the server-to-server communication. The log file for debugging can be found at ').' '.__PS_BASE_URI__.'modules/payfast/payfast.log. '.$this->l('If activated, be sure to protect it by putting a .htaccess file in the same directory. If not, the file will be readable by everyone.').'</p>       
            <label>
              '.$this->l('Debug').'
            </label>
            <div class="margin-form" style="margin-top:5px">
              <input type="checkbox" name="payfast_logs"'.(Tools::getValue('payfast_logs', Configuration::get('PAYFAST_LOGS')) ? ' checked="checked"' : '').' />
            </div>
            <p>'.$this->l('During checkout the following is what the client gets to click on to pay with PayFast.').'</p>            
            <label>&nbsp;</label>
            <div class="margin-form" style="margin-top:5px">
                '.Configuration::get('PAYFAST_PAYNOW_TEXT');

           if(Configuration::get('PAYFAST_PAYNOW_LOGO')=='on')
            {
                $html .= '<img align="'.Configuration::get('PAYFAST_PAYNOW_ALIGN').'" alt="Pay Now With PayFast" title="Pay Now With PayFast" src="'.__PS_BASE_URI__.'modules/payfast/logo.png">';
            }
            $html .='</div>
            <label>
            '.$this->l('PayNow Text').'
            </label>
            <div class="margin-form" style="margin-top:5px">
                <input type="text" name="payfast_paynow_text" value="'. Configuration::get('PAYFAST_PAYNOW_TEXT').'">
            </div>
            <label>
            '.$this->l('PayNow Logo').'
            </label>
            <div class="margin-form" style="margin-top:5px">
                <input type="radio" name="payfast_paynow_logo" value="off" '.( Configuration::get('PAYFAST_PAYNOW_LOGO')=='off' ? ' checked="checked"' : '').'"> &nbsp; '.$this->l('None').'<br>
                <input type="radio" name="payfast_paynow_logo" value="on" '.( Configuration::get('PAYFAST_PAYNOW_LOGO')=='on' ? ' checked="checked"' : '').'"> &nbsp; <img src="'.__PS_BASE_URI__.'modules/payfast/logo.png">
            </div>
            <label>
            '.$this->l('PayNow Logo Align').'
            </label>
            <div class="margin-form" style="margin-top:5px">
                <input type="radio" name="payfast_paynow_align" value="left" '.( Configuration::get('PAYFAST_PAYNOW_ALIGN')=='left' ? ' checked="checked"' : '').'"> &nbsp; '.$this->l('Left').'<br>
                <input type="radio" name="payfast_paynow_align" value="right" '.( Configuration::get('PAYFAST_PAYNOW_ALIGN')=='right' ? ' checked="checked"' : '').'"> &nbsp; '.$this->l('Right').'
            </div>
            <p>'.$this->l('Where would you like the the Secure Payments made with PayFast image to appear on your website?').'</p>
            <label>
            '.$this->l('Select the image position').'
            <label>
            <div class="margin-form" style="margin-bottom:18px;width:110px;">
                  <select name="logo_position">';
                    foreach($blockPositionList as $position => $translation)
                    {
                        $selected = ($currentLogoBlockPosition == $position) ? 'selected="selected"' : '';
                        $html .= '<option value="'.$position.'" '.$selected.'>'.$translation.'</option>';
                    }
            $html .='</select></div>

            <div style="float:right;"><input type="submit" name="submitPayfast" class="button" value="'.$this->l('   Save   ').'" /></div><div class="clear"></div>
          </fieldset>
        </form>
        <br /><br />
        <fieldset>
          <legend><img src="../img/admin/warning.gif" />'.$this->l('Information').'</legend>
          <p>- '.$this->l('In order to use your PayFast module, you must insert your PayFast Merchant ID and Merchant Key above.').'</p>
          <p>- '.$this->l('Any orders in currencies other than ZAR will be converted by prestashop prior to be sent to the PayFast payment gateway.').'<p>
          <p>- '.$this->l('It is possible to setup an automatic currency rate update using crontab. You will simply have to create a cron job with currency update link available at the bottom of "Currencies" section.').'<p>
        </fieldset>
        </div>';
    
        return $html;
    }

    private function _displayLogoBlock($position)
    {      
        return '<div style="text-align:center;"><a href="https://www.payfast.co.za" target="_blank" title="Secure Payments With PayFast"><img src="'.__PS_BASE_URI__.'modules/payfast/secure_logo.png" width="150" /></a></div>';
    }

    public function hookDisplayRightColumn($params)
    {
        return $this->_displayLogoBlock(self::RIGHT_COLUMN);
    }

    public function hookDisplayLeftColumn($params)
    {
        return $this->_displayLogoBlock(self::LEFT_COLUMN);
    }  

    public function hookDisplayFooter($params)
    {
        $html = '<section id="payfast_footer_link" class="footer-block col-xs-12 col-sm-2">        
        <div style="text-align:center;"><a href="https://www.payfast.co.za" rel="nofollow" title="Secure Payments With PayFast"><img src="'.__PS_BASE_URI__.'modules/payfast/secure_logo.png"  /></a></div>  
        </section>';
        return $html;
    }    

    public function hookPayment($params)
    {   
        global $cookie, $cart; 
        if (!$this->active)
        {
            return;
        }
        
        // Buyer details
        $customer = new Customer((int)($cart->id_customer));
        
        $toCurrency = new Currency(Currency::getIdByIsoCode('ZAR'));
        $fromCurrency = new Currency((int)$cookie->id_currency);
        
        $total = $cart->getOrderTotal();

        $pfAmount = Tools::convertPriceFull( $total, $fromCurrency, $toCurrency );
       
        $data = array();

        $currency = $this->getCurrency((int)$cart->id_currency);
        if ($cart->id_currency != $currency->id)
        {
            // If PayFast currency differs from local currency
            $cart->id_currency = (int)$currency->id;
            $cookie->id_currency = (int)$cart->id_currency;
            $cart->update();
        }
        

        // Use appropriate merchant identifiers
        // Live
        if( Configuration::get('PAYFAST_MODE') == 'live' )
        {
            $data['info']['merchant_id'] = Configuration::get('PAYFAST_MERCHANT_ID');
            $data['info']['merchant_key'] = Configuration::get('PAYFAST_MERCHANT_KEY');
            $data['payfast_url'] = 'https://www.payfast.co.za/eng/process';
        }
        // Sandbox
        else
        {
            $data['info']['merchant_id'] = self::SANDBOX_MERCHANT_ID;
            $data['info']['merchant_key'] = self::SANDBOX_MERCHANT_KEY; 
            $data['payfast_url'] = 'https://sandbox.payfast.co.za/eng/process';
        }
        $data['payfast_paynow_text'] = Configuration::get('PAYFAST_PAYNOW_TEXT');        
        $data['payfast_paynow_logo'] = Configuration::get('PAYFAST_PAYNOW_LOGO');      
        $data['payfast_paynow_align'] = Configuration::get('PAYFAST_PAYNOW_ALIGN');
        // Create URLs
        $data['info']['return_url'] = $this->context->link->getPageLink( 'order-confirmation', null, null, 'key='.$cart->secure_key.'&id_cart='.(int)($cart->id).'&id_module='.(int)($this->id));
        $data['info']['cancel_url'] = Tools::getHttpHost( true ).__PS_BASE_URI__;
        $data['info']['notify_url'] = Tools::getHttpHost( true ).__PS_BASE_URI__.'modules/payfast/validation.php?itn_request=true';
    
        $data['info']['name_first'] = $customer->firstname;
        $data['info']['name_last'] = $customer->lastname;
        $data['info']['email_address'] = $customer->email;
        $data['info']['m_payment_id'] = $cart->id;
        $data['info']['amount'] = number_format( sprintf( "%01.2f", $pfAmount ), 2, '.', '' );
        $data['info']['item_name'] = Configuration::get('PS_SHOP_NAME') .' purchase, Cart Item ID #'. $cart->id; 
        $data['info']['custom_int1'] = $cart->id;       
        $data['info']['custom_str1'] = $cart->secure_key;           
            
        
        
        $pfOutput = '';
        // Create output string
        foreach( ($data['info']) as $key => $val )
            $pfOutput .= $key .'='. urlencode( trim( $val ) ) .'&';
    
        $passPhrase = Configuration::get( 'PAYFAST_PASSPHRASE' );
        if( empty( $passPhrase ) ||  Configuration::get('PAYFAST_MODE') != 'live' )
        {
            $pfOutput = $pfOutput."passphrase=".self::SANDBOX_PASSPHASE;
        }
        else
        {
            $pfOutput = $pfOutput."passphrase=".urlencode( $passPhrase );
        }

        $data['info']['signature'] = md5( $pfOutput );
        $data['info']['user_agent'] = 'ThirtyBees 1.3';
       
        $this->context->smarty->assign( 'data', $data );        
  
        return $this->display(__FILE__, 'payfast.tpl'); 
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active)
        {
            return;
        }
        $test = __FILE__;

        return $this->display($test, 'payfast_success.tpl');
    
    }
   
}
