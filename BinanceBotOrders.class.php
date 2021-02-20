<?php

namespace BinanceBot;

class BinanceBotOrders
{
   private $_api = null;
   private $_prices = null;
   private $_smsGateway = null;

   private $orders = array();

   public function __construct( $arrs )
   {
      $this->_api = $arrs[1];
      $this->_prices = $arrs[2];
      $this->_smsGateway = $arrs[3];

      if( $arrs[0] == true )
      {
         @unlink( BinanceBotSettings::getInstance()->cacheOrdersFile );
      }

      $this->update();
   }

   public function update( $fresh = false )
   {
      if( $fresh == true )
      {
         @unlink( BinanceBotSettings::getInstance()->cacheOrdersFile );
      }

      $this->getAllOrders();
   }

   private function save()
   {
      file_put_contents( BinanceBotSettings::getInstance()->cacheOrdersFile, serialize( $this->orders ) );
   }

   private function load()
   {
      $this->orders = unserialize( file_get_contents( BinanceBotSettings::getInstance()->cacheOrdersFile ) );
   }

   private function getAllOrders()
   {
      if( file_exists( BinanceBotSettings::getInstance()->cacheOrdersFile ) )
      {
         $this->load();
         return;
      }

      print "\n";

      $this->orders = array();

      $numSymbols = count( $this->_prices->getAllPrices() );
      $curnumSymbol = 1;

      foreach( $this->_prices->getAllPrices() as $symbol => $data )
      {
         if( substr( $symbol, strlen( BinanceBotSettings::getInstance()->base_currency ) * -1 ) != BinanceBotSettings::getInstance()->base_currency )
         {
            $curnumSymbol++;
            continue;
         }

         print " Getting orders [$curnumSymbol/$numSymbols] " . $symbol . " ";
         $orders = $this->_api->orders( $symbol );
         foreach( $orders as $ordernum => $orderdetails )
         {
            if( isset( $orderdetails[ 'status' ] ) == false ) continue;
            $this->orders[$symbol][] = $orderdetails;
         }

         print "\r";
         print "\033[K";
         $curnumSymbol++;
      }

      $this->save();

      echo "\r\033[K\033[1A\r\033[K\r";
      echo "\r\033[K\033[1A\r\033[K\r";
      echo "\r\033[K\033[1A\r\033[K\r";
   }

   private function getAllOpenOrders()
   {
      $orders = array();

      foreach( $this->orders as $keysymbol => $valueorder )
      {
         foreach( $valueorder as $ordernum => $ordervalue )
         {
            if( $ordervalue[ 'status' ] == "NEW" )
            {
               $orders[] = $ordervalue;
            }
         }
      }

      return $orders;
   }

   private function getAllOpenOrdersBySymbol( $symbol )
   {
      return array_values(
         array_filter(
            $this->getAllOpenOrders(),
            function( $v ) use ( $symbol )
            {
               return $v['symbol'] == $symbol;
            }
         )
      );
   }

   public function getAllOpenBuyOrders()
   {
      return array_values(
         array_filter(
            $this->getAllOpenOrders(),
            function( $v )
            {
               return $v['side'] == 'BUY';
            }
         )
      );
   }

   public function getAllOpenSellOrders()
   {
      return array_values(
         array_filter(
            $this->getAllOpenOrders(),
            function( $v )
            {
               return $v['side'] == 'SELL';
            }
         )
      );
   }

   public function getAllBuyOrders( $symbol )
   {
      return array_values(
         array_filter(
            $this->orders[ $symbol ],
            function( $v )
            {
               return $v['side'] == 'BUY';
            }
         )
      );
   }

   public function getAllSellOrders( $symbol )
   {
      return array_values(
         array_filter(
            $this->orders[ $symbol ],
            function( $v )
            {
               return $v['side'] == 'SELL';
            }
         )
      );
   }

   public function getAllOpenBuyOrdersBySymbol( $symbol )
   {
      return array_values(
         array_filter(
            $this->getAllOpenOrdersBySymbol( $symbol ),
            function( $v )
            {
               return $v['side'] == 'BUY';
            }
         )
      );
   }

   public function getAllOpenSellOrdersBySymbol( $symbol )
   {
      return array_values(
         array_filter(
            $this->getAllOpenOrdersBySymbol( $symbol ),
            function( $v )
            {
               return $v['side'] == 'SELL';
            }
         )
      );
   }

   public function cancelBuyOrder( $symbol, $orderid )
   {
      print " Cancel buy order\n";

      // cancel order if great than 50% change
      $response = $this->_api->cancel( $symbol, $orderid );
      print_r( $response );

      $this->orders[$symbol] = array();

      $orders = $this->_api->orders( $symbol );
      foreach( $orders as $ordernum => $orderdetails )
      {
         if( isset( $orderdetails[ 'status' ] ) == false ) continue;
         $this->orders[$symbol][] = $orderdetails;
      }

      $this->save();

      @unlink( BinanceBotSettings::getInstance()->cacheBalancesFile );

      $this->_smsGateway->send( "Cancelled: $symbol - $orderid" );

      return 1;
   }

