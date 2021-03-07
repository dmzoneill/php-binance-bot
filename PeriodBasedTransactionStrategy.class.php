<?php

namespace BinanceBot;

class PeriodBasedTransactionStrategy extends TransactionStrategy
{
   public function __construct( $arrs )
   {
      parent::__construct( $arrs );
   }

   private function cancelAdditionalBuyOrders()
   {
      $orders = $this->BinanceBotOrders->getAllOpenBuyOrders();

      usort( $orders, function ($a, $b)
      {
         $a_orderTotal = $a['price'] * $a['origQty'];
         $a_currentTotal = $this->BinanceBotPrices->getPrice( $a['symbol'] ) * $a['origQty'];
         $a_diff = $a_orderTotal - $a_currentTotal;
         $a_diffpercent = round( ( abs( $a_diff ) / $a_orderTotal ) * 100 );

         $b_orderTotal = $b['price'] * $b['origQty'];
         $b_currentTotal = $this->BinanceBotPrices->getPrice( $b['symbol'] ) * $b['origQty'];
         $b_diff = $b_orderTotal - $b_currentTotal;
         $b_diffpercent = round( ( abs( $b_diff ) / $b_orderTotal ) * 100 );

         return $a_diffpercent < $b_diffpercent;
      });

      for( $i = 0; $i < count( $orders ) -1 - BinanceBotSettings::getInstance()->max_open_buy_orders; $i++ )
      {
         if(BinanceBotSettings::getInstance()->test == false) {
            try { 
               $res = $this->BinanceBotOrders->cancelBuyOrder( $orders[$i]['symbol'], $orders[$i][ 'orderId' ] );
               if(BinanceBotSettings::getInstance()->debug) {
                  print("Cancel buy order result:\n");
                  print_r($res);
               }
            } 
            catch (Exception $e) {
               print("Error: " . $e . "\n");
            }
         }
      }
   }

   public function placeBuyOrder()
   {
      $weightedStocks = $this->BinanceBotCandles->getBestStocks();
      $weightedStocksKeys = array_keys( $weightedStocks );
      $delim = count( $weightedStocks ) > BinanceBotSettings::getInstance()->max_open_buy_orders * 2 ? BinanceBotSettings::getInstance()->max_open_buy_orders * 2 : count( $weightedStocks );
      $pennyStocks = array();

      for( $t = 0; $t < $delim; $t++ )
      {
         $gotSymbol = 0;
         $symbol = $weightedStocksKeys[ $t ];

         if( $this->BinanceBotPrices->getPrice( $symbol ) < BinanceBotSettings::getInstance()->max_unit_price )
         {
            if( $this->api->prevDay( $symbol )[ 'priceChangePercent' ] < BinanceBotSettings::getInstance()->downward_trigger_percent )
            {
               if( count( $this->BinanceBotOrders->getAllOpenSellOrdersBySymbol( $symbol ) ) > 0 )
               {
                  continue;
               }
               if( count( $this->BinanceBotOrders->getAllOpenBuyOrdersBySymbol( $symbol ) ) > 0 )
               {
                  continue;
               }

               $gotSymbol = $this->BinanceBotHoldings->getHoldings( substr( $symbol, 0, strlen( BinanceBotSettings::getInstance()->base_currency ) * -1 ) );

               if( $gotSymbol != false )
               {
                  if( $gotSymbol > BinanceBotSettings::getInstance()->ignore_less_than_amount_dollars )
                  {
                     continue;
                  }
               }

               $pennyStocks[ $symbol ] = $this->BinanceBotPrices->getPrice( $symbol );
            }
         }
      }

      // no stocks meet criteria
      if( count( $pennyStocks ) == 0 )
      {
         $this->BinanceBotPrices->forceupdate();
         return 0;
      }

      $buysymbol = array_rand( $pennyStocks );

      if(BinanceBotSettings::getInstance()->debug) {
         print("Penny stocks\n");
         print_r( $pennyStocks );
      }

      list( $symbolPeriodLow, $symbolPeriodHigh, $symbolPeriodAvg ) = $this->BinanceBotCandles->getSymbolLowHighAvgAtInterval( $buysymbol, BinanceBotSettings::getInstance()->investment_period );

      if( $symbolPeriodLow == 0 && $symbolPeriodHigh == 0 && $symbolPeriodAvg == 0 ) return;

      $avgByBuyPercent = $symbolPeriodAvg * BinanceBotSettings::getInstance()->buy_at_percent;

      $buy_price = ( $avgByBuyPercent < $symbolPeriodLow ) ? $symbolPeriodLow : $avgByBuyPercent;

      $quantity = round( BinanceBotSettings::getInstance()->buy_total_amount / $buy_price );

      if(BinanceBotSettings::getInstance()->test == false) { 
         try {
            $response = $this->BinanceBotOrders->placeBuyOrder( $buysymbol, sprintf( "%f", $buy_price ), $quantity );
            if(BinanceBotSettings::getInstance()->debug) {
               print("Buy order result:\n");
               print_r($response);
            }
         } 
         catch (Exception $e) {
            print("Error: " . $e . "\n");
         }
      }
      
      printf( "\nPeriod low: %f, high: %f, average: %f, sought: %f\n", $symbolPeriodLow, $symbolPeriodHigh, $symbolPeriodAvg, $avgByBuyPercent );
      printf( "Buy %s of %s @ %f\n", $quantity, $buysymbol, $buy_price );
   }

