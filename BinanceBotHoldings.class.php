<?php

namespace BinanceBot;

use \Exception as Exception;

class BinanceBotHoldings
{
   private $_api = null;
   private $_prices = null;
   private $_smsGateway = null;
   private $balances = null;
   private $wallet = array();
   private $holdings = array();
   private $cum_btcvalue = 0;
   private $cum_usdvalue = 0;
   private $cum_btctotal = 0;
   private $cum_usdtotal = 0;
   private $cum_eurtotal = 0;
   private $balancesChanged = false;
   private $balanceUpdateCounter = 0;

   public function __construct( $arrs )
   {
      $this->_api = $arrs[1];
      $this->_prices = $arrs[2];
      $this->_smsGateway = $arrs[3];

      if( $arrs[0] == true )
      {
         @unlink( BinanceBotSettings::getInstance()->cacheBalancesFile );
      }

      $this->update();
   }

   public function update()
   {
      $this->balanceUpdateCounter++;
      if( file_exists( BinanceBotSettings::getInstance()->cacheBalancesFile ) && $this->balanceUpdateCounter % 13 != 0 )
      {
         $this->load();
      }
      else
      {
         try {
            $updated = $this->_api->balances( $this->_prices->getAllPrices() );
            asort( $updated );
            if( $this->balances != null && $updated[ BinanceBotSettings::getInstance()->base_currency ][ 'available' ] != $this->balances[ BinanceBotSettings::getInstance()->base_currency ][ 'available' ] )
            {
               $txt = $this->getBTCTotal();
               $myfile = file_put_contents( BinanceBotSettings::getInstance()->baseCurrencyTrackerFile, $txt.PHP_EOL , FILE_APPEND | LOCK_EX);
               $this->balancesChanged = true;
            }
         } catch(Exception $e){
            print($e);
         }
         $this->balances = $updated;
      }

      $this->updateWallet();
      $this->save();
   }

   public function getHoldingsChanged()
   {
      if( $this->balancesChanged == true )
      {
         $this->_smsGateway->send( "Holdings Changed" );
         $this->balancesChanged = false;
         return true;
      }

      return false;
   }

   private function save()
   {
      file_put_contents( BinanceBotSettings::getInstance()->cacheBalancesFile, serialize( $this->balances ) );
   }

   private function load()
   {
      $this->balances = unserialize( file_get_contents( BinanceBotSettings::getInstance()->cacheBalancesFile ) );
   }

   public function getBalances()
   {
      $ignored_coins = [];
      $temp_ignored_coins = [];
      if(strlen(BinanceBotSettings::getInstance()->ignored_coins) > 0) {
         if(stristr(BinanceBotSettings::getInstance()->ignored_coins, ",")) {
            $temp_ignored_coins = explode(",", BinanceBotSettings::getInstance()->ignored_coins);
         } else {
            $temp_ignored_coins = [BinanceBotSettings::getInstance()->ignored_coins . BinanceBotSettings::getInstance()->base_currency];
         }
         foreach($temp_ignored_coins as $coin) {
            $ignored_coins[] = $coin . BinanceBotSettings::getInstance()->base_currency;
         }
      }

      if(BinanceBotSettings::getInstance()->debug) {
         print(__FUNCTION__ . ":" . __LINE__ . " ignored coins:\n");
         print(implode(",", $ignored_coins) . "\n");
      }

      $temp = [];

      foreach( $this->balances as $symbol => $data )
      {
         if(in_array($symbol, $ignored_coins)) {
            if(BinanceBotSettings::getInstance()->debug) {
               print(__FUNCTION__ . ":" . __LINE__ . " ignored coin $symbol \n");
            }
            continue;
         }
         $temp[$symbol] = $data;
      }

      return $temp;
   }

   public function getHoldings( $symbol )
   {
      if( isset( $this->holdings[ $symbol ] ) )
      {
         //print $symbol . " - " . $this->holdings[ $symbol ] . "\n";
         return $this->holdings[ $symbol ];
      }
      return false;
   }

   public function getWallet()
   {
      return $this->wallet;
   }

   public function getBaseCurrencyAvailable()
   {
      if( !isset( $this->balances[ BinanceBotSettings::getInstance()->base_currency ] ) )
      {
         @unlink( BinanceBotSettings::getInstance()->cacheBalancesFile );
         $this->update();
      }
      return $this->balances[ BinanceBotSettings::getInstance()->base_currency ][ 'available' ];
   }

   public function getBTCTotal()
   {
      $cumbtctotal = 0;

      foreach( $this->balances as $symbol => $data )
      {
         $btctotal = $data['btcTotal'];
         $cumbtctotal += $btctotal;
      }

      return $cumbtctotal;
   }

   private function updateWallet()
   {
      $exchange_rate = $this->_prices->getBTCUSD();
      $exchange_rate_eur = $this->_prices->getBTCEUR();

      $this->cum_btcvalue = 0;
      $this->cum_usdvalue = 0;
      $this->cum_btctotal = 0;
      $this->cum_usdtotal = 0;
      $this->cum_eurtotal = 0;
      $this->wallet = array();
      $this->holdings = array();

      foreach( $this->balances as $symbol => $data )
      {
         if( $data[ 'available' ] )
         {
            $btcvalue = $data['btcValue'];
            $usdvalue = $exchange_rate * $data['btcValue'];
            $btctotal = $data['btcTotal'];
            $usdtotal = $exchange_rate * $data['btcTotal'];
            $eurtotal = $exchange_rate_eur * $data['btcTotal'];

            $this->holdings[ $symbol ] = $usdtotal;
            if( $data['available'] > 0.00000000 || $data['onOrder'] > 0.00000000 )
            {
               $this->wallet[] = array( $symbol, $data['available'], $data['onOrder'], $btcvalue, round( $usdvalue, 2 ), $btctotal, round( $usdtotal, 2 ), round( $eurtotal, 2 ) );
            }
            else
            {
               $other = isset( $this->wallet[ 'other' ] ) ? $this->wallet[ 'other' ] : array( '-OTHER-', 0, 0, 0, 0, 0, 0, 0 );
               $other[1] += $data['available'];
               $other[2] += $data['onOrder'];
               $other[3] += $btcvalue;
               $other[4] += round( $usdvalue, 2 );
               $other[5] += $btctotal;
               $other[6] += round( $usdtotal, 2 );
               $other[7] += round( $eurtotal, 2 );
               $this->wallet[ 'other' ] = $other;
            }

            $this->cum_btcvalue += $btcvalue;
            $this->cum_usdvalue += $usdvalue;
            $this->cum_btctotal += $btctotal;
            $this->cum_usdtotal += $usdtotal;
            $this->cum_eurtotal += $eurtotal;
         }
      }

      usort( $this->wallet, function ($a, $b)
      {
         return $a[6] < $b[6];
      });
   }

   public function getCumBTCValue()
   {
      return $this->cum_btcvalue;
   }

   public function getCumUSDValue()
   {
      return round( $this->cum_usdvalue, 2 );
   }

   public function getCumBTCTotal()
   {
      return $this->cum_btctotal;
   }

   public function getCumUSDTotal()
   {
      return round( $this->cum_usdtotal, 2 );
   }

   public function getCumEURTotal()
   {
      return round( $this->cum_eurtotal, 2 );
   }
}
