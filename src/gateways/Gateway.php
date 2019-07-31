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

use kuriousagency\commerce\alipay\Alipay;
use kuriousagency\commerce\alipay\models\PaymentForm;
use kuriousagency\commerce\alipay\responses\PaymentResponse;
use kuriousagency\commerce\alipay\responses\RefundResponse;

use Craft;
use craft\commerce\Plugin as Commerce;
use craft\commerce\base\Gateway as BaseGateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\errors\PaymentException;
use craft\commerce\errors\TransactionException;
use craft\commerce\errors\PaymentSourceException;
use craft\commerce\models\Currency;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Transaction;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\helpers\UrlHelper;
use craft\web\Response as WebResponse;
use craft\web\View;
use craft\db\Query;
use craft\db\Command;
use yii\base\Exception;
use yii\base\NotSupportedException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;


/**
 * @author    Kurious Agency
 * @package   CommerceAlipay
 * @since     1.0.0
 */
class Gateway extends BaseGateway
{
    // Public Properties
	// =========================================================================
	
	public $partner;

	public $key;

	public $testMode = false;


	private $gateway;


	public function init()
	{
		parent::init();

	}

	public static function displayName(): string
	{
		return Craft::t('commerce', 'Alipay');
	}

	public function getSettingsHtml()
	{
		return Craft::$app->getView()->renderTemplate('commerce-alipay/gatewaySettings', ['gateway' => $this]);
	}

	public function getBaseUrl()
	{
		return $this->testMode ?  'https://mapi.alipaydev.com/gateway.do' : 'https://mapi.alipay.com/gateway.do';
	}


	
	public function populateRequest(array &$request, BasePaymentForm $paymentForm = null)
	{
		/*parent::populateRequest($request, $paymentForm);
		
		$request['type'] = 'redirect';
		$request['out_trade_no'] = $request['transactionId'];
		$request['subject'] = $request['order']->number;
		$request['total_fee'] = $request['amount'];*/
	}

	public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
	{

	}

	public function completeAuthorize(Transaction $transaction): RequestResponseInterface
	{

	}

	public function capture(Transaction $transaction, string $reference): RequestResponseInterface
	{

	}

	public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
	{
		try {

			$order = $transaction->getOrder();

			$data = [
				'service' => 'create_forex_trade',
				'partner' => Craft::parseEnv($this->partner),
				'notify_url' => $this->getWebhookUrl(['commerceTransactionId' => $transaction->id, 'commerceTransactionHash' => $transaction->hash]),
				'return_url' => UrlHelper::actionUrl('commerce/payments/complete-payment', ['commerceTransactionId' => $transaction->id, 'commerceTransactionHash' => $transaction->hash]),
				'_input_charset' => 'utf-8',
				'product_code' => 'NEW_OVERSEAS_SELLER',
				'out_trade_no' => $transaction->hash,
				'currency' => $transaction->currency,
				'subject' => $order->number,
				'total_fee' => $transaction->paymentAmount,
			];

			$signed = $this->signRequest($data);

			$data['sign'] = $signed;
			$data['sign_type'] = 'MD5';

			$response = new PaymentResponse($data);
			$response->setRedirectUrl($this->getRedirectUrl($data));

			return $response;

			//Craft::dd($result);
			//Craft::$app->getRequest()->redirect($result);

			//return new PaymentResponse($result);

		} catch (\Exception $exception) {
			throw $exception;
		}
	}

