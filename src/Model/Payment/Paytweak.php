<?php

declare(strict_types=1);

namespace ContaoIsotopePaytweakBundle\Model\Payment;

use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\FrontendTemplate;
use Contao\Module;
use Contao\System;
use ContaoIsotopePaytweakBundle\Paytweak\Wrapper;
use Exception;
use Haste\Input\Input;
use Isotope\Interfaces\IsotopePostsale;
use Isotope\Interfaces\IsotopeProductCollection;
use Isotope\Model\Address;
use Isotope\Model\Payment;
use Isotope\Model\Payment\Postsale;
use Isotope\Model\ProductCollection\Order;
use Symfony\Component\HttpFoundation\Request;

/**
 * TODO:
 *
 * - Documentation
 * - Add translations into template files
 * - Extract as many logic as possible
 * - Module can use member address as well so need to handle that behaviour too
 * - Unit tests
 * - Add log system depending on the payment mode & log level
 */
class Paytweak extends Postsale implements IsotopePostsale
{
    protected $order;
    protected $module;
    protected $amount;
    protected $payment;
    protected $member;
    protected $billingAddress;
    protected $shippingAddress;
    protected $wrapper;
    protected $strFormTemplate = 'mod_wem_iso_paytweak_payment_form';

    public const LOGCATEGORY = 'PAYTWEAK';

    /**
     * Return the Paytweak payment form.
     *
     * @param IsotopeProductCollection
     * @param Module
     *
     * @return string
     */
    public function checkoutForm(IsotopeProductCollection $objOrder, Module $objModule)
    {
        $this->getVars($objOrder, $objModule);

        $objTemplate = new FrontendTemplate($this->strFormTemplate);
        $objTemplate->order = $this->order;
        $objTemplate->member = $this->member;
        $objTemplate->amount = $this->amount;

        $this->wrapper = $this->getWrapper();
        $this->wrapper->api_connect();

        $r = (array) json_decode($this->wrapper->get_message());
        if ('OK' !== $r['code']) {
            $objTemplate->error = true;
            $objTemplate->message = $r['message'];

            return $objTemplate->parse();
        }

        $data = [
            'order_id' => $this->order->getUniqueId(),
            'amount' => $this->amount,
            'firstname' => $this->billingAddress->firstname,
            'lastname' => $this->billingAddress->lastname,
            'email' => $this->billingAddress->email,
            'billing_address' => $this->getBillingAddressAsJson(),
            'cart' => $this->getCartAsJson(),
        ];

        $this->wrapper->api_post_method("links", $data);
        $r = (array) json_decode($this->wrapper->get_message());

        if ('OK' === $r['code']) {
            $objTemplate->url = $r['url'];
            $objTemplate->qrcode = $r['qrcode'];
            $objTemplate->order_id = $r['order_id'];
        } else {
            $objTemplate->error = true;
            $objTemplate->message = $r['message'];
        }

        $objTemplate->response = $r;

        return $objTemplate->parse();
    }

    /**
     * Retrieve the Order with Paytweak request
     * 
     * @return Order
     */
    public function getPostsaleOrder()
    {
        // Filter requests from Paytweak, we only want PAYMENT requests
        $args = $this->getBodyFromRequest();
        if ('PAYMENT' !== $args['notice']) {
            return;
        }

        // Retrieve order ID
        $orderUniqID = $args['order_id'];
        $this->addLog(sprintf('CGI 0: CGI callback for order %s', $orderUniqID));

        // Break if we cannot look for order
        if (!$orderUniqID) {
            $this->addLog('CGI Error: Order not found');
            return null;
        }

        return Order::findOneByUniqid($orderUniqID);
    }

    /**
     * Process payment on checkout page.
     * @param   IsotopeProductCollection    The order being places
     * @param   Module                      The checkout module instance
     * 
     * @return  mixed (Response or nothing)
     */
    public function processPostsale(IsotopeProductCollection $objOrder)
    {
        try {
            $this->addLog('CGI 0: Starting Paytweak Postsale with Order ' . $objOrder->id);
            $this->getVars($objOrder);
            $args = $this->getBodyFromRequest();

            // Process payment
            if ($this->isPaymentOk()) {
                $this->addLog('CGI 1: Payment OK with transaction_id - ' . $args['transaction_id']);

                if ($this->order->checkout()) {
                    $this->order->setDatePaid(time());
                    $this->order->updateOrderStatus($this->new_order_status);
                    $this->addLog('CGI 2: Order marked as checked out with new status: ' . $this->new_order_status);
                } else {
                    throw new Exception('Something went wrong when checking out order with valid payment');
                }
            } else {
                $this->addLog('CGI 1: Payment KO with status - ' . $args['status'] . ' and reason - ' . $args['reason']);
                if (null === $this->order->getConfig()) {
                    throw new Exception('Config for Order ID ' . $this->order->getId() . ' not found');
                } elseif ($this->order->checkout()) {
                    $this->order->updateOrderStatus($this->order->getConfig()->orderstatus_error);
                    $this->addLog('CGI 2 : Order marked as checked out with new status: ' . $this->order->getConfig()->orderstatus_error);
                } else {
                    throw new Exception('Something went wrong when checking out order with invalid payment');
                }
            }
        } catch(Exception $e) {
            $this->addLog('CGI error: ' . $e->getMessage());
        }
    }

