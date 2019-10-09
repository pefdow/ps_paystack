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

use \Yabacon\Paystack;

class Ps_PaystackValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $cart = $this->context->cart;

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'ps_paystack') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->trans('This payment method is not available.', array(), 'Modules.PaystackPayment.Shop'));
        }

        $customer = new Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $total = (float) $cart->getOrderTotal(true, Cart::BOTH);

        $callback_url = $this->context->link->getModuleLink(
            $this->module->name,
            'verification',
            [
                'id_cart' => (int) $cart->id,
                'total' => $total,
                'key' => $customer->secure_key,
            ],
            true
        );

        if ($usePaystack = true) {

            $paystack_key = $this->module->getSecretKey();

            try {
                $paystack = new Paystack($paystack_key);
                $paystack->disableFileGetContentsFallback();

                $txn = $paystack->transaction->initialize([
                    'amount' => Tools::getValue('total_amount'),
                    'email' => Tools::getValue('email'),
                    'reference' => Tools::getValue('reference'),
                    'callback_url' => $callback_url,
                ]);
            } catch (Exception $e) {
                // Todo:: throw error & redirect back
                die($e->getMessage());
            }

            if (!$txn->status) {
                die($txn->message);
            }

            Tools::redirectLink($txn->data->authorization_url);
        } else {
            Tools::redirectLink($callback_url);
        }
    }
}
