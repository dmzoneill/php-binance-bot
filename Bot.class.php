<?php

require 'bi-api.php';
require 'BinanceBotOrders.class.php';
require 'BinanceBotHoldings.class.php';
require 'BinanceBotPrinter.class.php';
require 'BinanceBotPrices.class.php';
require 'ITransactionStrategy.php';
require 'TransactionStrategy.class.php';
require 'BasicTransactionStrategy.class.php';
require 'PeriodBasedTransactionStrategy.class.php';
require 'BinanceBotSettings.class.php';
require 'BinanceBotCandles.class.php';
require 'ISMSGateway.php';
require 'sms/EirSMS.class.php';

class Bot
{
   private $apiKey = "";
   private $priKey = "";
   protected $api = null;
   protected $BinanceBotPrices = null;
   protected $BinanceBotOrders = null;
   protected $BinanceBotHoldings = null;
   protected $BinanceBotPrinter = null;
   protected $BinanceBotCandles = null;
   protected $BinanceBotSMSGateway = null;
   protected $BinanceBotTransactionStrategy = null;
   private $lastrunFile = "cache/lastrun.txt";

   public function __construct()
   {
      @mkdir( BinanceBot\BinanceBotSettings::getInstance()->cachedir );

      $this->apiKey = BinanceBot\BinanceBotSettings::getInstance()->apikey;
      $this->priKey = BinanceBot\BinanceBotSettings::getInstance()->prikey;

      $deleteCache = $this->doCachedRun();
      $this->api = new Binance\API( $this->apiKey, $this->priKey );

      $smsgateway = BinanceBot\BinanceBotSettings::getInstance()->smsGateway;
      $class = 'BinanceBot\\'. $smsgateway;
      $this->BinanceBotSMSGateway = new $class();

      $this->BinanceBotPrices = new BinanceBot\BinanceBotPrices( array( $deleteCache, $this->api ) );
      $this->BinanceBotOrders = new BinanceBot\BinanceBotOrders( array( $deleteCache, $this->api, $this->BinanceBotPrices, $this->BinanceBotSMSGateway ) );
      $this->BinanceBotHoldings = new BinanceBot\BinanceBotHoldings( array( $deleteCache, $this->api, $this->BinanceBotPrices, $this->BinanceBotSMSGateway ) );
      $this->BinanceBotCandles = new BinanceBot\BinanceBotCandles( array( false, $this->api, $this->BinanceBotPrices, $this->BinanceBotHoldings, $this->BinanceBotOrders ) );
      $this->BinanceBotPrinter = new BinanceBot\BinanceBotPrinter( array( $this->api, $this->BinanceBotHoldings, $this->BinanceBotOrders, $this->BinanceBotPrices, $this->BinanceBotCandles ) );

      $strategy = BinanceBot\BinanceBotSettings::getInstance()->strategy;
      $class = 'BinanceBot\\'. $strategy;
      $this->BinanceBotTransactionStrategy = new $class( array( $this->api, $this->BinanceBotHoldings, $this->BinanceBotOrders, $this->BinanceBotPrices, $this->BinanceBotCandles ) );
   }

   public function doCachedRun()
   {
      $lastrun = time();

      if( file_exists( BinanceBot\BinanceBotSettings::getInstance()->lastRunFile ) )
      {
         $lastrun = file_get_contents( BinanceBot\BinanceBotSettings::getInstance()->lastRunFile );
      }

      file_put_contents( BinanceBot\BinanceBotSettings::getInstance()->lastRunFile, $lastrun );

      if( $lastrun > time() - BinanceBot\BinanceBotSettings::getInstance()->cache_tmeout_seconds )
      {
         unlink( BinanceBot\BinanceBotSettings::getInstance()->lastRunFile );
         return false;
      }

      return true;
   }

   public function run()
   {
      $i = 1;
      $updating = false;

      while( true )
      {
         BinanceBot\BinanceBotSettings::getInstance()->update();
         $this->BinanceBotPrices->update();
         $this->BinanceBotHoldings->update();
         $this->BinanceBotOrders->update( $this->BinanceBotHoldings->getHoldingsChanged() );
         $this->BinanceBotPrinter->update();
         $this->BinanceBotTransactionStrategy->limitOrders( "BUY" );
         $this->BinanceBotTransactionStrategy->limitOrders( "SELL" );

         if( ( $i % 1800 == 0 || $updating == true ) && $this->BinanceBotCandles->getDueForUpdate( false ) == true )
         {
            $updating = true;
            $this->BinanceBotCandles->update();
            $updating = $this->BinanceBotCandles->getDueForUpdate( false );
         }

         $i++;

         sleep( 1 );
      }
   }
}
