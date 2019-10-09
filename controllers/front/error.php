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

class Ps_PaystackErrorModuleFrontController extends ModuleFrontController
{
    public $error_msg;
    public $msg_long;
    public $error_code;
    public $no_retry;

    public function init()
    {
        parent::init();
        $this->error_msg = Tools::getvalue('error_msg');
        $this->msg_long = Tools::getvalue('msg_long');
        $this->error_code = Tools::getvalue('error_code');
        $this->no_retry = Tools::getvalue('no_retry');
    }

    public function initContent()
    {
        parent::initContent();

        Context::getContext()->smarty->assign(array(
            'error_msg' => $this->error_msg,
            'msg_long' => $this->msg_long,
            'error_code' => $this->error_code,
            'show_retry' => (Context::getContext()->cart && Context::getContext()->cart->nbProducts() > 0 && !$this->no_retry) ? true : false,
        ));

        $this->setTemplate('module:ps_paystack/views/templates/front/payment_error.tpl');
    }
}
