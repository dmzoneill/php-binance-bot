<?php

namespace BinanceBot;

class BasicTransactionStrategy extends TransactionStrategy
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
         $this->BinanceBotOrders->cancelBuyOrder( $orders[$i]['symbol'], $orders[$i][ 'orderId' ] );
      }
   }

   public function placeBuyOrder()
   {
      $pennyStocks = array();

      $sorted = $this->BinanceBotPrices->getAllPrices();
      asort( $sorted );

      foreach( $sorted as $symbol => $price )
      {
         if( substr( $symbol, strlen( BinanceBotSettings::getInstance()->base_currency ) * -1 ) != BinanceBotSettings::getInstance()->base_currency ) continue;
         //print "$symbol => $price\n";
         if( count( $this->BinanceBotOrders->getAllOpenBuyOrdersBySymbol( $symbol ) ) > 0 )
         {
            continue;
         }

         if( $price < BinanceBotSettings::getInstance()->max_unit_price_btc )
         {
            if( $this->api->prevDay( $symbol )[ 'priceChangePercent' ] < BinanceBotSettings::getInstance()->downward_trigger_percent )
            {
               $openOrders = $this->BinanceBotOrders->getAllOpenSellOrdersBySymbol( $symbol . BinanceBotSettings::getInstance()->base_currency );
               if( count( $openOrders ) > 0 ) continue;
               
               $retracements = $this->BinanceBotCandles->getBestStocks();
               $retracementskeys = array_keys( $this->BinanceBotCandles->getBestStocks() );
               $delim = count( $retracements ) > BinanceBotSettings::getInstance()->max_open_buy_orders * 2 ? BinanceBotSettings::getInstance()->max_open_buy_orders * 2 : count( $retracements );
               $found = false;

               for( $t = 0; $t < $delim; $t++ )
               {
                  if( $retracementskeys[ $t ] == $symbol )
                  {
                     $found = true;
                     break;
                  }
               }

               if( $found == true )
               {
                  $pennyStocks[ $symbol ] = $price;
               }
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

      // make sure we dont own this stock already
      if( $this->BinanceBotHoldings->getHoldings( $symbol ) != false )
      {
         if( $this->BinanceBotHoldings->getHoldings( $symbol ) > BinanceBotSettings::getInstance()->ignore_less_than_amount_dollars )
         {
            // already own some of this stock, no need to buy more
            print "Already own " . $pennyStocks[ $buysymbol ] . "\n";
            return;
         }
      }

      $quantity = round( BinanceBotSettings::getInstance()->buy_total_amount_btc / ( $pennyStocks[ $buysymbol ] * BinanceBotSettings::getInstance()->buy_at_percent ) );

      $response = $this->BinanceBotOrders->placeBuyOrder( $buysymbol, sprintf( "%f", $pennyStocks[ $buysymbol ] * BinanceBotSettings::getInstance()->buy_at_percent ), $quantity );
      //print_r( $response );
      printf( "Buy %s of %s @ %f\n", $quantity, $buysymbol, ( $pennyStocks[ $buysymbol ] * BinanceBotSettings::getInstance()->buy_at_percent ) );
   }

   public function placeSellOrder()
   {
      $numorders = 0;

      foreach( $this->BinanceBotHoldings->getBalances() as $symbol => $data )
      {
         if( $symbol == BinanceBotSettings::getInstance()->base_currency ) continue;

         $openOrders = $this->BinanceBotOrders->getAllOpenSellOrdersBySymbol( $symbol . BinanceBotSettings::getInstance()->base_currency );
         if( count( $openOrders ) > 0 ) continue;

         if( $data[ 'available' ] >= 1.00 ) // 1.00
         {
            $orders = array_reverse( $this->BinanceBotOrders->getAllBuyOrders( $symbol . BinanceBotSettings::getInstance()->base_currency) );

            foreach( $orders as $ordernum => $orderdetails )
            {
               if( isset( $orderdetails[ 'status' ] ) == false ) continue;

               print $symbol . BinanceBotSettings::getInstance()->base_currency . " " . floor( $data[ 'available' ] ) . " * " . sprintf( "%f", $orderdetails[ 'price' ] * BinanceBotSettings::getInstance()->sell_at_percent ) ."\n";
               printf( $symbol . BinanceBotSettings::getInstance()->base_currency . " %f \n", ( floor( $data[ 'available' ] ) * ( $orderdetails[ 'price' ] * BinanceBotSettings::getInstance()->sell_at_percent ) ) );
               $response = $this->BinanceBotOrders->placeSellOrder( $symbol . BinanceBotSettings::getInstance()->base_currency , sprintf( "%f", $orderdetails[ 'price' ] * BinanceBotSettings::getInstance()->sell_at_percent ), floor( $data[ 'available' ] ) );
               printf( "Sell %s @ %f\n", $symbol, ( $orderdetails[ 'price' ] * BinanceBotSettings::getInstance()->sell_at_percent ) );
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
               $this->BinanceBotOrders->cancelBuyOrder( $symbol, $orderdetails[ 'orderId' ] );
            }

            $numOrders++;
         }
      }

      if( $side == "BUY" )
      {
         if( $this->BinanceBotHoldings->getBTCAvailable() < BinanceBotSettings::getInstance()->btc_reserve_amount )
         {
            return;
         }

         while( $numOrders < BinanceBotSettings::getInstance()->max_open_buy_orders )
         {
            if( $this->BinanceBotCandles->update() == true )
            {
               break;
            }

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