   public function placeSellOrder()
   {
      if(BinanceBotSettings::getInstance()->debug) {
         print("Place sell order:\n");
      }

      $numorders = 0;

      foreach( $this->BinanceBotHoldings->getBalances() as $symbol => $data )
      {
         if( $symbol == BinanceBotSettings::getInstance()->base_currency ) continue;

         $openOrders = $this->BinanceBotOrders->getAllOpenSellOrdersBySymbol( $symbol . BinanceBotSettings::getInstance()->base_currency );

         if( count( $openOrders ) > 0 ) continue; 

         if( $data[ 'available' ] >= 1.00 ) // 1.00
         {
            if(BinanceBotSettings::getInstance()->debug) {
               print("$symbol available = " . $data[ 'available' ] ."\n");
            }  

            $orders = array_reverse( $this->BinanceBotOrders->getAllBuyOrders( $symbol . BinanceBotSettings::getInstance()->base_currency) );

            if(BinanceBotSettings::getInstance()->debug) {
               print("getAllBuyOrders $symbol\n");
               print_r($orders);
            }

            foreach( $orders as $ordernum => $orderdetails )
            {
               if( isset( $orderdetails[ 'status' ] ) == false ) continue;

               list( $symbolPeriodLow, $symbolPeriodHigh, $symbolPeriodAvg ) = $this->BinanceBotCandles->getSymbolLowHighAvgAtInterval( $symbol . BinanceBotSettings::getInstance()->base_currency, BinanceBotSettings::getInstance()->investment_period );

               if( $symbolPeriodLow == 0 && $symbolPeriodHigh == 0 && $symbolPeriodAvg == 0 ) break;

               $avgBySellPercent = $symbolPeriodAvg * BinanceBotSettings::getInstance()->sell_at_percent;

               printf( "Period low: %f, high: %f, average: %f, sought: %f\n", $symbolPeriodLow, $symbolPeriodHigh, $symbolPeriodAvg, $avgBySellPercent );

               $sell_price = ( $avgBySellPercent > $symbolPeriodHigh ) ? $symbolPeriodHigh : $avgBySellPercent;
               $sell_price = ( $sell_price < $orderdetails[ 'price' ] ) ? $orderdetails[ 'price' ] * BinanceBotSettings::getInstance()->sell_at_percent : $sell_price;

               print $symbol . BinanceBotSettings::getInstance()->base_currency . " " . floor( $data[ 'available' ] ) . " * " . sprintf( "%f", $sell_price ) ."\n";
               printf( $symbol . BinanceBotSettings::getInstance()->base_currency . " %f \n", ( floor( $data[ 'available' ] ) * $sell_price ) );

               if(BinanceBotSettings::getInstance()->test == false) { 
                  try {
                     $response = $this->BinanceBotOrders->placeSellOrder( $symbol . BinanceBotSettings::getInstance()->base_currency, sprintf( "%f", $sell_price ), floor( $data[ 'available' ] ) );
                     if(BinanceBotSettings::getInstance()->debug) {
                        print("Sell order result:n");
                        print_r($response);
                     }
                  } 
                  catch (Exception $e) {
                     print("Error: " . $e . "\n");
                  }
               }

               printf( "Sell %s @ %f\n", $symbol, $sell_price );
               $numorders++;
               break;
            }
         }
      }

      return $numorders;
   }