    /**
     * Format & return order amount
     * 
     * @return double
     */
    protected function getAmount()
    {
        return number_format(floatval($this->order->getTotal()), 2, ".", "");
    }

    /**
     * Format Billing address as Json
     * 
     * @return string
     */
    protected function getBillingAddressAsJson()
    {
        return json_encode([
            'address' => $this->billingAddress->street_1,
            'additional_information' => $this->billingAddress->street_2,
            'zip_code' => $this->billingAddress->postal,
            'city' => $this->billingAddress->city,
            'country' => strtoupper($this->billingAddress->country),
        ]);
    }

    /**
     * Format Shipping address as Json
     * 
     * @return string
     */
    protected function getShippingAddressAsJson()
    {
        return json_encode([
            'address' => $this->shippingAddress->street_1,
            'additional_information' => $this->shippingAddress->street_2,
            'zip_code' => $this->shippingAddress->postal,
            'city' => $this->shippingAddress->city,
            'country' => strtoupper($this->shippingAddress->country),
        ]);
    }

    /**
     * Format Order items as Json
     * 
     * @return string
     */
    protected function getCartAsJson()
    {
        $arrItems = $this->order->getItems();

        if (!$arrItems || empty($arrItems)) {
            throw new Exception('Cart is empty');
        }

        $arrCart = [];

        foreach($arrItems as $i) {
            $arrCart[] = [
                'id' => $i->sku,
                'description' => $i->name,
                'amount' => $i->price,
                'quantity' => $i->quantity,
            ];
        }

        return json_encode($arrCart);
    }
    
    /**
     * Retrieve order document reference
     * 
     * @return string
     */
    protected function getReference()
    {
        return $this->order->getDocumentNumber();
    }

    /**
     * Retrieve and format various vars from the order
     * 
     * @param  IsotopeProductCollection $objOrder
     * @param  Module|null              $objModule
     */
    protected function getVars(IsotopeProductCollection $objOrder, Module $objModule = null): void
    {
        $this->order = $objOrder;
        $this->module = $objModule;
        $this->billingAddress = $objOrder->getRelated('billing_address_id');
        $this->shippingAddress = $objOrder->getRelated('shipping_address_id');
        $this->amount = $this->getAmount();
        $this->payment = $this->order->getRelated('payment_id');
        $this->member = $this->order->getRelated('member');
        $this->reference = $this->getReference();
    }

    /**
     * Check if payment is valid from request
     * 
     * @return boolean
     */
    protected function isPaymentOk()
    {
        $args = $this->getBodyFromRequest();
        return 5 === (int) $args['status'] || 9 === (int) $args['status'];
    }

    /**
     * Return the Symfony Request object of the current request.
     *
     * @return Request
     */
    private function getRequest(): Request
    {
        return System::getContainer()->get('request_stack')->getCurrentRequest();
    }

    /**
     * Return request body as array
     * 
     * @return Array
     */
    private function getBodyFromRequest()
    {
        return json_decode($this->getRequest()->getContent(), true);
    }

    /**
     * Log system
     */
    private function addLog($msg): void
    {
        // use debug_backtrace() to retrieve the last method
        System::log($msg, __METHOD__, self::LOGCATEGORY);
    }

    /**
     * Retrieve Paytweak PHP Wrapper
     * 
     * @return Wrapper
     */
    private function getWrapper()
    {
        // Retrieve Encyption service
        $encryptionService = System::getContainer()->get('plenta.encryption');

        // Retrieve Paytweak Wrapper
        return new Wrapper(
            $encryptionService->decrypt($this->payment->paytweak_key_public),
            $encryptionService->decrypt($this->payment->paytweak_key_private),
        );
    }
}
