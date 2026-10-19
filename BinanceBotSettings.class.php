<?php

namespace BinanceBot;

/**
 * @property mixed $test
 * @property mixed $debug
 * @property mixed $apikey
 * @property mixed $prikey
 * @property mixed $base_currency
 * @property mixed $strategy
 * @property mixed $max_open_buy_orders
 * @property mixed $max_open_sell_orders
 * @property mixed $buy_at_percent
 * @property mixed $sell_at_percent
 * @property mixed $cancel_buy_at_lower_percent
 * @property mixed $cancel_buy_at_upper_percent
 * @property mixed $investment_period
 * @property mixed $max_unit_price
 * @property mixed $downward_trigger_percent
 * @property mixed $buy_total_amount
 * @property mixed $ignore_less_than_amount_dollars
 * @property mixed $reserve_amount
 * @property mixed $ignored_coins
 * @property mixed $cachedir
 * @property mixed $lastRunFile
 * @property mixed $cacheOrdersFile
 * @property mixed $cacheBalancesFile
 * @property mixed $cachePricesFile
 * @property mixed $cacheCandlesFile
 * @property mixed $baseCurrencyTrackerFile
 * @property mixed $cache_tmeout_seconds
 * @property mixed $smsGateway
 * @property mixed $smsGatewayUsername
 * @property mixed $smsGatewayPassword
 * @property mixed $smsGatewayRecipientNumber
 */
class BinanceBotSettings
{
    private static ?self $instance = null;
    private mixed $data = array();
    private string $configFile = "config.json";

    private int $timeLoaded = 0;

    private function __construct()
    {
        $this->load();
    }

    private function load(): void
    {
        $raw = file_get_contents($this->configFile);
        $decoded = is_string($raw) ? json_decode($raw, true) : array();
        $this->data = is_array($decoded) ? $decoded : array();
        $mtime = filemtime($this->configFile);
        $this->timeLoaded = $mtime === false ? 0 : $mtime;
    }

    private function reload(): void
    {
        clearstatcache();

        if ($this->timeLoaded < filemtime($this->configFile)) {
            print("Settings change detected... \n");
            $this->load();
        }
    }

    public function update(): void
    {
        $this->load();
    }

    public function __get(mixed $variable)
    {
        $this->reload();

        if (isset($this->data[ $variable ])) {
            return $this->data[ $variable ];
        } else {
            print "Unknown variable: $variable\n";
        }
    }

    /**
     * @return string[]
     *
     */
    public function ignoredCoinPairs(): array
    {
        $ignored_coins = [];
        $ignored = $this->__get('ignored_coins');
        $base = $this->__get('base_currency');
        if (!is_string($ignored) || !is_string($base) || $ignored === '') {
            return $ignored_coins;
        }

        if (stristr($ignored, ",") !== false) {
            $temp_ignored_coins = explode(",", $ignored);
        } else {
            $temp_ignored_coins = [$ignored . $base];
        }
        foreach ($temp_ignored_coins as $coin) {
            $ignored_coins[] = $coin . $base;
        }

        return $ignored_coins;
    }

    public static function getInstance(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new BinanceBotSettings();
        }
        return self::$instance;
    }
}
