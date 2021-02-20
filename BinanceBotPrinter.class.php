<?php

namespace BinanceBot;

class BinanceBotPrinter
{
   private $holdingsMask = "| %2.2s | %8.8s | %9.9s | %7.7s | %10.10s | % 8.2f | %10.10s | % 8.2f | % 8.2f |";
   private $holdingsMaskTitle = "| %2.2s | %8.8s | %9.9s | %7.7s | %10.10s | %8.8s | %10.10s | %8.8s | %8.8s |";
   private $holdingsTotalMask = "%37.37s %12.12s   %8.8s   %10.10s   %8.8s   %8.8s";
   private $holdingsTotalLine = "";

   private $ordersMaskTitle = "| %2.2s | %8.8s | %4.4s | %5.5s | %5.5s | %10.10s | %10.10s | %8.8s | %8.8s | %6.6s | %6.6s | %3.3s | %2.2s | %3.3s | %3.3s |";
   private $ordersMask = "| %2.2s | %8.8s | %4.4s | %5.5s | %5.5s | % 10.8f | % 10.8f | % 8.2f | % 8.2f | % 6.2f | % 6.2f | %3.3s | T1%2.2s\e[49m | T2%3.3s\e[49m | T3%3.3s\e[49m |";
   private $ordersTotalMask = "%52.52s  % 10.8f              % 8.2f % 8.2f";

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

      $this->holdingsTotalLine = str_repeat("-", 96);
      $this->ordersTotalLine = str_repeat("-", 127);
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

      $exchangeline = "Exchange Rate = $" . BinanceBotPrices::getBTCUSD();
      $exchangeline .= " - €" . BinanceBotPrices::getBTCEUR() . " - " . sha1( time() );

      $orderStats = "Max Buy/Sell orders = " . BinanceBotSettings::getInstance()->max_open_buy_orders . "/" . BinanceBotSettings::getInstance()->max_open_sell_orders;
      $orderStats .= sprintf( " - Accumulated: % 10.8f btc ( $% 5.2f )", ( $cumsell - $cumbuy ), ( $cumsell - $cumbuy ) * BinanceBotPrices::getBTCUSD() );

      $apiStats = "Api requests: " . $this->_api->getRequestCount(). ", total of " . $this->_api->getTransfered();
      $apiStats .= sprintf( " - Bought: %s, Sold: %s\n", $buyfilled, $sellfilled );

      $this->addLine( $exchangeline );
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
      $this->addLine( sprintf($this->ordersMaskTitle, '#', 'Symbol', 'Side', 'Type', 'Q', 'BTC/Unit', 'TotalBTC', 'USD/Unit', 'TotalUSD', 'CTotal', 'C/Unit', 'C%', '3m' ,'15m', '1hr' ) );
      $this->addLine( " " . $this->ordersTotalLine );

      $numid = 1;
      foreach( $this->_orders->getAllOpenBuyOrders() as $order )
      {
         $orderTotalUsd = round($order['price'] * $order['origQty'] * BinanceBotPrices::getBTCUSD(),2);
         $currentTotalUsd = round($this->_prices->getPrice( $order['symbol'] ) * $order['origQty'] * BinanceBotPrices::getBTCUSD(),2);
         $diff = $orderTotalUsd - $currentTotalUsd;
         $diffunit = $order['origQty'] < 1 ? $diff : round( $diff / $order['origQty'], 2);
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
                        round($order['price'] * BinanceBotPrices::getBTCUSD(), 2),
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
      $this->addLine( sprintf($this->ordersMaskTitle, '#', 'Symbol', 'Side', 'Type', 'Q', 'BTC/Unit', 'TotalBTC', 'USD/Unit', 'TotalUSD', 'CTotal', 'C/Unit', 'C%', '3m' ,'15m', '1hr' ) );
      $this->addLine( " " . $this->ordersTotalLine );

      $numid = 1;
      foreach( $this->_orders->getAllOpenSellOrders() as $order )
      {
         $orderTotalUsd = round($order['price'] * $order['origQty'] * BinanceBotPrices::getBTCUSD(),2);
         $currentTotalUsd = round($this->_prices->getPrice( $order['symbol'] ) * $order['origQty'] * BinanceBotPrices::getBTCUSD(),2);
         $diff = $orderTotalUsd - $currentTotalUsd;
         $diffunit = $order['origQty'] < 1 ? $diff : round( $diff / $order['origQty'], 2);
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
                        round($order['price'] * BinanceBotPrices::getBTCUSD(), 2),
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
