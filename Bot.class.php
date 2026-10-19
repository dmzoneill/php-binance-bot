<?php

namespace BinanceBot;

class Bot
{
    /** @var string */
    private $apiKey = "";

    /** @var string */
    private $priKey = "";

    /** @var mixed */
    protected $api = null;

    /** @var mixed */
    protected $BinanceBotPrices = null;

    /** @var mixed */
    protected $BinanceBotOrders = null;

    /** @var mixed */
    protected $BinanceBotHoldings = null;

    /** @var mixed */
    protected $BinanceBotPrinter = null;

    /** @var mixed */
    protected $BinanceBotCandles = null;

    /** @var mixed */
    protected $BinanceBotSMSGateway = null;

    /** @var mixed */
    protected $BinanceBotTransactionStrategy = null;

    /**
     * @return void
     */
    public function __construct()
    {
        @mkdir(BinanceBotSettings::getInstance()->cachedir);

        $this->apiKey = BinanceBotSettings::getInstance()->apikey;
        $this->priKey = BinanceBotSettings::getInstance()->prikey;

        $deleteCache = $this->doCachedRun();
        $this->api = new \Binance\API($this->apiKey, $this->priKey);

        $smsgateway = BinanceBotSettings::getInstance()->smsGateway;
        if ($smsgateway === 'EirSMS') {
            $this->BinanceBotSMSGateway = new EirSMS();
        }

        $this->BinanceBotPrices = new BinanceBotPrices(
            array($deleteCache, $this->api)
        );
        $this->BinanceBotOrders = new BinanceBotOrders(
            array($deleteCache, $this->api, $this->BinanceBotPrices, $this->BinanceBotSMSGateway)
        );
        $this->BinanceBotHoldings = new BinanceBotHoldings(
            array($deleteCache, $this->api, $this->BinanceBotPrices, $this->BinanceBotSMSGateway)
        );
        $this->BinanceBotCandles = new BinanceBotCandles(
            array(
                false,
                $this->api,
                $this->BinanceBotPrices,
                $this->BinanceBotHoldings,
                $this->BinanceBotOrders,
            )
        );
        $this->BinanceBotPrinter = new BinanceBotPrinter(
            array(
                $this->api,
                $this->BinanceBotHoldings,
                $this->BinanceBotOrders,
                $this->BinanceBotPrices,
                $this->BinanceBotCandles,
            )
        );

        $strategy = BinanceBotSettings::getInstance()->strategy;
        $strategyArgs = array(
            $this->api,
            $this->BinanceBotHoldings,
            $this->BinanceBotOrders,
            $this->BinanceBotPrices,
            $this->BinanceBotCandles,
        );
        if ($strategy === 'BasicTransactionStrategy') {
            $this->BinanceBotTransactionStrategy = new BasicTransactionStrategy($strategyArgs);
        } else {
            $this->BinanceBotTransactionStrategy = new PeriodBasedTransactionStrategy($strategyArgs);
        }
    }

    /**
     * @return bool
     */
    public function doCachedRun()
    {
        $lastrun = time();
        $lastRunFile = BinanceBotSettings::getInstance()->lastRunFile;

        if (file_exists($lastRunFile)) {
            $contents = file_get_contents($lastRunFile);
            if (is_string($contents)) {
                $lastrun = $contents;
            }
        }

        file_put_contents($lastRunFile, strval($lastrun));

        $timeout = BinanceBotSettings::getInstance()->cache_tmeout_seconds;
        if ($lastrun > time() - $timeout) {
            unlink($lastRunFile);
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    protected function shouldContinue()
    {
        return true;
    }

    /**
     * @return void
     */
    public function run()
    {
        $i = 1;
        $updating = false;

        while ($this->shouldContinue()) {
            BinanceBotSettings::getInstance()->update();
            $this->BinanceBotPrices->update();
            $this->BinanceBotHoldings->update();
            $changed = $this->BinanceBotHoldings->getHoldingsChanged();
            $this->BinanceBotOrders->update($changed);
            $this->BinanceBotPrinter->update();
            $this->BinanceBotTransactionStrategy->limitOrders("BUY");
            $this->BinanceBotTransactionStrategy->limitOrders("SELL");

            $due = $this->BinanceBotCandles->getDueForUpdate(false);
            if (($i % 1800 == 0 || $updating == true) && $due == true) {
                $this->BinanceBotCandles->update();
                $updating = $this->BinanceBotCandles->getDueForUpdate(false);
            }

            $i++;

            sleep(1);
        }
    }
}
