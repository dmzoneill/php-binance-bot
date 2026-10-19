<?php

namespace BinanceBot;

/*
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
      $this->api = $arr[0];
      $this->data[ 'symbol' ] = $arr[1];
      $this->data[ 'interval' ] = $arr[2];
      $this->data[ 'intervaltime' ] = $arr[3];
   }

   public function run()
   {
      $this->synchronized( function()
      {
         $this->data[ 'data' ] = (object) $this->api->candlesticks( $this->data[ 'symbol' ] ,
             $this->data[ 'interval' ] );
         print ".";
         $this->data[ 'isdone' ] = true;
      }, $this );
   }
}
*/

class BinanceBotCandles
{
    private mixed $api = null;
    private mixed $prices = null;
    private mixed $orders = null;
    private mixed $candles = null;
    private mixed $retracements = array();
    private mixed $lastLargest = 0;
    private bool $invalidRetracements = false;

    public function __construct(mixed $arrs)
    {
        $this->api = $arrs[1];
        $this->prices = $arrs[2];
        $this->orders = $arrs[4];

        if ($arrs[0] == true) {
            @unlink(BinanceBotSettings::getInstance()->cacheCandlesFile);
        }

        if (file_exists(BinanceBotSettings::getInstance()->cacheCandlesFile)) {
            $this->load();
        }

        $this->update();
    }

