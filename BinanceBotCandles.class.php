<?php

namespace BinanceBot;

class BinanceBotCandleWorker extends \Thread
{
   private $_api = null;
   private $data = null;

   public function __construct( \Volatile $data )
   {
      $this->data = $data;
   }

   public function setup( $arr )
   {
      $this->_api = $arr[0];
      $this->data[ 'symbol' ] = $arr[1];
      $this->data[ 'interval' ] = $arr[2];
      $this->data[ 'intervaltime' ] = $arr[3];
   }

   public function run()
   {
      $this->synchronized( function()
      {
         $this->data[ 'data' ] = (object) $this->_api->candlesticks( $this->data[ 'symbol' ] , $this->data[ 'interval' ] );
         print ".";
         $this->data[ 'isdone' ] = true;
      }, $this );
   }
}

class BinanceBotCandles
{
   private $_api = null;
   private $_prices = null;
   private $_holdings = null;
   private $_orders = null;
   private $candles = null;
   private $retracements = array();
   private $lastlargest = 0;
   private $invalidRetracements = false;

   public function __construct( $arrs )
   {
      $this->_api = $arrs[1];
      $this->_prices = $arrs[2];
      $this->_holdings = $arrs[3];
      $this->_orders = $arrs[4];

      if( $arrs[0] == true )
      {
         @unlink( BinanceBotSettings::getInstance()->cacheCandlesFile );
      }

      if( file_exists( BinanceBotSettings::getInstance()->cacheCandlesFile ) )
      {
         $this->load();
      }

      $this->update();
   }

   public function update()
   {
      if( $this->getDueForUpdate() == false )
      {
         return false;
      }

      if( $this->updateNext( true, false, false ) == true )
      {
         if( $this->updateNext( false, true, false ) == true )
         {
            /*if( $this->updateNext( false, false, true ) == true )
            {

            }*/
            if( $this->invalidRetracements == true )
            {
               $this->retracements = array();
               $this->getBestStocks();
               print " done with retracements";
               $this->invalidRetracements = false;
            }
         }
      }
      $this->save();

      return true;
   }

   public function getDueForUpdate( $print = true )
   {
      $symbols = array_keys( $this->_prices->getAllPrices() );

      $count = 0;

      foreach( $symbols as $symbol )
      {
         if( substr( $symbol, strlen( BinanceBotSettings::getInstance()->base_currency ) * -1 ) != BinanceBotSettings::getInstance()->base_currency ) continue;
         if( isset( $this->candles[ $symbol ] ) == false ) { $count += 3; continue; }
         if( $this->candles[ $symbol ][ 'lastupdate3m' ] < time() - 900 ) $count++;
         if( $this->candles[ $symbol ][ 'lastupdate15m' ] < time() - 1800 ) $count++;
         if( $this->candles[ $symbol ][ 'lastupdate1h' ] < time() - 3600 ) $count++;
      }

      if( $count > 0 && $print == true )
      {
         print " Candle updates remaining " . $count . " ";
      }

      return $count > 0;
   }