	public function completePurchase(Transaction $transaction): RequestResponseInterface
	{
		if (!$this->supportsCompletePurchase()) {
			throw new NotSupportedException(Craft::t('commerce', 'Completing purchase is not supported by this gateway'));
		}

		// out_trade_no=d7c1bcfe77c59531c56012c30300b505&
		// total_fee=46.50&
		// trade_status=TRADE_FINISHED&
		// sign=cf732ed8c670d5ec64e68cf20e20205c&
		// trade_no=2019073022001362641000050013&
		// currency=USD&
		// sign_type=MD5

		$request = Craft::$app->getRequest();
		$params = $request->getQueryParams();
		//Craft::dd($params);
		unset($params['p']);
		unset($params['commerceTransactionHash']);
		unset($params['sign_type']);
		unset($params['sign']);

		$signed = $this->signRequest($params);

		if ($signed != $request->getParam('sign')) {
			// failed.
		}

		$transactionHash = $request->getParam('commerceTransactionHash');
		$transaction = Commerce::getInstance()->getTransactions()->getTransactionByHash($transactionHash);

		$childTransaction = Commerce::getInstance()->getTransactions()->createTransaction(null, $transaction);
        $childTransaction->type = $transaction->type;
        if (!$transaction) {
            Craft::warning('Transaction with the hash “'.$transactionHash.'” not found.', 'alipay');
            //$response->data = 'ok';
            //return $response;
		}

		$response = new PaymentResponse($params);
		if ($request->getParam('trade_status') == 'WAIT_BUYER_PAY') {
			$response->setProcessing($request->getParam('trade_status'));
		}

		return $response;
	}

	public function refund(Transaction $transaction): RequestResponseInterface
	{
		if (!$this->supportsRefund()) {
            throw new NotSupportedException(Craft::t('commerce', 'Refunding is not supported by this gateway'));
		}

		//Craft::dd($transaction->getParent());

		$data = [
			'service' => 'forex_refund',
			'partner' => Craft::parseEnv($this->partner),
			'product_code' => 'NEW_OVERSEAS_SELLER',
			'out_return_no' => $transaction->hash,
			'out_trade_no' => $transaction->getParent()->getParent()->hash,
			'currency' => $transaction->currency,
			'return_amount' => $transaction->amount,
			'reason' => $transaction->note,
			'is_sync' => 'Y',
			'_input_charset' => 'utf-8',
		];

		$signed = $this->signRequest($data);

		$data['sign'] = $signed;
		$data['sign_type'] = 'MD5';

		//Craft::dd($data);
		
		try {
			$client = new Client();
			$url = $this->getBaseUrl();
			$response = $client->request('POST', $url, [
				'form_params' => $data
			]);
			$result = simplexml_load_string($response->getBody(), 'SimpleXMLElement', LIBXML_NOCDATA);
			$result = json_decode(json_encode($result), true);
			//Craft::dd($result);

			return new RefundResponse($result);

		} catch (\Exception $exception) {
			throw $exception;
		}
	}

	public function createPaymentSource(BasePaymentForm $sourceData, int $userId): PaymentSource
	{

	}

	public function deletePaymentSource($token): bool
	{
		
	}

	// public function getWebhookUrl(array $params = []): string
	// {
	// 	return UrlHelper::actionUrl('commerce/payments/complete-payment', $params);
	// }

	public function processWebHook(): WebResponse
	{
		$response = Craft::$app->getResponse();
		$request = Craft::$app->getRequest();
		$transactionHash = $request->post('commerceTransactionHash');
		$transaction = Commerce::getInstance()->getTransactions()->getTransactionByHash($transactionHash);
		Craft::info($transactionHash, 'commerce-alipay');
		Alipay::log($transactionHash, LogLevel::Info, true);

		$childTransaction = Commerce::getInstance()->getTransactions()->createTransaction(null, $transaction);
		$childTransaction->type = $transaction->type;
		if (!$transaction) {
			Craft::warning('Transaction with the hash “'.$transactionHash.'” not found.', 'alipay');
			$response->data = 'fail';
			return $response;
		}

		switch ($request->post('trade_status')) {
			case 'TRADE_FINISHED':
			case 'TRADE_SUCCESS':
				$childTransaction->status = TransactionRecord::STATUS_SUCCESS;
				break;
			case 'WAIT_BUYER_PAY':
				$childTransaction->status = TransactionRecord::STATUS_PENDING;
				break;
			default:
				$childTransaction->status = TransactionRecord::STATUS_FAILED;
				break;
		}

		$childTransaction->response = $request->post();
		$childTransaction->code = $request->post('trade_status');
		$childTransaction->reference = $request->post('out_trade_no');
		Commerce::getInstance()->getTransactions()->saveTransaction($childTransaction);

		$response->data = 'success';
		return $response;

		return Craft::$app->end();
	}


