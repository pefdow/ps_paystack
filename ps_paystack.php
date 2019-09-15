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


if (!defined('_PS_VERSION_')) {
    exit;
}

class Ps_Paystack extends Module
{
    protected $_html = '';
    protected $_postErrors = [];

    public function __construct() {
        $this->name = "ps_paystack";
        $this->version = "0.0.1";
        $this->tab = 'payments_gateways';
        $this->author = "Adedayo Ajayi";
        $this->ps_versions_compliancy = ['min' => '1.7.1.0', 'max' => _PS_VERSION_];

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans('Paystack Payments');
        $this->description = $this->trans('Accept payments via Paystack.');

        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall?');
    }
    
    public function install() {
        Configuration::updateValue('PS_PAYSTACK_TEST_MODE', 1);
        Configuration::updateValue('PS_PAYSTACK_FEE_RATE', 1.5);
        Configuration::updateValue('PS_PAYSTACK_MAX_FEE', 2000);
        Configuration::updateValue('PS_PAYSTACK_CHARGE_CUSTOMER', 0);
        return parent::install();
    }

    public function uninstall() {
        if (!Configuration::deleteByName('PS_PAYSTACK_TEST_SECRETKEY')
            || !Configuration::deleteByName('PS_PAYSTACK_TEST_PUBLICKEY')
            || !Configuration::deleteByName('PS_PAYSTACK_LIVE_PUBLICKEY')
            || !Configuration::deleteByName('PS_PAYSTACK_LIVE_SECRETKEY')
            || !Configuration::deleteByName('PS_PAYSTACK_TEST_MODE')
            || !Configuration::deleteByName('PS_PAYSTACK_FEE_RATE')
            || !Configuration::deleteByName('PS_PAYSTACK_MAX_FEE')
            || !Configuration::deleteByName('PS_PAYSTACK_CHARGE_CUSTOMER')
            || !parent::uninstall()
        ) {
            return false;
        }
        return true;
    }

    public function getContent() {
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

        $this->_html .= $this->_displayPaystackInfo();
        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    public function renderForm() {
        // Get default language
        $defaultLang = (int)Configuration::get('PS_LANG_DEFAULT');

        $fields_general_form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Paystack General Configuration', [], 'Modules.PaystackPayment.Admin'),
                    'icon' => 'icon-user'
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Test Mode', [], 'Modules.PaystackPayment.Admin'),
                        'name' => 'PS_PAYSTACK_TEST_MODE',
                        'is_bool' => true,
                        'hint' => $this->trans('Switch between TEST mode and LIVE mode', array(), 'Modules.PaystackPayment.Admin'),
                        'required' => true,
                         'values' => [
                                [
                                    'id' => 'active_on',
                                    'value' => true,
                                    'label' => $this->trans('True', [], 'Modules.PaystackPayment.Admin')
                                ],
                                [
                                    'id' => 'active_off',
                                    'value' => false,
                                    'label' => $this->trans('False', [], 'Modules.PaystackPayment.Admin')
                                ]
                            ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Transaction Fee Rate (%)', [], 'Modules.PaystackPayment.Admin'),
                        'name' => 'PS_PAYSTACK_FEE_RATE',
                       
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Maximum Transaction Fee', [], 'Modules.PaystackPayment.Admin'),
                        'name' => 'PS_PAYSTACK_MAX_FEE',
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Charge Customer', [], 'Modules.PaystackPayment.Admin'),
                        'name' => 'PS_PAYSTACK_CHARGE_CUSTOMER',
                        'is_bool' => true,
                        'hint' => $this->trans('Add paystack fee to client\'s total amount.', array(), 'Modules.PaystackPayment.Admin'),
                        'required' => true,
                         'values' => [
                                [
                                    'id' => 'active_on',
                                    'value' => true,
                                    'label' => $this->trans('True', [], 'Modules.PaystackPayment.Admin')
                                ],
                                [
                                    'id' => 'active_off',
                                    'value' => false,
                                    'label' => $this->trans('False', [], 'Modules.PaystackPayment.Admin')
                                ]
                            ],
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                ]
            ]
        ];