   private function updateNext( $buySymbolOpenOrders = true, $buySymbolNonOpenOrders = false, $otherSymbols = false )
   {
      $symbols = array_keys( $this->_prices->getAllPrices() );

      shuffle( $symbols );

      $maxUpdates = 10;
      $workers = array();
      $volitiles = array();
      $intervals = array( "3m" => array( 900, 30 ), "15m" => array( 1800, 160 ), "1h" => array( 3600, 600 ) );

      foreach( $symbols as $symbol )
      {
         $hasopenbuyorders = count( $this->_orders->getAllOpenBuyOrdersBySymbol( $symbol ) ) > 0 ? true : false;
         $hasopensellorders = count( $this->_orders->getAllOpenSellOrdersBySymbol( $symbol ) ) > 0 ? true : false;

         if( $buySymbolOpenOrders == true )
         {
            if( substr( $symbol, strlen( BinanceBotSettings::getInstance()->base_currency ) * -1 ) != BinanceBotSettings::getInstance()->base_currency ) continue;
            if( $hasopenbuyorders == false && $hasopensellorders == false ) continue;
         }

         if( $buySymbolNonOpenOrders == true )
         {
            if( substr( $symbol, strlen( BinanceBotSettings::getInstance()->base_currency ) * -1 ) != BinanceBotSettings::getInstance()->base_currency ) continue;
            if( $hasopenbuyorders == true || $hasopensellorders == true ) continue;
         }

         if( $otherSymbols == true )
         {
            if( substr( $symbol, strlen( BinanceBotSettings::getInstance()->base_currency ) * -1 ) == BinanceBotSettings::getInstance()->base_currency ) continue;
         }

         if( isset( $this->candles[ $symbol ] ) == false )
         {
            $this->candles[ $symbol ] = array();
            $this->candles[ $symbol ][ '3m' ] = array();
            $this->candles[ $symbol ][ '15m' ] = array();
            $this->candles[ $symbol ][ '1h' ] = array();
            $this->candles[ $symbol ][ 'lastupdate3m' ] = time() - 9999999;
            $this->candles[ $symbol ][ 'lastupdate15m' ] = time() - 9999999;
            $this->candles[ $symbol ][ 'lastupdate1h' ] = time() - 9999999;
            $this->candles[ $symbol ][ '3mRetracementPercent' ] = 0;
            $this->candles[ $symbol ][ '15mRetracementPercent' ] = 0;
            $this->candles[ $symbol ][ '1hRetracementPercent' ] = 0;
         }

         foreach( $intervals as $interval => $intervaltime )
         {
            if( $this->candles[ $symbol ][ 'lastupdate' . $interval ] < time() - $intervaltime[0] )
            {
               $data = new \Volatile;
               $worker = new BinanceBotCandleWorker( $data );
               $worker->setup( array( $this->_api, $symbol, $interval, $intervaltime ) );
               $worker->start();
               $workers[] = $worker;
               $volitiles[] = $data;
               usleep( 500000 );
               $maxUpdates--;
               $this->invalidRetracements = true;
            }
         }

         if( $maxUpdates <= 0 )
         {
            break;
         }
      }

      if( $maxUpdates != 10 )
      {
         $stopper = count( $volitiles );
         while( $stopper > 0 )
         {
            foreach( $volitiles as $volitile )
            {
               if( isset( $volitile[ 'isdone' ] ) )
               {
                  $this->candles[ (string) $volitile->symbol ][ (string) $volitile->interval ] = (array) $volitile->data;
                  $this->candles[ (string) $volitile->symbol ][ 'lastupdate' . (string) $volitile->interval ] = time() + rand( 1, ( (array) $volitile->intervaltime )[0] );
                  $this->candles[ (string) $volitile->symbol ][ 'lastupdate' . (string) $volitile->interval . 'Trend' ] = $this->calcTrend( (string) $volitile->symbol, (string) $volitile->interval );
                  $this->candles[ (string) $volitile->symbol ][ (string) $volitile->interval . 'RetracementPercent' ] = $this->calcRetracement( (string) $volitile->symbol, (string) $volitile->interval );
                  $this->_api->addToTransfered( strlen( json_encode( $this->candles[ (string) $volitile->symbol ][ (string) $volitile->interval ] ) ) );
                  $stopper--;
               }
            }
         }

         return false;
      }

      return true;
   }

   private function calcTrend( $symbol, $interval )
   {
      $temp = array_values( $this->candles[ $symbol ][ $interval ] );

      $diff = array();

      for( $i = 1; $i < count( $temp ); $i++ )
      {
         $diff[] = $temp[$i]['close'] - $temp[$i-1]['close'];
      }

      $positive = 0;
      $negative = 0;

      foreach( $diff as $d )
      {
         if( $d == 0 ) continue;
         if( $d < 0 ) $negative += $d;
         if( $d > 0 ) $positive += $d;
      }

      //printf( "positive % 12.8f\n", $positive );
      //printf( "negative % 12.8f\n", $negative );
      //printf( "diff     % 12.8f\n", $negative + $positive );
      if( $negative + $positive == 0 ) return 0;
      return ( $negative + $positive ) > 0 ? 1 : -1;
   }

   private function calcRetracement( $symbol, $interval )
   {
      $temp = array_values( $this->candles[ $symbol ][ $interval ] );

      $diff = array();

      for( $i = 1; $i < count( $temp ); $i++ )
      {
         $diff[] = $temp[$i]['close'] - $temp[$i-1]['close'];
      }

      $largest = 0;
      $smallest = 99999999;

      foreach( $diff as $d )
      {
         if( $d > $largest ) $largest = $d;
         if( $d < $smallest ) $smallest = $d;
      }

      return ( $smallest / $largest ) * 100;
   }