	public function supportsAuthorize(): bool
	{
		return false;
	}

	public function supportsCapture(): bool
	{
		return false;
	}

	public function supportsCompleteAuthorize(): bool
	{
		return false;
	}

	public function supportsCompletePurchase(): bool
	{
		return true;
	}

	public function supportsPaymentSources(): bool
	{
		return false;
	}

	public function supportsPurchase(): bool
	{
		return true;
	}

	public function supportsRefund(): bool
	{
		return true;
	}

	public function supportsPartialRefund(): bool
	{
		return true;
	}

	public function supportsWebhooks(): bool
	{		
		return true;
	}

	public function supportsPlanSwitch(): bool
    {
        return false;
	}
	
	public function supportsReactivation(): bool
    {
        return false;
    }

	

	public function getPaymentFormModel(): BasePaymentForm
	{
		return new PaymentForm();
	}

	public function getPaymentFormHtml(array $params)
	{
		// try {
		// 	$defaults = [
		// 		'gateway' => $this,
		// 		'paymentForm' => $this->getPaymentFormModel(),
		// 		'paymentMethods' => $this->fetchPaymentMethods(),
		// 		'issuers' => $this-> fetchIssuers(),
		// 	];
		// } catch (\Throwable $exception) {
		// 	return parent::getPaymentFormHtml($params);
		// }

		// $params = array_merge($defaults, $params);

		// $view = Craft::$app->getView();

		// $previousMode = $view->getTemplateMode();
		// $view->setTemplateMode(View::TEMPLATE_MODE_CP);

		// $html = $view->renderTemplate('commerce-alipay/paymentForm', $params);
		// $view->setTemplateMode($previousMode);

		// return $html;
	}



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

	private function getRedirectUrl($data=[])
	{
		$url = $this->getBaseUrl();

		if (count($data)) {
			$url.='?'.http_build_query($data);
		}
		return $url;
	}

	private function signRequest(array $data)
	{
		$key = Craft::parseEnv($this->key);

		ksort($data);
		reset($data);

		$params = urldecode(http_build_query($data));
		$sign = md5($params.$key);

		return $sign;
	}

	// private function request(array $data, $transaction)
	// {
	// 	$partner = Craft::parseEnv($this->partner);
	// 	$key = Craft::parseEnv($this->key);

	// 	$url = $this->testMode ?  'https://openapi.alipaydev.com/gateway.do' : 'https://mapi.alipay.com/gateway.do';
		
	// 	//$client = new Client();

	// 	// commerceTransactionHash
	// 	// ['commerceTransactionId' => $childTransaction->id, 'commerceTransactionHash' => $childTransaction->hash]
	// 	$data['service'] = 'create_forex_trade';
	// 	$data['partner'] = $partner;
	// 	$data['notify_url'] = $this->getWebhookUrl(['commerceTransactionId' => $transaction->id, 'commerceTransactionHash' => $transaction->hash]);
	// 	$data['return_url'] = UrlHelper::actionUrl('commerce/payments/complete-payment', ['commerceTransactionId' => $transaction->id, 'commerceTransactionHash' => $transaction->hash]);
	// 	$data['_input_charset'] = 'utf-8';
	// 	$data['product_code'] = 'NEW_OVERSEAS_SELLER';

	// 	$sorted = $data;
	// 	//Craft::dd($sorted);

	// 	ksort($sorted);
	// 	reset($sorted);
	// 	//Craft::dd($data);

    //     $params = urldecode(http_build_query($sorted));
	// 	$sign = md5($params.$key);

	// 	$data['sign'] = $sign;
	// 	$data['sign_type'] = 'MD5';

	// 	$response = new PaymentResponse($data);
	// 	$response->setRedirectUrl($url);

	// 	return $response;

	// 	return $url.'?'.http_build_query($data);

	// 	try {
	// 		Craft::dump($data);
	// 		$response = $client->request('GET', $url, [
	// 			'query' => $data
	// 		]);
	// 		Craft::dd($response);

	// 	} catch (\Exception $e) {
	// 		return $e->getMessage();
	// 	}
	// }

    
}