   public function placeBuyOrder( $symbol, $price, $quantity )
   {
      print " Place buy order\n";

      // open order already
      if( count( $this->getAllOpenBuyOrdersBySymbol( $symbol ) ) > 0 ) return 0;

      $response = $this->_api->buy( $symbol, $quantity, $price );
      print_r( $response );

      $orders = array_reverse( $this->_api->openOrders( $symbol ) );
      $this->orders[ $symbol ][] = $orders[0];
      $this->save();

      @unlink( BinanceBotSettings::getInstance()->cacheBalancesFile );

      $this->_smsGateway->send( "Buy Order: $symbol - $price - $quantity" );

      return 1;
   }

   public function placeSellOrder( $symbol, $price, $quantity )
   {
      print " Place sell order\n";

      // open order already
      if( count( $this->getAllOpenSellOrdersBySymbol( $symbol ) ) > 0 ) return 0;

      $exchangeInfo = $this->_api->exchangeInfo();
      $exchangeInfoSymbols = $exchangeInfo['symbols'];

      foreach( $exchangeInfoSymbols as $exchangeInfoSymbol )
      {
         if( $exchangeInfoSymbol['symbol'] == $symbol )
         {
            print_r( $exchangeInfoSymbol );
         }
      }

      $response = $this->_api->sell( $symbol, $quantity, $price );
      print_r( $response );

      $orders = array_reverse( $this->_api->openOrders( $symbol ) );
      $this->orders[ $symbol ][] = $orders[0];
      $this->save();

      @unlink( BinanceBotSettings::getInstance()->cacheBalancesFile );

      $this->_smsGateway->send( "Sell Order: $symbol - $price - $quantity" );

      return 1;
   }

   public function getBuyOrderTotals()
   {
      $cum_TotalPriceBTC = 0;
      $cum_TotalPriceUSD = 0;
      $cum_ChangePriceUSD = 0;

      foreach( $this->getAllOpenBuyOrders() as $order )
      {
         $cum_TotalPriceBTC += $order['price'] * $order['origQty'];
         $cum_TotalPriceUSD += ($order['price'] * $order['origQty']) * BinanceBotPrices::getBTCUSD();

         $orderTotalUsd = round($order['price'] * $order['origQty'] * BinanceBotPrices::getBTCUSD(),2);
         $currentTotalUsd = round($this->_prices->getPrice( $order['symbol'] ) * $order['origQty'] * BinanceBotPrices::getBTCUSD(),2);
         $cum_ChangePriceUSD += $orderTotalUsd - $currentTotalUsd;
      }

      return array( "", $cum_TotalPriceBTC, round( $cum_TotalPriceUSD, 2 ), $cum_ChangePriceUSD );
   }

   public function getSellOrderTotals()
   {
      $cum_TotalPriceBTC = 0;
      $cum_TotalPriceUSD = 0;
      $cum_ChangePriceUSD = 0;

      foreach( $this->getAllOpenSellOrders() as $order )
      {
         $cum_TotalPriceBTC += $order['price'] * $order['origQty'];
         $cum_TotalPriceUSD += ($order['price'] * $order['origQty']) * BinanceBotPrices::getBTCUSD();

         $orderTotalUsd = round($order['price'] * $order['origQty'] * BinanceBotPrices::getBTCUSD(),2);
         $currentTotalUsd = round($this->_prices->getPrice( $order['symbol'] ) * $order['origQty'] * BinanceBotPrices::getBTCUSD(),2);
         $cum_ChangePriceUSD += $orderTotalUsd - $currentTotalUsd;
      }

      return array( "", $cum_TotalPriceBTC, round( $cum_TotalPriceUSD, 2 ), $cum_ChangePriceUSD );
   }

   public function calculateOrderSummaryBTCEarnings()
   {
      $cumbuy = 0;
      $cumsell = 0;
      $buyfilled = 0;
      $sellfilled = 0;

      foreach($this->orders as $symbol => $orderarr )
      {
         $buy = 0;
         $sell = 0;
         foreach($orderarr as $odernum => $orderdetails )
         {
            if( $orderdetails['side'] == "BUY" && $orderdetails['status'] == "FILLED" )
            {
               $buy += $orderdetails[ 'price' ] * $orderdetails['executedQty'];
               $buyfilled += 1;
            }
            if( $orderdetails['side'] == "SELL" && $orderdetails['status'] == "FILLED" )
            {
               $sell += $orderdetails[ 'price' ] * $orderdetails['executedQty'];
               $sellfilled += 1;
            }
         }
         //printf( "%10s - % 11.8f\n", $symbol, ( $sell - $buy ) );
         $cumbuy += $buy;
         $cumsell += $sell;
      }

      return array( $cumbuy, $cumsell, $buyfilled, $sellfilled );
   }
}
