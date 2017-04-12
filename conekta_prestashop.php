<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Conekta_Prestashop extends PaymentModule
{
    protected $_html = '';
    protected $_postErrors = array();

    public $details;
    public $owner;
    public $address;
    public $extra_mail_vars;

    public function __construct()
    {
        $this->name = 'conekta_prestashop';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'Conekta';
        $this->controllers = array('validation');
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        
        
        $config = Configuration::getMultiple(array('CHEQUE_NAME', 'CHEQUE_ADDRESS'));
        if (isset($config['CHEQUE_NAME'])) {
            $this->checkName = $config['CHEQUE_NAME'];
        }
        if (isset($config['CHEQUE_ADDRESS'])) {
            $this->address = $config['CHEQUE_ADDRESS'];
        }


        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Conekta Prestashop');
        $this->description = $this->l('This is a fucking awsome plugin');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
    }

    public function install()
    {
        if (!parent::install() || !$this->registerHook('paymentOptions') || !$this->registerHook('paymentReturn')) {
            return false;
        }
        return true;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $payment_options = [
            $this->getOfflinePaymentOption(),
            //$this->getExternalPaymentOption(),
            $this->getEmbeddedPaymentOption(),
            //$this->getIframePaymentOption(),
        ];

        return $payment_options;
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getOfflinePaymentOption()
    {
        $offlineOption = new PaymentOption();
        $offlineOption->setCallToActionText($this->l('Pay offline'))
                      ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
                      ->setAdditionalInformation($this->context->smarty->fetch('module:conekta_prestashop/views/templates/front/payment_infos.tpl'))
                      ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/payment.jpg'));

        return $offlineOption;
    }

    public function getExternalPaymentOption()
    {
        $externalOption = new PaymentOption();
        $externalOption->setCallToActionText($this->l('Pay external'))
                       ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
                       ->setInputs([
                            'token' => [
                                'name' =>'token',
                                'type' =>'hidden',
                                'value' =>'12345689',
                            ],
                        ])
                       ->setAdditionalInformation($this->context->smarty->fetch('module:conekta_prestashop/views/templates/front/payment_infos.tpl'))
                       ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/payment.jpg'));

        return $externalOption;
    }

    public function getEmbeddedPaymentOption()
    {
        $embeddedOption = new PaymentOption();
        $embeddedOption->setCallToActionText($this->l('Pay embedded'))
                       ->setForm($this->generateForm())
                       ->setAdditionalInformation($this->context->smarty->fetch('module:conekta_prestashop/views/templates/front/payment_infos.tpl'))
                       ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/payment.jpg'));

        return $embeddedOption;
    }

    public function getIframePaymentOption()
    {
        $iframeOption = new PaymentOption();
        $iframeOption->setCallToActionText($this->l('Pay iframe'))
                     ->setAdditionalInformation($this->context->smarty->fetch('module:conekta_prestashop/views/templates/front/payment_infos.tpl'))
                     ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/payment.jpg'));

        return $iframeOption;
    }
    private function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if (!Tools::getValue('CHEQUE_NAME')) {
                $this->_postErrors[] = $this->trans('The "Payee" field is required.', array(),'Modules.Checkpayment.Admin');
            } elseif (!Tools::getValue('CHEQUE_ADDRESS')) {
                $this->_postErrors[] = $this->trans('The "Address" field is required.', array(), 'Modules.Checkpayment.Admin');
            }
        }
    }
    private function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('CHEQUE_NAME', Tools::getValue('CHEQUE_NAME'));
            Configuration::updateValue('CHEQUE_ADDRESS', Tools::getValue('CHEQUE_ADDRESS'));
        }
        $this->_html .= $this->displayConfirmation($this->trans('Settings updated', array(), 'Admin.Notifications.Success'));
    }


    private function _displayCheck()
    {
        return $this->display(__FILE__, './views/templates/hook/infos.tpl');
    }
    public function getConfigFieldsValues()
    {
        return array(
            'CHEQUE_NAME' => Tools::getValue('CHEQUE_NAME', Configuration::get('CHEQUE_NAME')),
            'CHEQUE_ADDRESS' => Tools::getValue('CHEQUE_ADDRESS', Configuration::get('CHEQUE_ADDRESS')),
        );
    }


    public function renderForm()
    {
       $fields_form = array(
            'form' => array(
                'legend' => array(
                  'title' => $this->trans('Contact details', array(), 'Modules.Checkpayment.Admin'),
                  //'title' => 'some-title',  
                  'icon' => 'icon-envelope'
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Payee (name)', array(), 'Modules.Checkpayment.Admin'),
                        //'label' => 'some-label', 
                        'name' => 'CHEQUE_NAME',
                        'required' => true
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => $this->trans('Address', array(), 'Modules.Checkpayment.Admin'),
                        'desc' => $this->trans('Address where the check should be sent to.', array(), 'Modules.Checkpayment.Admin'),
                        //'label' => 'some-new-label', 
                        //'desc' => 'some-desc', 
                        'name' => 'CHEQUE_ADDRESS',
                        'required' => true
                      ),
                      array(
                          'type'      => 'radio',                               // This is an <input type="checkbox"> tag.
                          'label'     => $this->l('Mode'),        // The <label> for this <input> tag.
                          'name'      => 'active',                              // The content of the 'id' attribute of the <input> tag.
                          'required'  => true,                                  // If set to true, this option must be set.
                          'class'     => 't',                                   // The content of the 'class' attribute of the <label> tag for the <input> tag.
                          'is_bool'   => true,                                  // If set to true, this means you want to display a yes/no or true/false option.
                                                                                // The CSS styling will therefore use green mark for the option value '1', and a red mark for value '2'.
                                                                                // If set to false, this means there can be more than two radio buttons,
                                                                                // and the option label text will be displayed instead of marks.
                          'values'    => array(                                 // $values contains the data itself.
                            array(
                              'id'    => 'active_on',                           // The content of the 'id' attribute of the <input> tag, and of the 'for' attribute for the <label> tag.
                              'value' => 1,                                     // The content of the 'value' attribute of the <input> tag.
                              'label' => $this->l('Production')                    // The <label> for this radio button.
                            ),
                            array(
                              'id'    => 'active_off',
                              'value' => 0,
                              'label' => $this->l('Sandbox')
                            )
                          ),
                        ),
                     array(
                        'type' => 'text',
                        'label' => $this->trans('Webhook', array(), 'Modules.Checkpayment.Admin'),
                        //'label' => 'some-label', 
                        'name' => 'WEB_HOOK',
                        'required' => true
                      ),
                      array(
                        'type'    => 'checkbox',                   // This is an <input type="checkbox"> tag.
                        'label'   => $this->l('Payment Method'),          // The <label> for this <input> tag.
                        'desc'    => $this->l('Choose options.'),  // A help text, displayed right next to the <input> tag.
                        'name'    => 'Payment Methods',                    // The content of the 'id' attribute of the <input> tag.
                        'values'  => array(
                          'query' => array(
                            array(
                                'id' => 'card_payment_method',
                                'name' => $this->l('Card'),
                                'val' => 'card_payment_method'
                              ),
                           array(
                                'id' => 'installment_payment_method',
                                'name' => $this->l('Monthly Installents'),
                                'val' => 'installment_payment_method'
                            ),
                           array(
                               'id' => 'cash_payment_method',
                                'name' => $this->l('Cash'),
                                'val' => 'cash_payment_method'
                            ),
                           array(
                                'id' => 'banorte_payment_method',
                                'name' => $this->l('Banorte'),
                                'val' => 'banorte_payment_method'
                            ),
                           array(
                                'id' => 'spei_payment_method',
                                'name' => $this->l('SPEI'),
                                'val' => 'spei_payment_method'
                            ),

                        ),
                          //'query' => $options,                     // $options contains the data itself.
                          'id'    => 'id_option',                  // The value of the 'id' key must be the same as the key
                                                                   // for the 'value' attribute of the <option> tag in each $options sub-array.
                          'name'  => 'name'                        // The value of the 'name' key must be the same as the key
                        ),                                           // for the text content of the <option> tag in each $options sub-array.
                        'expand' => array(                      // 1.6-specific: you can hide the checkboxes when there are too many.
                                                                   // A button appears with the number of options it hides.
                          ['print_total'] => count($options),
                          'default' => 'show',
                          'show' => array('text' => $this->l('show'), 'icon' => 'plus-sign-alt'),
                          'hide' => array('text' => $this->l('hide'), 'icon' => 'minus-sign-alt')
                        ),
                      ),
                      array(
                        'type' => 'password',
                        'label' => $this->trans('Test Private Key', array(), 'Modules.Checkpayment.Admin'),
                        'name' => 'TEST_PRIVATE_KEY',
                        'required' => true
                      ),
                       array(
                        'type' => 'password',
                        'label' => $this->trans('Test Public Key', array(), 'Modules.Checkpayment.Admin'),
                        'name' => 'TEST_PUBLIC_KEY',
                        'required' => true
                      ),
                       array(
                        'type' => 'password',
                        'label' => $this->trans('Test Live Key', array(), 'Modules.Checkpayment.Admin'),
                        'name' => 'LIVE_PRIVATE_KEY',
                        'required' => true
                      ),
                       array(
                        'type' => 'password',
                        'label' => $this->trans('Live Public Key', array(), 'Modules.Checkpayment.Admin'),
                        'name' => 'LIVE_PUBLIC_KEY',
                        'required' => true
                      ),
            ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
                    //'title' => 'submit title'),
                )
            ),
          );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
        );

        $this->fields_form = array();

        return $helper->generateForm(array($fields_form));
    }
    public function getContent()
    {
        $this->_html = '';

        if (Tools::isSubmit('btnSubmit')) {
             $this->_postValidation();
             if (!count($this->_postErrors)) {
                 $this->_postProcess();
             } else {
                 foreach ($this->_postErrors as $err) {
                     $this->_html .= $this->displayError($err);
                 }
             }
         }

         $this->_html .= $this->_displayCheck();
         $this->_html .= $this->renderForm();

         return $this->_html;
    }

    protected function generateForm()
    {
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[] = sprintf("%02d", $i);
        }

        $years = [];
        for ($i = 0; $i <= 10; $i++) {
            $years[] = date('Y', strtotime('+'.$i.' years'));
        }

        $this->context->smarty->assign([
            'action' => $this->context->link->getModuleLink($this->name, 'validation', array(), true),
            'months' => $months,
            'years' => $years,
        ]);

        return $this->context->smarty->fetch('module:conekta_prestashop/views/templates/front/payment_form.tpl');
    }
}
