<?php

$include_dir = Mage::getBaseDir('lib') . '/paycorp/';
require_once $include_dir . 'au.com.gateway.client.utils/IJsonHelper.php';
require_once $include_dir . 'au.com.gateway.client/GatewayClient.php';
require_once $include_dir . 'au.com.gateway.client.config/ClientConfig.php';
require_once $include_dir . 'au.com.gateway.client.component/RequestHeader.php';
require_once $include_dir . 'au.com.gateway.client.component/CreditCard.php';
require_once $include_dir . 'au.com.gateway.client.component/TransactionAmount.php';
require_once $include_dir . 'au.com.gateway.client.component/Redirect.php';
require_once $include_dir . 'au.com.gateway.client.facade/BaseFacade.php';
require_once $include_dir . 'au.com.gateway.client.payment/PaymentCompleteResponse.php';
require_once $include_dir . 'au.com.gateway.client.facade/Payment.php';
require_once $include_dir . 'au.com.gateway.client.payment/PaymentInitRequest.php';
require_once $include_dir . 'au.com.gateway.client.payment/PaymentInitResponse.php';
require_once $include_dir . 'au.com.gateway.client.helpers/PaymentCompleteJsonHelper.php';
require_once $include_dir . 'au.com.gateway.client.payment/PaymentCompleteRequest.php';
require_once $include_dir . 'au.com.gateway.client.root/PaycorpRequest.php';
require_once $include_dir . 'au.com.gateway.client.helpers/PaymentInitJsonHelper.php';
require_once $include_dir . 'au.com.gateway.client.utils/HmacUtils.php';
require_once $include_dir . 'au.com.gateway.client.utils/CommonUtils.php';
require_once $include_dir . 'au.com.gateway.client.utils/RestClient.php';
require_once $include_dir . 'au.com.gateway.client.enums/TransactionType.php';
require_once $include_dir . 'au.com.gateway.client.enums/Version.php';
require_once $include_dir . 'au.com.gateway.client.enums/Operation.php';
require_once $include_dir . 'au.com.gateway.client.facade/Vault.php';
require_once $include_dir . 'au.com.gateway.client.facade/Report.php';
require_once $include_dir . 'au.com.gateway.client.facade/AmexWallet.php';

class Paycorp_Payments_PaymentController extends Mage_Core_Controller_Front_Action
{
    /**
     * @throws Exception
     * @throws Mage_Core_Exception
     */
    public function redirectAction()
    {
        $session = Mage::getSingleton('checkout/session');

        // Load Order
        $order_id = $session->getLastRealOrderId();

        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($order_id);
        if (!$order->getId()) {
            Mage::throwException('No order for processing found');
        }

        // Set quote to inactive
        Mage::getSingleton('checkout/session')->setPaycorpQuoteId(Mage::getSingleton('checkout/session')->getQuoteId());
        Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
        Mage::getSingleton('checkout/session')->clear();

        /** @var Paycorp_Payments_Model_Method_CC $method */
        $method = $order->getPayment()->getMethodInstance();

        $currency_code = $order->getOrderCurrency()->getCurrencyCode();
        $amount = $order->getGrandTotal();

        try {
            $clientConfig = new ClientConfig();
            $clientConfig->setServiceEndpoint($method->getConfigData('pg_domain'));
            $clientConfig->setAuthToken($method->getConfigData('auth_token'));
            $clientConfig->setHmacSecret($method->getConfigData('hmac_secret'));
            $clientConfig->setValidateOnly(false);

            $client = new GatewayClient($clientConfig);

            $initRequest = new PaymentInitRequest();
            $initRequest->setClientId($method->getConfigData('client_id'));
            $initRequest->setTransactionType($method->getConfigData('transaction_type'));
            $initRequest->setClientRef($order_id);
            $initRequest->setComment('');
            $initRequest->setTokenize(false);
            //$initRequest->setExtraData(array('msisdn' => $msisdn));

            $transactionAmount = new TransactionAmount(intval($amount * 100));
            //$transactionAmount->setTotalAmount(intval($amount * 100));
            $transactionAmount->setServiceFeeAmount(0);
            $transactionAmount->setPaymentAmount(intval($amount * 100));
            $transactionAmount->setCurrency($currency_code);
            $initRequest->setTransactionAmount($transactionAmount);

            $redirect = new Redirect();
            $redirect->setReturnUrl(Mage::getUrl('paycorp/payment/success', array('_secure' => true)));
            $redirect->setReturnMethod('GET');
            $initRequest->setRedirect($redirect);

            //$initResponse = $client->getPayment()->init( $initRequest );
            $initResponse = $client->payment()->init($initRequest);
        } catch (Exception $e) {
            $message = $e->getMessage();

            // Cancel order
            $order->cancel();
            $order->addStatusHistoryComment($message, Mage_Sales_Model_Order::STATE_CANCELED);
            $order->save();

            // Set quote to active
            if ($quoteId = Mage::getSingleton('checkout/session')->getPaycorpQuoteId()) {
                $quote = Mage::getModel('sales/quote')->load($quoteId);
                if ($quote->getId()) {
                    $quote->setIsActive(true)->save();
                    Mage::getSingleton('checkout/session')->setQuoteId($quoteId);
                }
            }

            Mage::getSingleton('checkout/session')->addError($message);
            $this->_redirect('checkout/cart');
            return;
        }

        // Redirect
        Mage::app()->getFrontController()->getResponse()->setRedirect($initResponse->getPaymentPageUrl())->sendResponse();
    }

