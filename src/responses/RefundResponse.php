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

class RefundResponse implements RequestResponseInterface
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
		if (isset($this->data['is_success'])) {
			return $this->data['is_success'] == 'T';
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
        return '';
    }
    /**
     * @inheritdoc
     */
    public function getRedirectUrl(): string
    {
		return '';
    }
    /**
     * @inheritdoc
     */
    public function getTransactionReference(): string
    {
        return '';
    }
    /**
     * @inheritdoc
     */
    public function getCode(): string
    {
        return '';
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
		if (isset($this->data['error'])) {
			return $this->data['error'];
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