        $fields_test_form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Paystack Test Configuration', [], 'Modules.PaystackPayment.Admin'),
                    'icon' => 'icon-cogs'
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->trans('Test Secret key', [], 'Modules.PaystackPayment.Admin'),
                        'name' => 'PS_PAYSTACK_TEST_SECRETKEY',
                       
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Test Public key', [], 'Modules.PaystackPayment.Admin'),
                        'name' => 'PS_PAYSTACK_TEST_PUBLICKEY',
                    ], 
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                ]
            ]
        ];

        $fields_live_form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Paystack Live Configuration', [], 'Modules.PaystackPayment.Admin'),
                    'icon' => 'icon-cogs'
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->trans('Live Secret key', [], 'Modules.PaystackPayment.Admin'),
                        'name' => 'PS_PAYSTACK_LIVE_SECRETKEY',
                       
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Live Public key', [], 'Modules.PaystackPayment.Admin'),
                        'name' => 'PS_PAYSTACK_LIVE_PUBLICKEY',
                    ],  
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                ]
            ]
        ];

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;

        // Language
        $helper->default_form_language = $defaultLang;
        $helper->allow_employee_form_lang = $defaultLang;

        // Title and toolbar
        $helper->show_toolbar = false;
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
        );

        $this->fields_form = [];

        return $helper->generateForm([$fields_general_form, $fields_test_form, $fields_live_form]);
    }

    public function getConfigFieldsValues() {
        return [
            'PS_PAYSTACK_TEST_SECRETKEY' => Tools::getValue('PS_PAYSTACK_TEST_SECRETKEY', Configuration::get('PS_PAYSTACK_TEST_SECRETKEY')),
            'PS_PAYSTACK_TEST_PUBLICKEY' => Tools::getValue('PS_PAYSTACK_TEST_PUBLICKEY', Configuration::get('PS_PAYSTACK_TEST_PUBLICKEY')),
            'PS_PAYSTACK_LIVE_SECRETKEY' => Tools::getValue('PS_PAYSTACK_LIVE_SECRETKEY', Configuration::get('PS_PAYSTACK_LIVE_SECRETKEY')),
            'PS_PAYSTACK_LIVE_PUBLICKEY' => Tools::getValue('PS_PAYSTACK_LIVE_PUBLICKEY', Configuration::get('PS_PAYSTACK_LIVE_PUBLICKEY')),
            'PS_PAYSTACK_TEST_MODE' => Tools::getValue('PS_PAYSTACK_TEST_MODE', Configuration::get('PS_PAYSTACK_TEST_MODE')),
            'PS_PAYSTACK_FEE_RATE' => Tools::getValue('PS_PAYSTACK_FEE_RATE', Configuration::get('PS_PAYSTACK_FEE_RATE')),
            'PS_PAYSTACK_MAX_FEE' => Tools::getValue('PS_PAYSTACK_MAX_FEE', Configuration::get('PS_PAYSTACK_MAX_FEE')),
            'PS_PAYSTACK_CHARGE_CUSTOMER' => Tools::getValue('PS_PAYSTACK_CHARGE_CUSTOMER', Configuration::get('PS_PAYSTACK_CHARGE_CUSTOMER')),
        ];
    }

    protected function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('PS_PAYSTACK_TEST_SECRETKEY', Tools::getValue('PS_PAYSTACK_TEST_SECRETKEY'));
            Configuration::updateValue('PS_PAYSTACK_TEST_PUBLICKEY', Tools::getValue('PS_PAYSTACK_TEST_PUBLICKEY'));
            Configuration::updateValue('PS_PAYSTACK_LIVE_SECRETKEY', Tools::getValue('PS_PAYSTACK_LIVE_SECRETKEY'));
            Configuration::updateValue('PS_PAYSTACK_LIVE_PUBLICKEY', Tools::getValue('PS_PAYSTACK_LIVE_PUBLICKEY'));
            Configuration::updateValue('PS_PAYSTACK_TEST_MODE', Tools::getValue('PS_PAYSTACK_TEST_MODE'));
            Configuration::updateValue('PS_PAYSTACK_FEE_RATE', Tools::getValue('PS_PAYSTACK_FEE_RATE'));
            Configuration::updateValue('PS_PAYSTACK_MAX_FEE', Tools::getValue('PS_PAYSTACK_MAX_FEE'));
            Configuration::updateValue('PS_PAYSTACK_CHARGE_CUSTOMER', Tools::getValue('PS_PAYSTACK_CHARGE_CUSTOMER'));
        }
        $this->_html .= $this->displayConfirmation($this->trans('Settings updated', array(), 'Admin.Notifications.Success'));
    }

    private function _displayPaystackInfo() {
        return $this->display(__FILE__, 'infos.tpl');
    }

    private function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if ((int)Tools::getValue('PS_PAYSTACK_TEST_MODE') == 1) {
                if (!Tools::getValue('PS_PAYSTACK_TEST_SECRETKEY')) {
                    $this->_postErrors[] = $this->trans('The "Test Secret Key" field is required.', array(),'Modules.PaystackPayment.Admin');
                }
                if (!Tools::getValue('PS_PAYSTACK_TEST_PUBLICKEY')) {
                    $this->_postErrors[] = $this->trans('The "Test Public Key" field is required.', array(), 'Modules.PaystackPayment.Admin');
                }
            } else {
                if (!Tools::getValue('PS_PAYSTACK_LIVE_SECRETKEY')) {
                    $this->_postErrors[] = $this->trans('The "Live Secret Key" field is required.', array(),'Modules.PaystackPayment.Admin');
                }
                if (!Tools::getValue('PS_PAYSTACK_LIVE_PUBLICKEY')) {
                    $this->_postErrors[] = $this->trans('The "Live Public Key" field is required.', array(), 'Modules.PaystackPayment.Admin');
                }
            }
            
        }
    }

}
