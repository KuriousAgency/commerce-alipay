<?php
/**
 * Commerce Alipay plugin for Craft CMS 3.x
 *
 * Alipay integration for Craft Commerce
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2019 Kurious Agency
 */

namespace kuriousagency\commerce\alipay\gateways;

use kuriousagency\commerce\alipay\models\RequestResponse;
use kuriousagency\commerce\alipay\models\AlipayOffsitePaymentForm;

use Craft;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin as Commerce;
use craft\commerce\omnipay\base\OffsiteGateway;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\helpers\UrlHelper;
use craft\web\Response as WebResponse;
use Omnipay\Common\AbstractGateway;
use Omnipay\Omnipay;
use Omnipay\GlobalAlipay\Message\WebPurchaseRequest;
use Omnipay\GlobalAlipay\Message\WebPurchaseResponse;
use Omnipay\GlobalAlipay\Message\CompletePurchaseRequest;
use Omnipay\GlobalAlipay\Message\CompletePurchaseResponse;
use Omnipay\GlobalAlipay\WebGateway as OmnipayGateway;
use yii\base\NotSupportedException;

/**
 * @author    Kurious Agency
 * @package   CommerceAlipay
 * @since     1.0.0
 */
class Gateway extends OffsiteGateway
{
    // Public Properties
	// =========================================================================
	
	// partner ID,It's a 16-bit string start with "2088".Login in https://globalprod.alipay.com/order/myOrder.htm to see your partner ID.
	public $partner;

	// MD5 key . The security check code, 32 bit string composed of numbers and letters.See your key at https://globalprod.alipay.com/order/myOrder.htm
	public $key;

	public $testMode = false;



	public static function displayName(): string
	{
		return Craft::t('commerce', 'Alipay');
	}

	public function getSettingsHtml()
	{
		return Craft::$app->getView()->renderTemplate('commerce-alipay/gatewaySettings', ['gateway' => $this]);
	}


	
	public function populateRequest(array &$request, BasePaymentForm $paymentForm = null)
	{
		// $params = [
		// 	'out_trade_no' => date('YmdHis') . mt_rand(1000,9999), //your site trade no, unique
		// 	'subject'      => 'test', //order title
		// 	'total_fee'    => '0.01', //order total fee
		// 	'currency'     => 'USD', //default is 'USD'
		// ];

		parent::populateRequest($request, $paymentForm);
        $request['type'] = 'redirect';
		$request['out_trade_no'] = $request['transactionId'];
		$request['subject'] = $request['order']->number;
		$request['total_fee'] = $request['amount'];

		//Craft::dd($request);
	}


	// public function completePurchase(Transaction $transaction): RequestResponseInterface
	// {
	// 	if (!$this->supportsCompletePurchase()) {
	// 		throw new NotSupportedException(Craft::t('commerce', 'Completing purchase is not supported by this gateway'));
	// 	}

	// 	$request = $this->createRequest($transaction);
	// 	$request['transactionReference'] = $transaction->reference;

	// 	Craft::dd($request);
	// 	//$completeRequest = $this->prepareCompletePurchaseRequest($request);

	// 	//return $this->performRequest($completeRequest, $transaction);
	// }

	public function supportsWebhooks(): bool
	{
		return true;
	}

	// public function getWebhookUrl(array $params = []): string
	// {
	// 	return UrlHelper::actionUrl('commerce/payments/complete-payment', $params);
	// }