   private function save()
   {
      file_put_contents( BinanceBotSettings::getInstance()->cacheCandlesFile, serialize( $this->candles ) );
   }

   private function load()
   {
      $this->candles = unserialize( file_get_contents( BinanceBotSettings::getInstance()->cacheCandlesFile ) );
   }

   public function getTrend( $symbol, $interval )
   {
      return isset( $this->candles[ $symbol ][ "lastupdate" . $interval . "Trend" ] ) ? $this->candles[ $symbol ][ "lastupdate" . $interval . "Trend" ] : -2;
   }

   public function getTrendRetracement( $symbol, $interval )
   {
      return isset( $this->candles[ $symbol ][ $interval . "RetracementPercent" ] ) ? $this->candles[ $symbol ][ $interval . "RetracementPercent" ] : 100;
   }

   private function largestRetracement( $sb, $int )
   {
      $this->lastLargest = 0;

      $tc = $this->candles[$sb][$int];

      if( is_object( $tc ) )
      {
         unset( $this->candles[$sb][$int] );
         return 1;
      }

      $keys = array_keys( $tc );
      $tempcandles = array_values( $tc );

      $diff = array();

      for( $i = 1; $i < count( $tempcandles ); $i++ )
      {
         $diff[] = $tempcandles[$i]['close'] - $tempcandles[$i-1]['close'];
      }

      $positive = 0;
      $negative = 0;
      $largest = 0;
      $smallest = 9999999999;

      foreach( $diff as $d )
      {
         if( $d == 0 ) continue;
         if( $d < 0 ) $negative += $d;
         if( $d > 0 ) $positive += $d;
         if( $d > $largest ) $largest = $d;
         if( $d < $smallest && $d != 0 ) $smallest = $d;
      }

      $this->lastLargest = $largest;
      return ( $smallest / $largest ) * 100;
   }

   public function getBestStocks()
   {
      if( count( $this->retracements ) > 0 )
      {
         return array_reverse( $this->retracements );
      }

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
         print_r($ignored_coins);
      }

      foreach( $this->candles as $symbol => $candle )
      {
         if(count($ignored_coins) > 0) {
            if(in_array($symbol, $ignored_coins )) {
               if(BinanceBotSettings::getInstance()->debug) {
                  print(__FUNCTION__ . ":" . __LINE__ . " ignored coin $symbol \n");
               }
               continue;
            }
         }

         if( substr( $symbol, strlen( BinanceBotSettings::getInstance()->base_currency ) * -1 ) != BinanceBotSettings::getInstance()->base_currency ) continue;

         $m1 = $this->largestRetracement( $symbol, "3m" );
         $m2 = $this->largestRetracement( $symbol, "15m" );
         $m3 = $this->largestRetracement( $symbol, "1h" );

         if( $this->lastLargest < 0.0001 && ( !is_nan( $m1 ) && !is_nan( $m3 ) && !is_nan( $m3 ) ) )
         {
            $cumTrend = $this->getTrend( $symbol, "3m" ) + $this->getTrend( $symbol, "15m" ) + $this->getTrend( $symbol, "1h" );
            $this->retracements[ $symbol ] = array( $cumTrend, $m1 + $m2 + $m3, $m1, $m2, $m3 );
         }
      }

      uasort( $this->retracements, function($a,$b)
      {
          $c  = $a[ 0 ] - $b[ 0 ];
          $c .= $a[ 1 ] - $b[ 1 ];
          return $c;
      });

      return array_reverse( $this->retracements );
   }

   public function getSymbolLowHighAvgAtInterval( $sb, $int )
   {
      if( !isset( $this->candles[$sb] ) )
      {
         print_r( array_keys( $this->candles ) );
         return array( 0, 0, 0 );
      }

      $tc = $this->candles[$sb][$int];
      $keys = array_keys( $tc );
      $tempcandles = array_values( $tc );

      $largest = 0;
      $smallest = 9999999999;
      $total  = 0;

      for( $i = 1; $i < count( $tempcandles ); $i++ )
      {
         if( $tempcandles[$i]['close'] > $largest ) $largest = $tempcandles[$i]['close'];
         if( $tempcandles[$i]['close'] < $smallest && $tempcandles[$i]['close'] != 0 ) $smallest = $tempcandles[$i]['close'];

         $total += $tempcandles[$i]['close'];
      }

      return array( $smallest, $largest, $total / count( $tempcandles ) );
   }
}
