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

class Ps_PaystackVerificationModuleFrontController extends ModuleFrontController
{
    public $cart_id;
    public $total;
    public $secure_key;

    public function init()
    {
        $this->cart_id = (int) Tools::getValue('id_cart');
        $this->total = (float) Tools::getValue('total');
        $this->secure_key = Tools::getValue('key');

        // Fix:: Uncaught Exception: Kernel Container is not available
        // https://github.com/PrestaShop/PrestaShop/issues/15503#issuecomment-530800626
        global $kernel;
        if (!$kernel) {
            require_once _PS_ROOT_DIR_ . '/app/AppKernel.php';
            $kernel = new \AppKernel('prod', false);
            $kernel->boot();
        }
    }

    public function initContent()
    {
        $txnref = Tools::getValue('trxref');
        $reference = Tools::getValue('reference');

        // verify paystack payment
        $paystack_key = $this->module->getSecretKey();

        try {
            $paystack = new Paystack($paystack_key);
            $paystack->disableFileGetContentsFallback();

            $txn = $paystack->transaction->verify([
                'reference' => $reference,
            ]);
        } catch (Exception $e) {
            // Todo:: throw error & redirect back
            die($e->getMessage());
        }

        if (!$txn->status) {
            die($txn->message);
        }

        $success = $txn->data->status === 'success';

        // if successful
        if ($success) {
            // place order
            $this->module->validateOrder(
                $this->cart_id,
                Configuration::get('PS_OS_PAYSTACK_PAID'),
                $this->total,
                $this->module->displayName,
                null,
                [],
                null,
                false,
                $this->secure_key
            );

            $params = [
                'id_cart' => $this->cart_id,
                'id_module' => (int) $this->module->id,
                'id_order' => (int) $this->module->currentOrder,
                'key' => $this->secure_key,
                'txnref' => $txnref,
                'reference' => $reference,
            ];

            $comfirmationUrl = $this->context->link->getPageLink(
                'order-confirmation',
                true,
                null,
                $params,
                false
            );

            Tools::redirectLink($comfirmationUrl);
        } else {
            // Todo:: handle errors
            $errors = [
                'no_retry' => false,
                'msg_long' => 'Error making payments',
                'error_code' => 'ER01',
                'error_msg' => 'There was an error processing your payment. Kindly try again later.',
            ];

            $errorPageUrl = $this->context->link->getModuleLink(
                $this->module->name,
                'error',
                $errors
            );

            Tools::redirectLink($errorPageUrl);
        }
    }
}
