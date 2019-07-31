<?php
/**
 * Commerce Alipay plugin for Craft CMS 3.x
 *
 * Alipay integration for Craft Commerce
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2019 Kurious Agency
 */

namespace kuriousagency\commerce\alipay\responses;

use Craft;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\errors\NotImplementedException;

class PaymentResponse implements RequestResponseInterface
{
    /**
     * @var
     */
    protected $data = [];
    /**
     * @var string
     */
    private $_redirect = '';
    /**
     * @var bool
     */
    private $_processing = false;
    /**
     * Response constructor.
     *
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }
    public function setRedirectUrl(string $url)
    {
        $this->_redirect = $url;
    }
    public function setProcessing(bool $status)
    {
        $this->_processing = $status;
    }
    /**
     * @inheritdoc
     */
    public function isSuccessful(): bool
    {
		if (isset($this->data['trade_status'])) {
			return $this->data['trade_status'] == 'TRADE_FINISHED' || 
					$this->data['trade_status'] == 'TRADE_SUCCESS' ||
					$this->data['trade_status'] == 'TRADE_CLOSED';
		}

        return false;
    }
    /**
     * @inheritdoc
     */
    public function isProcessing(): bool
    {
        return $this->_processing;
    }
    /**
     * @inheritdoc
     */
    public function isRedirect(): bool
    {
        return !empty($this->_redirect);
    }
    /**
     * @inheritdoc
     */
    public function getRedirectMethod(): string
    {
        return 'GET';
    }
    /**
     * @inheritdoc
     */
    public function getRedirectData(): array
    {
        return $this->data;
    }
    /**
     * @inheritdoc
     */
    public function getRedirectUrl(): string
    {
		return $this->_redirect;
    }
    /**
     * @inheritdoc
     */
    public function getTransactionReference(): string
    {
        return $this->transactionValue('trade_no');
    }
    /**
     * @inheritdoc
     */
    public function getCode(): string
    {
        return $this->transactionValue('trade_status');
    }
    /**
     * @inheritdoc
     */
    public function getData()
    {
        return $this->data;
    }
    /**
     * @inheritdoc
     */
    public function getMessage(): string
    {
		if (isset($this->data['message']) && $this->data['message']) {
			//return $this->data->message;
			return Craft::t('commerce','There was an issue with your payment method, please check your details and try again. If the issue persists you will need to contact your card provider.');
		}

		return '';
    }
    /**
     * @inheritdoc
     */
    public function redirect()
    {

	}
	

	private function transactionValue($key)
	{
		if (isset($this->data[$key])) {
			return $this->data[$key];
		}

		return '';
	}
}