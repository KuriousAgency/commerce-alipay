<?php
/**
 * Commerce Alipay plugin for Craft CMS 3.x
 *
 * Alipay integration for Craft Commerce
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2019 Kurious Agency
 */

namespace kuriousagency\commerce\alipay;

use kuriousagency\commerce\alipay\gateways\Gateway;

use Craft;
use craft\base\Plugin;
use craft\commerce\services\Gateways;
use craft\events\RegisterComponentTypesEvent;

use yii\base\Event;

/**
 * Class CommerceAlipay
 *
 * @author    Kurious Agency
 * @package   CommerceAlipay
 * @since     1.0.0
 *
 */
class Alipay extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var CommerceAlipay
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $schemaVersion = '1.0.0';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
		self::$plugin = $this;
		
		Event::on(
			Gateways::class,
			Gateways::EVENT_REGISTER_GATEWAY_TYPES,
			function(RegisterComponentTypesEvent $event) {
				$event->types[] = Gateway::class;
			}
		);


        Craft::info(
            Craft::t(
                'commerce-alipay',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    // Protected Methods
    // =========================================================================

}