    public function update(): bool
    {
        if ($this->getDueForUpdate() == false) {
            return false;
        }

        if ($this->updateNext(true, false, false) == true) {
            if ($this->updateNext(false, true, false) == true) {
               /*if( $this->updateNext( false, false, true ) == true )
               {

               }*/
                if ($this->invalidRetracements == true) {
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

    public function getDueForUpdate(mixed $print = true): bool
    {
        /** @var array<string, mixed> $priceMap */
        $priceMap = $this->prices->getAllPrices();
        $symbols = array_keys($priceMap);

        $count = 0;

        foreach ($symbols as $symbol) {
            if (
                substr($symbol, strlen(BinanceBotSettings::getInstance()->base_currency) * -1)
                != BinanceBotSettings::getInstance()->base_currency
            ) {
                continue;
            }
            if (isset($this->candles[ $symbol ]) == false) {
                $count += 3;
                continue;
            }
            if ($this->candles[ $symbol ][ 'lastupdate3m' ] < time() - 900) {
                $count++;
            }
            if ($this->candles[ $symbol ][ 'lastupdate15m' ] < time() - 1800) {
                $count++;
            }
            if ($this->candles[ $symbol ][ 'lastupdate1h' ] < time() - 3600) {
                $count++;
            }
        }

        if ($count > 0 && $print == true) {
            print " Candle updates remaining " . $count . " ";
        }

        return $count > 0;
    }

    private function updateNext(
        bool $buySymbolOpenOrders = true,
        bool $buySymbolNonOpenOrders = false,
        bool $otherSymbols = false
    ): bool {
        /** @var array<string, mixed> $priceMap */
        $priceMap = $this->prices->getAllPrices();
        $symbols = array_keys($priceMap);

        shuffle($symbols);

        $intervals = array( "3m" => array( 900, 30 ), "15m" => array( 1800, 160 ), "1h" => array( 3600, 600 ) );

        foreach ($symbols as $symbol) {
            $hasopenbuyorders = count($this->orders->getAllOpenBuyOrdersBySymbol($symbol)) > 0 ? true : false;
            $hasopensellorders = count($this->orders->getAllOpenSellOrdersBySymbol($symbol)) > 0 ? true : false;

            if ($buySymbolOpenOrders == true) {
                if (
                    substr($symbol, strlen(BinanceBotSettings::getInstance()->base_currency) * -1)
                    != BinanceBotSettings::getInstance()->base_currency
                ) {
                    continue;
                }
                if ($hasopenbuyorders == false && $hasopensellorders == false) {
                    continue;
                }
            }

            if ($buySymbolNonOpenOrders == true) {
                if (
                    substr($symbol, strlen(BinanceBotSettings::getInstance()->base_currency) * -1)
                    != BinanceBotSettings::getInstance()->base_currency
                ) {
                    continue;
                }
                if ($hasopenbuyorders == true || $hasopensellorders == true) {
                    continue;
                }
            }

            if ($otherSymbols == true) {
                if (
                    substr($symbol, strlen(BinanceBotSettings::getInstance()->base_currency) * -1)
                    == BinanceBotSettings::getInstance()->base_currency
                ) {
                    continue;
                }
            }

            if (isset($this->candles[ $symbol ]) == false) {
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

            foreach ($intervals as $interval => $intervaltime) {
                if ($this->candles[ $symbol ][ 'lastupdate' . $interval ] < time() - $intervaltime[0]) {
                    $data = $this->api->candlesticks($symbol, $interval);
                    $this->candles[ $symbol ][ $interval ] = $data;
                    $this->candles[ $symbol ][ 'lastupdate' . $interval ] = time() + rand(1, $intervaltime[0]);
                    $this->candles[ $symbol ][ 'lastupdate' . $interval . 'Trend' ] = $this->calcTrend(
                        $symbol,
                        $interval
                    );
                    $this->candles[ $symbol ][ $interval . 'RetracementPercent' ] = $this->calcRetracement(
                        $symbol,
                        $interval
                    );
                    $encoded = json_encode($this->candles[ $symbol ][ $interval ]);
                    if (is_string($encoded)) {
                        $this->api->addToTransfered(strlen($encoded));
                    }
                    print(".");
                    $this->invalidRetracements = true;
                }
            }
        }

        return true;
    }

    /**
     */
    private function closeDiffs(mixed $candles): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = array_values($candles);
        $diff = array();

        for ($i = 1; $i < count($rows); $i++) {
            $diff[] = $rows[$i]['close'] - $rows[$i - 1]['close'];
        }

        return $diff;
    }

    private function calcTrend(mixed $symbol, string $interval): int
    {
        $diff = $this->closeDiffs($this->candles[ $symbol ][ $interval ]);

        $positive = 0;
        $negative = 0;

        foreach ($diff as $d) {
            if ($d == 0) {
                continue;
            }
            if ($d < 0) {
                $negative += $d;
            }
            if ($d > 0) {
                $positive += $d;
            }
        }

       //printf( "positive % 12.8f\n", $positive );
       //printf( "negative % 12.8f\n", $negative );
       //printf( "diff     % 12.8f\n", $negative + $positive );
        if ($negative + $positive == 0) {
            return 0;
        }
        return ( $negative + $positive ) > 0 ? 1 : -1;
    }

    /**
     *
     */
    private function calcRetracement(mixed $symbol, string $interval): mixed
    {
        $diff = $this->closeDiffs($this->candles[ $symbol ][ $interval ]);

        $largest = 0;
        $smallest = 99999999;

        foreach ($diff as $d) {
            if ($d > $largest) {
                $largest = $d;
            }
            if ($d < $smallest) {
                $smallest = $d;
            }
        }

        return ( $smallest / $largest ) * 100;
    }

    private function save(): void
    {
        file_put_contents(BinanceBotSettings::getInstance()->cacheCandlesFile, serialize($this->candles));
    }

    private function load(): void
    {
        $raw = file_get_contents(BinanceBotSettings::getInstance()->cacheCandlesFile);
        if (is_string($raw)) {
            $this->candles = unserialize($raw);
        }
    }

    /**
     */
    public function getTrend(mixed $symbol, string $interval): mixed
    {
        return isset($this->candles[ $symbol ][ "lastupdate" . $interval . "Trend" ])
            ? $this->candles[ $symbol ][ "lastupdate" . $interval . "Trend" ] : -2;
    }

    public function getTrendRetracement(mixed $symbol, mixed $interval): mixed
    {
        return isset($this->candles[ $symbol ][ $interval . "RetracementPercent" ])
            ? $this->candles[ $symbol ][ $interval . "RetracementPercent" ] : 100;
    }

    /**
     */
    private function largestRetracement(mixed $sb, string $int): mixed
    {
        $this->lastLargest = 0;

        $tc = $this->candles[$sb][$int];

        if (is_object($tc)) {
            unset($this->candles[$sb][$int]);
            return 1;
        }

        $diff = $this->closeDiffs($tc);

        $largest = 0;
        $smallest = 9999999999;

        foreach ($diff as $d) {
            if ($d == 0) {
                continue;
            }
            if ($d > $largest) {
                $largest = $d;
            }
            if ($d < $smallest && $d != 0) {
                $smallest = $d;
            }
        }

        $this->lastLargest = $largest;
        return ( $smallest / $largest ) * 100;
    }

    /**
     * @return (mixed)[]
     *
     */
    public function getBestStocks(): array
    {
        if (count($this->retracements) > 0) {
            return array_reverse($this->retracements);
        }

        $ignored_coins = BinanceBotSettings::getInstance()->ignoredCoinPairs();

        if (BinanceBotSettings::getInstance()->debug) {
            print(__FUNCTION__ . ":" . __LINE__ . " ignored coins:\n");
            print_r($ignored_coins);
        }

        /** @var array<string, mixed> $candleBook */
        $candleBook = is_array($this->candles) ? $this->candles : array();
        foreach (array_keys($candleBook) as $symbol) {
            if (count($ignored_coins) > 0) {
                if (in_array($symbol, $ignored_coins)) {
                    if (BinanceBotSettings::getInstance()->debug) {
                        print(__FUNCTION__ . ":" . __LINE__ . " ignored coin $symbol \n");
                    }
                    continue;
                }
            }

            if (
                substr($symbol, strlen(BinanceBotSettings::getInstance()->base_currency) * -1)
                != BinanceBotSettings::getInstance()->base_currency
            ) {
                continue;
            }

            $m1 = $this->largestRetracement($symbol, "3m");
            $m2 = $this->largestRetracement($symbol, "15m");
            $m3 = $this->largestRetracement($symbol, "1h");

            if ($this->lastLargest < 0.0001 && ( !is_nan($m1) && !is_nan($m2) && !is_nan($m3) )) {
                $cumTrend = $this->getTrend($symbol, "3m") + $this->getTrend($symbol, "15m") + $this->getTrend(
                    $symbol,
                    "1h"
                );
                $this->retracements[ $symbol ] = array( $cumTrend, $m1 + $m2 + $m3, $m1, $m2, $m3 );
            }
        }

        uasort($this->retracements, function ($a, $b) {
            $c  = $a[ 0 ] - $b[ 0 ];
            $c .= $a[ 1 ] - $b[ 1 ];
            return intval($c);
        });

        return array_reverse($this->retracements);
    }

    /**
     * @return (int|mixed)[]
     *
     */
    public function getSymbolLowHighAvgAtInterval(mixed $sb, mixed $int): array
    {
        if (!is_array($this->candles) || !isset($this->candles[$sb])) {
            print_r(is_array($this->candles) ? array_keys($this->candles) : array());
            return array( 0, 0, 0 );
        }

        $tc = $this->candles[$sb][$int];
        $tempcandles = is_array($tc) ? array_values($tc) : array();

        $largest = 0;
        $smallest = 9999999999;
        $total  = 0;

        for ($i = 1; $i < count($tempcandles); $i++) {
            $candle = $tempcandles[$i];
            if (!is_array($candle) || !isset($candle['close'])) {
                continue;
            }
            if ($candle['close'] > $largest) {
                $largest = $candle['close'];
            }
            if ($candle['close'] < $smallest && $candle['close'] != 0) {
                $smallest = $candle['close'];
            }

            $total += $candle['close'];
        }

        return array( $smallest, $largest, $total / count($tempcandles) );
    }
}