	public function processWebHook(): WebResponse
	{
		// $response = Craft::$app->getResponse();
        // $transactionHash = Craft::$app->getRequest()->getParam('commerceTransactionHash');
        // $transaction = Commerce::getInstance()->getTransactions()->getTransactionByHash($transactionHash);
        // $childTransaction = Commerce::getInstance()->getTransactions()->createTransaction(null, $transaction);
        // $childTransaction->type = $transaction->type;
        // if (!$transaction) {
        //     Craft::warning('Transaction with the hash “'.$transactionHash.'” not found.', 'sagepay');
        //     $response->data = 'ok';
        //     return $response;
        // }
        // /** @var Gateway $gateway */
        // $gateway = $this->gateway();
        // /** @var ServerNotifyRequest $request */
        // $request = $gateway->acceptNotification();
        // $request->setTransactionReference($transaction->reference);
        // /** @var ServerNotifyResponse $gatewayResponse */
        // $gatewayResponse = $request->send();
        // if (!$request->isValid()) {
        //     $url = UrlHelper::siteUrl($transaction->getOrder()->cancelUrl);
        //     Craft::warning('Notification request is not valid: '.json_encode($request->getData(), JSON_PRETTY_PRINT), 'sagepay');
        //     $gatewayResponse->invalid($url, 'Invalid signature');
        //     $response->data = 'ok';
        //     return $response;
        // }
        // $request->getData();
        // $status = $request->getTransactionStatus();
        // switch ($status) {
        //     case $request::STATUS_COMPLETED:
        //         $childTransaction->status = TransactionRecord::STATUS_SUCCESS;
        //         break;
        //     case $request::STATUS_PENDING:
        //         $childTransaction->status = TransactionRecord::STATUS_PENDING;
        //         break;
        //     case $request::STATUS_FAILED:
        //         $childTransaction->status = TransactionRecord::STATUS_FAILED;
        //         break;
        // }
        // $childTransaction->response = $gatewayResponse->getData();
        // $childTransaction->code = $gatewayResponse->getCode();
        // $childTransaction->reference = $gatewayResponse->getTransactionReference();
        // $childTransaction->message = $gatewayResponse->getMessage();
        // Commerce::getInstance()->getTransactions()->saveTransaction($childTransaction);
        // $url = UrlHelper::actionUrl('commerce/payments/complete-payment', ['commerceTransactionId' => $childTransaction->id, 'commerceTransactionHash' => $childTransaction->hash]);
        // $gatewayResponse->confirm($url);
        // As of `omnipay-sagepay` version 3.2.2, the `confirm` call above starts output, so prevent Yii from erroring out by trying to send headers or anything, really.
        exit();
	}



	/*public function refund(Transaction $transaction): RequestResponseInterface
	{
		if (!$this->supportsRefund()) {
            throw new NotSupportedException(Craft::t('commerce', 'Refunding is not supported by this gateway'));
        }
        $request = $this->createRequest($transaction);
        $parent= $transaction->getParent();
        if ($parent->type == TransactionRecord::TYPE_CAPTURE) {
            $reference = $parent->getParent()->reference;
        } else {
            $reference = $transaction->reference;
        }
        $refundRequest = $this->prepareRefundRequest($request, $reference);
        return $this->performRequest($refundRequest, $transaction);
	}*/

	public function supportsPaymentSources(): bool
	{
		return false;
	}

	

	// public function getPaymentFormModel(): BasePaymentForm
	// {
	// 	return new AlipayOffsitePaymentForm();
	// }

	// public function getPaymentFormHtml(array $params)
	// {
	// 	try {
	// 		$defaults = [
	// 			'gateway' => $this,
	// 			'paymentForm' => $this->getPaymentFormModel(),
	// 			'paymentMethods' => $this->fetchPaymentMethods(),
	// 			'issuers' => $this-> fetchIssuers(),
	// 		];
	// 	} catch (\Throwable $exception) {
	// 		return parent::getPaymentFormHtml($params);
	// 	}

	// 	$params = array_merge($defaults, $params);

	// 	$view = Craft::$app->getView();

	// 	$previousMode = $view->getTemplateMode();
	// 	$view->setTemplateMode(View::TEMPLATE_MODE_CP);

	// 	$html = $view->renderTemplate('commerce-alipay/paymentForm', $params);
	// 	$view->setTemplateMode($previousMode);

	// 	return $html;
	// }



	// Protected Methods
	// =========================================================================

	protected function createGateway(): AbstractGateway
	{
		$gateway = static::createOmnipayGateway($this->getGatewayClassName());

		$gateway->setPartner(Craft::parseEnv($this->partner));
		$gateway->setKey(Craft::parseEnv($this->key));
		$gateway->setReturnUrl(UrlHelper::actionUrl('commerce/payments/complete-payment'));
		$gateway->setNotifyUrl($this->getWebhookUrl());
		if ($this->testMode) {
			$gateway->setEnvironment('sandbox');
		}

        return $gateway;
	}

	protected function getGatewayClassName()
	{
		return '\\' . OmnipayGateway::class;
	}


	// protected function prepareResponse(ResponseInterface $response, Transaction $transaction): RequestResponseInterface
	// {
	// 	return new RequestResponse($response, $transaction);
	// }

    
}