   public function LimitOrders( $side )
   {
      $exchange_rate = BinanceBotPrices::getBTCUSD();
      $numOrders = 0;

      foreach( $this->BinanceBotPrices->getAllPrices() as $symbol => $price )
      {
         $orders = ( $side == "SELL" ) ? $this->BinanceBotOrders->getAllOpenSellOrdersBySymbol( $symbol ) : $this->BinanceBotOrders->getAllOpenBuyOrdersBySymbol( $symbol );

         foreach( $orders as $ordernum => $orderdetails )
         {
            $PriceBTC = $orderdetails['price'];
            $PriceUSD = $exchange_rate * $PriceBTC;
            $TotalPriceBTC = $PriceBTC * $orderdetails['origQty'];
            $TotalPriceUSD = $exchange_rate * ( $PriceBTC * $orderdetails['origQty']);
            $TotalPriceUSDCurrent = $exchange_rate * ( $this->BinanceBotPrices->getPrice( $symbol ) * $orderdetails['origQty'] );
            $TotalPriceUSDDifferencePercent = round( ( $TotalPriceUSD / $TotalPriceUSDCurrent ) * 100 , 2 );

            $Spread = ( $orderdetails['side'] == "SELL" ) ?
                        $this->getSellSpread( $symbol, $TotalPriceUSD, $orderdetails['origQty'] ) :
                        $this->getBuySpread( $symbol, $TotalPriceUSD, $orderdetails['origQty'] );

            if( $side == "BUY" && ( $TotalPriceUSDDifferencePercent < BinanceBotSettings::getInstance()->cancel_buy_at_lower_percent || $TotalPriceUSDDifferencePercent > BinanceBotSettings::getInstance()->cancel_buy_at_upper_percent ) )
            {
               if(BinanceBotSettings::getInstance()->test == false) { 
                  try {
                     $response = $this->BinanceBotOrders->cancelBuyOrder( $symbol, $orderdetails[ 'orderId' ] );
                     if(BinanceBotSettings::getInstance()->debug) {
                        print("Cancel buy order result:\n");
                        print_r($response);
                     }
                  } 
                  catch (Exception $e) {
                     print("Error: " . $e . "\n");
                  }
               }
            }

            $numOrders++;
         }
      }

      if( $side == "BUY" && $numOrders < BinanceBotSettings::getInstance()->max_open_buy_orders )
      {
         if( $this->BinanceBotCandles->update() == true )
         {
            return;
         }
      }

      if( $side == "SELL" && $numOrders < BinanceBotSettings::getInstance()->max_open_sell_orders )
      {
         if( $this->BinanceBotCandles->update() == true )
         {
            return;
         }
      }

      if( $side == "BUY" )
      {
         if( $this->BinanceBotHoldings->getBaseCurrencyAvailable() < BinanceBotSettings::getInstance()->reserve_amount )
         {
            return;
         }

         while( $numOrders < BinanceBotSettings::getInstance()->max_open_buy_orders )
         {
            $norders = $this->placeBuyOrder();
            $numOrders++;
            $numOrders = ( $norders == 0 ) ? BinanceBotSettings::getInstance()->max_open_buy_orders : $numOrders;
         }

         if( $numOrders > BinanceBotSettings::getInstance()->max_open_buy_orders )
         {
            $this->cancelAdditionalBuyOrders();
         }
      }
      else
      {
         while( $numOrders < BinanceBotSettings::getInstance()->max_open_sell_orders )
         {
            $norders = $this->placeSellOrder();
            $numOrders++;
            $numOrders = ( $norders == 0 ) ? BinanceBotSettings::getInstance()->max_open_sell_orders : $numOrders;
         }
      }
   }
}
