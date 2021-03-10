<?php

namespace BinanceBot;

class BinanceBotPrinter
{
   private $holdingsMaskTitle = "| %4s | %10s | %20s | %20s | %16s | %8s | %16s | %8s | %8s |";
   private $holdingsMask = "| %4s | %10s | % 20.11f | % 20.11f | % 16.11f | % 8.2f | % 16.11f | % 8.2f | % 8.2f |";
   private $holdingsTotalMask = "%70s % 12.11f   %8.2f   %16.11f   %8.2f   %8.2f";
   private $holdingsTotalLine = "";

   private $ordersMaskTitle = "| %4s | %10s | %6s | %7s | %7s | %14s | %14s | %10s | %8s | %7s | %7s | %5s | %3s | %3s | %3s |";
   private $ordersMask = "| %4s | %10s | %6s | %7s | %7d | % 14.10f | % 14.10f | % 10.4f | % 8.2f | % 7.2f | % 7.2f | %5s | T1%3s\e[49m | T2%3s\e[49m | T3%3s\e[49m |";
   private $ordersTotalMask = "%68s  % 11.10f                % 8.2f  % 8.2f";

   private $outputLines = array();
   private $_api = null;
   private $_holdings = null;
   private $_orders = null;
   private $_prices = null;
   private $_candles = null;

   public function __construct( $arrs )
   {
      $this->_api = $arrs[0];
      $this->_holdings = $arrs[1];
      $this->_orders = $arrs[2];
      $this->_prices = $arrs[3];
      $this->_candles = $arrs[4];

      $this->holdingsTotalLine = str_repeat("-", 136);
      $this->ordersTotalLine = str_repeat("-", 152);
   }

   private function clear()
   {
      for($i = 0; $i < count( $this->outputLines ) + 7; $i++)
      {
         echo "\r\033[K\033[1A\r\033[K\r";
      }

      $this->outputLines = array();
   }

   private function printLines()
   {
      for($i = 0; $i < count( $this->outputLines ); $i++)
      {
         print $this->outputLines[$i] . "\n";
      }
   }

   private function addLine( $line )
   {
      $this->outputLines[] = " " . $line;
   }

   private function printHeading()
   {
      list( $cumbuy, $cumsell, $buyfilled, $sellfilled ) = $this->_orders->calculateOrderSummaryBTCEarnings();

      $exchangeline = "Exchange Rate BTC = $" . BinanceBotPrices::getBTCUSD();
      $exchangeline .= " - €" . BinanceBotPrices::getBTCEUR() . " - " . sha1( time() );

      $exchangelineBase = "Exchange Rate " . BinanceBotSettings::getInstance()->base_currency . " = $" . BinanceBotPrices::getBaseCurrencyUSD();
      $exchangelineBase .= " - €" . BinanceBotPrices::getBaseCurrencyEUR();

      $orderStats = "Max Buy/Sell orders = " . BinanceBotSettings::getInstance()->max_open_buy_orders . "/" . BinanceBotSettings::getInstance()->max_open_sell_orders;
      $orderStats .= sprintf( " - Accumulated: % 10.8f " . BinanceBotSettings::getInstance()->base_currency . " ( $% 5.2f )", ( $cumsell - $cumbuy ), ( $cumsell - $cumbuy ) * BinanceBotPrices::getBaseCurrencyUSD() );

      $apiStats = "Api requests: " . $this->_api->getRequestCount(). ", total of " . $this->_api->getTransfered();
      $apiStats .= sprintf( " - Bought: %s, Sold: %s\n", $buyfilled, $sellfilled );

      $this->addLine( $exchangeline );
      $this->addLine( $exchangelineBase );
      $this->addLine( $orderStats );
      $this->addLine( $apiStats );
   }

   public function printHoldings()
   {
      $this->addLine( "Holdings" );
      $this->addLine( " " . $this->holdingsTotalLine );
      $this->addLine( sprintf( $this->holdingsMaskTitle, '#', 'Symbol', 'Available', 'OnOrder', 'btcValue', 'usdValue', 'btcTotal', 'usdTotal', 'eurTotal' ) );
      $this->addLine( " " . $this->holdingsTotalLine );

      $numid = 1;
      foreach( $this->_holdings->getWallet() as $coin )
      {
         $copy = $coin;
         array_unshift( $copy, $numid );
         $this->addLine( vsprintf($this->holdingsMask, $copy) );
         $numid++;
      }

      $this->addLine( " " . $this->holdingsTotalLine );
      $this->addLine( sprintf( $this->holdingsTotalMask, "", $this->_holdings->getCumBTCValue(), $this->_holdings->getCumUSDValue(), $this->_holdings->getCumBTCTotal(), $this->_holdings->getCumUSDTotal(), $this->_holdings->getCumEURTotal() ) );
   }