    /**
     * @throws Exception
     * @throws Mage_Core_Exception
     */
    public function successAction()
    {
        // Load Order
        $order_id = Mage::getSingleton('checkout/session')->getLastRealOrderId();

        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($order_id);
        if (!$order->getId()) {
            Mage::throwException('No order for processing found');
        }

        /** @var Paycorp_Payments_Model_Method_CC $method */
        $method = $order->getPayment()->getMethodInstance();
        
        $clientConfig = new ClientConfig();
        $clientConfig->setServiceEndpoint($method->getConfigData('pg_domain'));
        $clientConfig->setAuthToken($method->getConfigData('auth_token'));
        $clientConfig->setHmacSecret($method->getConfigData('hmac_secret'));

        $client = new GatewayClient($clientConfig);

        $completeRequest = new PaymentCompleteRequest();
        $completeRequest->setClientId($method->getConfigData('client_id'));
        $completeRequest->setReqid($_GET['reqid']);

        $completeResponse = $client->payment()->complete($completeRequest);
        $order_id = $completeResponse->getClientRef();
        $response_code = $completeResponse->getResponseCode();
        $transaction_id = $completeResponse->getTxnReference();

        switch ($response_code) {
            case '00':
                // Payment is success
                $message = sprintf('Transaction success. Transaction ID: %s, ', $transaction_id);

                // Change order status
                $orderState = Mage_Sales_Model_Order::STATE_PROCESSING;
                $orderStatus = $order->getConfig()->getStateDefaultStatus($orderState);
                $order->setData('state', $orderState);
                $order->setStatus($orderStatus);
                $order->addStatusHistoryComment($message, $orderStatus);

                // Save transaction
                Mage::helper('paycorp')->createTransaction($order->getPayment(),
                    null,
                    $transaction_id,
                    Mage_Sales_Model_Order_Payment_Transaction::TYPE_PAYMENT, 0
                );

                $invoice = Mage::helper('paycorp')->makeInvoice($order, false);
                $invoice->setTransactionId($transaction_id);
                $invoice->save();

                $order->save();
                $order->sendNewOrderEmail();

                // Redirect to Success Page
                Mage::getSingleton('checkout/session')->setLastSuccessQuoteId(Mage::getSingleton('checkout/session')->getPaycorpQuoteId());
                $this->_redirect('checkout/onepage/success', array('_secure' => true));
                break;
            default:
                $message = sprintf('Transaction failed. Transaction ID: %s. Code: %s', $transaction_id, $response_code);
        
                // Cancel order
                $order->cancel();
                $order->addStatusHistoryComment($message);
                $order->save();

                // Set quote to active
                if ($quoteId = Mage::getSingleton('checkout/session')->getPaycorpQuoteId()) {
                    $quote = Mage::getModel('sales/quote')->load($quoteId);
                    if ($quote->getId()) {
                        $quote->setIsActive(true)->save();
                        Mage::getSingleton('checkout/session')->setQuoteId($quoteId);
                    }
                }

                Mage::getSingleton('checkout/session')->addError($message);
                $this->_redirect('checkout/cart');
                break;
        }
        
    }
}
