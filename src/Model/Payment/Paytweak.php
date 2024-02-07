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

        return $objTemplate->parse();
    }

    public function getPostsaleOrder()
    {
        $orderId = $this->getOrderIdFromRequest();

        $this->addLog(sprintf('CGI 0 : CGI callback for order %s', $orderId));

        if (null === $orderId) {
            return null;
        }

        return Order::findByPk($orderId);
    }

    /**
     * Process payment on checkout page.
     * @param   IsotopeProductCollection    The order being places
     * @param   Module                      The checkout module instance
     * @return  mixed
     */
    public function processPostsale(IsotopeProductCollection $objOrder)
    {
        $this->getVars($objOrder, null);
        $this->addLog('CGI 0 : Call du retour CGI');

        return false;
    }
    
    protected function getReference()
    {
        return "REF" . $this->order->id; // Todo -> make format flexible
    }

    protected function getPostFromRequest()
    {
        return $this->getRequest()->request->all();
    }

    protected function getOrderIdFromRequest()
    {
        $parameters = $this->getPostFromRequest();

        return str_replace('REF', '', $parameters['reference']);
    }

    protected function getVars(IsotopeProductCollection $objOrder, Module $objModule = null)
    {
        $this->order = $objOrder;
        $this->module = $objModule;
        $this->billingAddress = $objOrder->getRelated('billing_address_id');
        $this->shippingAddress = $objOrder->getRelated('shipping_address_id');
        $this->amount = number_format(floatval($this->order->getTotal()), 2, ".", ""); // Todo -> extract function & make format flexible
        $this->payment = $this->order->getRelated('payment_id');
        $this->member = $this->order->getRelated('member');
        $this->reference = $this->getReference();
    }

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

    /**
     * Return the Symfony Request object of the current request.
     */
    protected function getRequest(): Request
    {
        return System::getContainer()->get('request_stack')->getCurrentRequest();
    }

    /**
     * Log system
     */
    private function addLog($msg): void
    {
        // use debug_backtrace() to retrieve the last method
        System::log($msg, __METHOD__, self::LOGCATEGORY);
    }
}