   public function printBuyOrders()
   {
      $this->addLine( "Buy Limits Orders" );
      $this->addLine( " " . $this->ordersTotalLine );
      $this->addLine( sprintf($this->ordersMaskTitle, '#', 'Symbol', 'Side', 'Type', 'Q', BinanceBotSettings::getInstance()->base_currency . '/Unit', 'Total' . BinanceBotSettings::getInstance()->base_currency, 'USD/Unit', 'TotalUSD', 'CTotal', 'C/Unit', 'C%', '3m' ,'15m', '1hr' ) );
      $this->addLine( " " . $this->ordersTotalLine );

      $numid = 1;
      foreach( $this->_orders->getAllOpenBuyOrders() as $order )
      {
         $orderTotalUsd = round($order['price'] * $order['origQty'] * BinanceBotPrices::getBaseCurrencyUSD(),2);
         $currentTotalUsd = round($this->_prices->getPrice( $order['symbol'] ) * $order['origQty'] * BinanceBotPrices::getBaseCurrencyUSD(),2);
         $diff = $orderTotalUsd - $currentTotalUsd;
         $diffunit = $order['origQty'] < 1 ? $diff : round( $diff / $order['origQty'], 3);
         $diffpercent = round( ( abs( $diff ) / $orderTotalUsd ) * 100 );

         $mask = $this->ordersMask;
         $mask = preg_replace( "/T1/", $this->formatTrendColor( $this->_candles->getTrend( $order['symbol'], "3m" ) ), $mask );
         $mask = preg_replace( "/T2/", $this->formatTrendColor( $this->_candles->getTrend( $order['symbol'], "15m" ) ), $mask );
         $mask = preg_replace( "/T3/", $this->formatTrendColor( $this->_candles->getTrend( $order['symbol'], "1h" ) ), $mask );

         $this->addLine( sprintf($mask,
                        $numid,
                        $order['symbol'],
                        $order['side'],
                        $order['type'],
                        round($order['origQty'],3),
                        $order['price'],
                        $order['price'] * $order['origQty'],
                        round($order['price'] * BinanceBotPrices::getBaseCurrencyUSD(), 2),
                        $orderTotalUsd,
                        round( $diff, 2),
                        $diffunit,
                        $diffpercent . "%",
                        $this->formatTrend( $this->_candles->getTrend( $order['symbol'], "3m" ) ),
                        $this->formatTrend( $this->_candles->getTrend( $order['symbol'], "15m" ) ),
                        $this->formatTrend( $this->_candles->getTrend( $order['symbol'], "1h" ) )
                        ) );

         $numid++;
      }

      $this->addLine( " " . $this->ordersTotalLine );
      $this->addLine( vsprintf($this->ordersTotalMask, $this->_orders->getBuyOrderTotals() ) );
   }

   public function printSellOrders()
   {
      $this->addLine( "Sell Limits Orders" );
      $this->addLine( " " . $this->ordersTotalLine );
      $this->addLine( sprintf($this->ordersMaskTitle, '#', 'Symbol', 'Side', 'Type', 'Q', BinanceBotSettings::getInstance()->base_currency. '/Unit', 'Total' . BinanceBotSettings::getInstance()->base_currency, 'USD/Unit', 'TotalUSD', 'CTotal', 'C/Unit', 'C%', '3m' ,'15m', '1hr' ) );
      $this->addLine( " " . $this->ordersTotalLine );

      $numid = 1;
      foreach( $this->_orders->getAllOpenSellOrders() as $order )
      {
         $orderTotalUsd = round($order['price'] * $order['origQty'] * BinanceBotPrices::getBaseCurrencyUSD(),2);
         $currentTotalUsd = round($this->_prices->getPrice( $order['symbol'] ) * $order['origQty'] * BinanceBotPrices::getBaseCurrencyUSD(),2);
         $diff = $orderTotalUsd - $currentTotalUsd;
         $diffunit = $order['origQty'] < 1 ? $diff : round( $diff / $order['origQty'], 3);
         $diffpercent = round( ( abs( $diff ) / $orderTotalUsd ) * 100 );

         $mask = $this->ordersMask;
         $mask = preg_replace( "/T1/", $this->formatTrendColor( $this->_candles->getTrend( $order['symbol'], "3m" ) ), $mask );
         $mask = preg_replace( "/T2/", $this->formatTrendColor( $this->_candles->getTrend( $order['symbol'], "15m" ) ), $mask );
         $mask = preg_replace( "/T3/", $this->formatTrendColor( $this->_candles->getTrend( $order['symbol'], "1h" ) ), $mask );

         $this->addLine( sprintf($mask,
                        $numid,
                        $order['symbol'],
                        $order['side'],
                        $order['type'],
                        round($order['origQty'],3),
                        $order['price'],
                        $order['price'] * $order['origQty'],
                        round($order['price'] * BinanceBotPrices::getBaseCurrencyUSD(), 2),
                        $orderTotalUsd,
                        round( $diff, 2),
                        $diffunit,
                        $diffpercent . "%",
                        $this->formatTrend( $this->_candles->getTrend( $order['symbol'], "3m" ) ),
                        $this->formatTrend( $this->_candles->getTrend( $order['symbol'], "15m" ) ),
                        $this->formatTrend( $this->_candles->getTrend( $order['symbol'], "1h" ) )
                        ) );
         $numid++;
      }

      $this->addLine( " " . $this->ordersTotalLine );
      $this->addLine( vsprintf($this->ordersTotalMask, $this->_orders->getSellOrderTotals() ) );
   }

   private function formatTrend( $trend )
   {
      switch( $trend )
      {
         case -2:
            return " ";
         break;

         case -1:
            return " ";
         break;

         case 1:
            return " ";
         break;

         default:
            return " ";
         break;
      }
   }

   private function formatTrendColor( $trend )
   {
      switch( $trend )
      {
         case -2:
            return "\e[49m";
         break;

         case -1:
            return "\e[41m";
         break;

         case 1:
            return "\e[42m";
         break;

         default:
            return "\e[49m";
         break;
      }
   }

   public function update()
   {
      $this->clear();
      $this->printHeading();
      $this->printHoldings();
      $this->printBuyOrders();
      $this->printSellOrders();
      $this->printLines();
   }
}
