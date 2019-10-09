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

class Ps_Paystack extends PaymentModule
{
    protected $_html = '';
    protected $_postErrors = [];

    public function __construct()
    {
        $this->name = "ps_paystack";
        $this->version = "0.0.1";
        $this->tab = 'payments_gateways';
        $this->author = "Adedayo Ajayi";
        $this->ps_versions_compliancy = ['min' => '1.7.1.0', 'max' => _PS_VERSION_];
        $this->controllers = array('validation', 'verification');

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans('Paystack Payment');
        $this->description = $this->trans('Accept payments via Paystack.');

        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall?');
    }

    public function install()
    {
        // Registration order status
        if (!$this->installOrderState()) {
            return false;
        }

        Configuration::updateValue('PS_PAYSTACK_TEST_MODE', 1);
        Configuration::updateValue('PS_PAYSTACK_FEE_RATE', 1.5);
        Configuration::updateValue('PS_PAYSTACK_MAX_FEE', 2000);
        Configuration::updateValue('PS_PAYSTACK_CHARGE_CUSTOMER', 0);
        return parent::install() && $this->registerHook('paymentOptions') && $this->registerHook('paymentReturn');
    }

    public function uninstall()
    {
        if (
            !Configuration::deleteByName('PS_PAYSTACK_TEST_SECRETKEY')
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

        $this->_html .= $this->_displayPaystackInfo();
        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    public function renderForm()
    {
        // Get default language
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');

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
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;

        // Language
        $helper->default_form_language = $defaultLang;
        $helper->allow_employee_form_lang = $defaultLang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = false;
        $helper->id = (int) Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
        );

        $this->fields_form = [];

        return $helper->generateForm([$fields_general_form, $fields_test_form, $fields_live_form]);
    }

    public function getConfigFieldsValues()
    {
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

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        $params = $this->getTemplateVarInfos();
        $this->smarty->assign($params);

        $newOption = new PaymentOption();
        $newOption->setModuleName($this->name)
            ->setCallToActionText($this->trans('Pay via Paystack', [], 'Modules.PaystackPayment.Admin'))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', [], true))
            ->setAdditionalInformation($this->fetch('module:ps_paystack/views/templates/front/payment_infos.tpl'))
            ->setInputs($this->_generateInputs($params));

        return [$newOption];
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        return $this->fetch('module:ps_paystack/views/templates/hook/payment_return.tpl');
    }

    public function getTemplateVarInfos()
    {
        $paystack_public_key = $this->getPublicKey();
        $paystack_secret_key = $this->getSecretKey();

        if ($paystack_public_key == '' || $paystack_secret_key == '') {
            return; // Todo:: invalid setup
        }

        $cart = $this->context->cart;
        $customer = new Customer((int) ($cart->id_customer));
        $amount = $cart->getOrderTotal(true, Cart::BOTH);
        $currency_order = new Currency($cart->id_currency);

        $params = array(
            "reference"   => 'KDN' . $cart->id . 'PR' . time(),
            "amount"      => number_format($amount, 2),
            "total_amount" => $amount * 100,
            "display_amount" => Tools::displayPrice($amount, $currency_order, false),
            "currency"    => $currency_order->iso_code,
            "email"       => $customer->email,
        );

        return $params;
    }

    public function installOrderState()
    {
        $states = [
            'PS_OS_PAYSTACK_WAITING' => [
                'name' => 'Awaiting PayStack payment',
                'send_email' => false,
                'color' => '#4169E1',
                'hidden' => false,
                'delivery' => false,
                'logable' => false,
                'invoice' => false,
                'shipped' => false,
                'paid' => false,
                'pdf_invoice' => false,
                'pdf_delivery' => false,
                'unremovable' => true,
            ],
            'PS_OS_PAYSTACK_PAID' => [
                'name' => 'Paid via PayStack',
                'send_email' => true,
                'color' => '#0CA4DA',
                'hidden' => false,
                'delivery' => false,
                'logable' => true,
                'invoice' => true,
                'shipped' => false,
                'paid' => true,
                'pdf_invoice' => true,
                'pdf_delivery' => false,
                'unremovable' => true,
                'template' => 'payment'
            ]
        ];

        foreach ($states as $key => $state) {
            if (
                !Configuration::get($key)
                || !Validate::isLoadedObject(new OrderState(Configuration::get($key)))
            ) {
                $order_state = new OrderState();
                $order_state->module_name = $this->name;
                $order_state->name = array();
                foreach (Language::getLanguages() as $language) {
                    $order_state->name[$language['id_lang']] = $state['name'];
                    if (isset($state['template'])) {
                        $order_state->template = $state['template'];
                    }
                }
                $order_state->send_email = $state['send_email'];
                $order_state->color = $state['color'];
                $order_state->hidden = $state['hidden'];
                $order_state->delivery = $state['delivery'];
                $order_state->logable = $state['logable'];
                $order_state->invoice = $state['invoice'];
                $order_state->shipped = $state['shipped'];
                $order_state->paid = $state['paid'];
                $order_state->pdf_invoice = $state['pdf_invoice'];
                $order_state->pdf_delivery = $state['pdf_delivery'];
                $order_state->unremovable = $state['unremovable'];
                $order_state->add();
                Configuration::updateValue($key, (int) $order_state->id);
            }
        }
        return true;
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

    private function _displayPaystackInfo()
    {
        return $this->display(__FILE__, 'infos.tpl');
    }

    private function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if ((int) Tools::getValue('PS_PAYSTACK_TEST_MODE') == 1) {
                if (!Tools::getValue('PS_PAYSTACK_TEST_SECRETKEY')) {
                    $this->_postErrors[] = $this->trans('The "Test Secret Key" field is required.', [], 'Modules.PaystackPayment.Admin');
                }
                if (!Tools::getValue('PS_PAYSTACK_TEST_PUBLICKEY')) {
                    $this->_postErrors[] = $this->trans('The "Test Public Key" field is required.', [], 'Modules.PaystackPayment.Admin');
                }
            } else {
                if (!Tools::getValue('PS_PAYSTACK_LIVE_SECRETKEY')) {
                    $this->_postErrors[] = $this->trans('The "Live Secret Key" field is required.', [], 'Modules.PaystackPayment.Admin');
                }
                if (!Tools::getValue('PS_PAYSTACK_LIVE_PUBLICKEY')) {
                    $this->_postErrors[] = $this->trans('The "Live Public Key" field is required.', [], 'Modules.PaystackPayment.Admin');
                }
            }
        }
    }

    private function _generateInputs($params)
    {
        $inputs = [];

        foreach ($params as $key => $value) {
            $inputs[$key] = [
                'name' => $key,
                'type' => 'hidden',
                'value' => $value
            ];
        }

        return $inputs;
    }

    public function getSecretKey()
    {
        $paystack_mode = Configuration::get('PS_PAYSTACK_TEST_MODE');
        $paystack_key = $paystack_mode == 1 ? Configuration::get('PS_PAYSTACK_TEST_SECRETKEY') : Configuration::get('PS_PAYSTACK_LIVE_SECRETKEY');

        if ($paystack_key == '') {
            throw new PrestaShopException('Paystack secret key not set.');
        }

        return $paystack_key;
    }

    public function getPublicKey()
    {
        $paystack_mode = Configuration::get('PS_PAYSTACK_TEST_MODE');
        $paystack_key = $paystack_mode == 1 ? Configuration::get('PS_PAYSTACK_TEST_PUBLICKEY') : Configuration::get('PS_PAYSTACK_LIVE_PUBLICKEY');

        if ($paystack_key == '') {
            throw new PrestaShopException('Paystack public key not set.');
        }

        return $paystack_key;
    }
}
