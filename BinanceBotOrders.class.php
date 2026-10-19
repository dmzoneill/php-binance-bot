<?php

namespace BinanceBot;

use Exception;

class BinanceBotOrders extends BinanceBotService
{
    private mixed $orders = array();

    #[\Override]
    protected function cacheFile()
    {
        return BinanceBotSettings::getInstance()->cacheOrdersFile;
    }

    #[\Override]
    public function update(mixed $fresh = false)
    {
        if ($fresh == true) {
            @unlink(BinanceBotSettings::getInstance()->cacheOrdersFile);
        }

        $this->getAllOrders();
    }

    private function save(): void
    {
        file_put_contents(BinanceBotSettings::getInstance()->cacheOrdersFile, serialize($this->orders));
    }

    private function load(): void
    {
        $raw = file_get_contents(BinanceBotSettings::getInstance()->cacheOrdersFile);
        if (is_string($raw)) {
            $this->orders = unserialize($raw);
        }
    }

    /**
     * @return void
     */
    private function getAllOrders()
    {
        if (file_exists(BinanceBotSettings::getInstance()->cacheOrdersFile)) {
            $this->load();
            return;
        }

        print "\n";

        $this->orders = array();

        $numSymbols = count($this->prices->getAllPrices());
        $curnumSymbol = 1;

        /** @var array<string, mixed> $priceMap */
        $priceMap = $this->prices->getAllPrices();
        foreach (array_keys($priceMap) as $symbol) {
            if (
                substr($symbol, strlen(BinanceBotSettings::getInstance()->base_currency) * -1)
                != BinanceBotSettings::getInstance()->base_currency
            ) {
                $curnumSymbol++;
                continue;
            }
            try {
                print " Getting orders [$curnumSymbol/$numSymbols] " . $symbol . " ";
                $this->loadSymbolOrders($symbol, false);
            } catch (Exception $e) {
                print($e);
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

    /**
     */
    private function getAllOpenOrders(): array
    {
        $orders = array();

        foreach ($this->orders as $valueorder) {
            foreach ($valueorder as $ordervalue) {
                if ($ordervalue[ 'status' ] == "NEW") {
                    $orders[] = $ordervalue;
                }
            }
        }

        return $orders;
    }

    /**
     */
    private function getAllOpenOrdersBySymbol(mixed $symbol): array
    {
        $matched = array();
        foreach ($this->getAllOpenOrders() as $order) {
            if ($order['symbol'] == $symbol) {
                $matched[] = $order;
            }
        }

        return $matched;
    }

    public function getAllOpenBuyOrders(): mixed
    {
        return $this->filterBySide($this->getAllOpenOrders(), 'BUY');
    }

    public function getAllOpenSellOrders(): mixed
    {
        return $this->filterBySide($this->getAllOpenOrders(), 'SELL');
    }

    /**
     *
     */
    private function filterBySide(mixed $orders, string $side): array
    {
        return array_values(
            array_filter(
                $orders,
                function (mixed $v) use ($side) {
                    return $v['side'] == $side;
                }
            )
        );
    }

    /**
     */
    private function ordersBySide(mixed $symbol, string $side): mixed
    {
        if (in_array($symbol, array_keys($this->orders)) == false) {
            return [];
        }

        return $this->filterBySide($this->orders[ $symbol ], $side);
    }

    public function getAllBuyOrders(mixed $symbol): mixed
    {
        return $this->ordersBySide($symbol, 'BUY');
    }

    public function getAllSellOrders(mixed $symbol): mixed
    {
        return $this->ordersBySide($symbol, 'SELL');
    }

    public function getAllOpenBuyOrdersBySymbol(mixed $symbol): mixed
    {
        return $this->filterBySide($this->getAllOpenOrdersBySymbol($symbol), 'BUY');
    }

    public function getAllOpenSellOrdersBySymbol(mixed $symbol): mixed
    {
        return $this->filterBySide($this->getAllOpenOrdersBySymbol($symbol), 'SELL');
    }

    private function loadSymbolOrders(mixed $symbol, bool $reset): void
    {
        if ($reset == true) {
            $this->orders[$symbol] = array();
        }

        $orders = $this->api->orders($symbol);
        foreach ($orders as $orderdetails) {
            if (isset($orderdetails[ 'status' ]) == false) {
                continue;
            }
            $this->orders[$symbol][] = $orderdetails;
        }
    }

    private function rememberPlacedOrder(mixed $symbol, mixed $price, mixed $quantity, string $label): void
    {
        $orders = array_reverse($this->api->openOrders($symbol));
        $this->orders[ $symbol ][] = $orders[0];
        $this->save();

        @unlink(BinanceBotSettings::getInstance()->cacheBalancesFile);

        $this->smsGateway->send($label . ": $symbol - $price - $quantity");
    }

    /**
     * @return (float|mixed|string)[]
     *
     */
    private function accumulateOpenOrderTotals(mixed $orders): array
    {
        $cum_TotalPriceBTC = 0.0;
        $cum_TotalPriceUSD = 0.0;
        $cum_ChangePriceUSD = 0.0;

        foreach ($orders as $order) {
            $figures = $this->usdChangeForOrder($order);
            $cum_TotalPriceBTC += $order['price'] * $order['origQty'];
            $cum_TotalPriceUSD += ($order['price'] * $order['origQty']) * BinanceBotPrices::getBaseCurrencyUSD();
            $cum_ChangePriceUSD += $figures['diff'];
        }

        return array( "", $cum_TotalPriceBTC, round($cum_TotalPriceUSD, 2), $cum_ChangePriceUSD );
    }

    /**
     * @return float[]
     *
     */
    public function usdChangeForOrder(mixed $order): array
    {
        $rate = BinanceBotPrices::getBaseCurrencyUSD();
        $orderTotalUsd = round($order['price'] * $order['origQty'] * $rate, 2);
        $currentTotalUsd = round($this->prices->getPrice($order['symbol']) * $order['origQty'] * $rate, 2);

        return array(
         'orderTotalUsd' => $orderTotalUsd,
         'currentTotalUsd' => $currentTotalUsd,
         'diff' => $orderTotalUsd - $currentTotalUsd,
        );
    }

    public function cancelBuyOrder(mixed $symbol, mixed $orderid): int
    {
        print " Cancel buy order\n";

       // cancel order if great than 50% change
        $response = $this->api->cancel($symbol, $orderid);

        if (BinanceBotSettings::getInstance()->debug) {
            print_r($response);
        }

        $this->loadSymbolOrders($symbol, true);

        $this->save();

        @unlink(BinanceBotSettings::getInstance()->cacheBalancesFile);

        $this->smsGateway->send("Cancelled: $symbol - $orderid");

        return 1;
    }

    public function placeBuyOrder(mixed $symbol, mixed $price, mixed $quantity): int
    {
        if (BinanceBotSettings::getInstance()->debug) {
            print "Place buy order\n";
        }

        try {
           // open order already
            if (count($this->getAllOpenBuyOrdersBySymbol($symbol)) > 0) {
                return 0;
            }
            $response = $this->api->buy($symbol, $quantity, $price);

            if (BinanceBotSettings::getInstance()->debug) {
                print_r($response);
            }

            $this->rememberPlacedOrder($symbol, $price, $quantity, "Buy Order");
        } catch (Exception $e) {
            print($e);
        }

        return 1;
    }

    public function placeSellOrder(mixed $symbol, mixed $price, mixed $quantity): int
    {
        print " Place sell order\n";

       // open order already
        if (count($this->getAllOpenSellOrdersBySymbol($symbol)) > 0) {
            return 0;
        }

        $exchangeInfo = $this->api->exchangeInfo();
        $exchangeInfoSymbols = $exchangeInfo['symbols'];

        foreach ($exchangeInfoSymbols as $exchangeInfoSymbol) {
            if ($exchangeInfoSymbol['symbol'] == $symbol) {
                if (BinanceBotSettings::getInstance()->debug) {
                    print_r($exchangeInfoSymbol);
                }
            }
        }

        $response = null;
        try {
            $response = $this->api->sell($symbol, $quantity, $price);
        } catch (Exception $e) {
            print($e);
        }

        if (BinanceBotSettings::getInstance()->debug) {
            print_r($response);
        }

        $this->rememberPlacedOrder($symbol, $price, $quantity, "Sell Order");

        return 1;
    }

    public function getBuyOrderTotals(): mixed
    {
        return $this->accumulateOpenOrderTotals($this->getAllOpenBuyOrders());
    }

    public function getSellOrderTotals(): mixed
    {
        return $this->accumulateOpenOrderTotals($this->getAllOpenSellOrders());
    }

    /**
     * @return (int|mixed)[]
     *
     */
    public function calculateOrderSummaryBTCEarnings(): array
    {
        $cumbuy = 0;
        $cumsell = 0;
        $buyfilled = 0;
        $sellfilled = 0;

        foreach ($this->orders as $orderarr) {
            $buy = 0;
            $sell = 0;
            foreach ($orderarr as $orderdetails) {
                if ($orderdetails['side'] == "BUY" && $orderdetails['status'] == "FILLED") {
                    $buy += $orderdetails[ 'price' ] * $orderdetails['executedQty'];
                    $buyfilled += 1;
                }
                if ($orderdetails['side'] == "SELL" && $orderdetails['status'] == "FILLED") {
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
