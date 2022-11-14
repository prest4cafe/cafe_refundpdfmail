<?php
/**
* 2007-2022 PrestaShop
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
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2022 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

use PrestaShop\PrestaShop\Core\Domain\Order\CancellationActionType;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Cafe_refundpdfmail extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'cafe_refundpdfmail';
        $this->tab = 'others';
        $this->version = '1.0.0';
        $this->author = 'presta.cafe';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('cafe_refundpdfmail');
        $this->description = $this->l('Allows you to add the credit note in pdf to the email');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        return parent::install() &&
            $this->registerHook('actionProductCancel');
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    public function getContent()
    {
        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output;
    }

    public function hookActionProductCancel($params)
    {
        if ($params['action'] === CancellationActionType::PARTIAL_REFUND) {
            $customer = new Customer((int)$params['order']->id_customer);
            $order = new Order((int)$params['order']->id);
            $from = Configuration::get('PS_SHOP_EMAIL');

            $orderDetailId = $params["id_order_detail"];
            $quantity = $params["cancel_quantity"];
            $amount = $params["cancel_amount"];

            $cancelledProducts = [[
                'id_order_detail' => $orderDetailId,
                'quantity' => $quantity,
                'amount' => $amount,
                'unit_price' => $amount/$quantity,
                'unit_price_tax_incl' => 0,
                'unit_price_tax_excl' => $amount/$quantity,
                'total_price_tax_incl' => 0,
                'total_price_tax_excl' => $amount,
            ]];

            $orderSlipTemp = OrderSlip::Create($order, $cancelledProducts, false, $amount, false, false);
            $getorderSlip = OrderSlip::getOrdersSlip((int)($order->id_customer), (int)($order->id), true);

            //on recupere et on instancie le dernier orderslip
            $orderSlip = new OrderSlip($getorderSlip[0]["id_order_slip"]);

            $pdf = new PDF($orderSlip, PDF::TEMPLATE_ORDER_SLIP, $this->context->smarty);

            $file_attachement['content'] = $pdf->render(false);
            $file_attachement['name'] = sprintf('%06d', $orderSlip->id).'.pdf';
            $file_attachement['mime'] = 'application/pdf';

            $var_list = [
                '{firstname}' => $customer->firstname,
                '{lastname}' => $customer->lastname,
                '{order_name}' => $order->reference,
                '{attached_file}' => '-',
            ];

            Mail::Send(
                $this->context->language->id,
                'credit_slip',
                $this->trans('New credit slip regarding your order', [], 'Emails.Subject'),
                $var_list,
                $customer->email,
                $customer->firstname .' '. $customer->lastname,
                null,
                null,
                $file_attachement,
                null,
                _PS_MAIL_DIR_,
                false,
                null,
                null,
                $from
            );

            $message_txt="Yeah. PARTIAL_REFUND";
            $type="success"; // can be error..
            $router = $this->get('router');
            $this->get('session')->getFlashBag()->add($type, $message_txt);
            $urlResend = $router->generate('admin_orders_view', ['orderId'=> (int)$order->id]);

            Tools::redirectAdmin($urlResend);
        } else {
            
            return false;
        }
    }
}
