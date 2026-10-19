<?php

namespace BinanceBot;

use Exception;

class PeriodBasedTransactionStrategy extends TransactionStrategy
{
    /**
     * @param mixed $symbol
     * @param mixed $orderId
     * @return void
     */
    #[\Override]
    protected function cancelTrackedBuyOrder($symbol, $orderId)
    {
        if (BinanceBotSettings::getInstance()->test == false) {
            try {
                $res = $this->BinanceBotOrders->cancelBuyOrder($symbol, $orderId);
                if (BinanceBotSettings::getInstance()->debug) {
                    print(__FUNCTION__ . ":" . __LINE__ . " Cancel buy order result:\n");
                    print_r($res);
                }
            } catch (Exception $e) {
                print(__FUNCTION__ . ":" . __LINE__ . " Error: " . $e->getMessage() . "\n");
            }
        }
    }

    /**
     * @param mixed $side
     * @param int $numOrders
     * @return bool
     */
    #[\Override]
    protected function shouldAbortForCandles($side, $numOrders)
    {
        if ($side == "BUY" && $numOrders < BinanceBotSettings::getInstance()->max_open_buy_orders) {
            if ($this->BinanceBotCandles->update() == true) {
                return true;
            }
        }

        if ($side == "SELL" && $numOrders < BinanceBotSettings::getInstance()->max_open_sell_orders) {
            if ($this->BinanceBotCandles->update() == true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    #[\Override]
    protected function updateCandlesBeforeBuy()
    {
        return false;
    }

    /**
     * @param mixed $symbol
     * @param array<string, mixed> $data
     * @return void
     */
    #[\Override]
    protected function beforeSellOrders($symbol, $data)
    {
        if (BinanceBotSettings::getInstance()->debug) {
            print(__FUNCTION__ . ":" . __LINE__ . " $symbol available = " . $data['available'] . "\n");
        }
    }

    /**
     * @param mixed $symbol
     * @param array<int, mixed> $orders
     * @return void
     */
    #[\Override]
    protected function afterSellOrdersLoaded($symbol, $orders)
    {
        if (BinanceBotSettings::getInstance()->debug) {
            print(__FUNCTION__ . ":" . __LINE__ . " getAllBuyOrders $symbol\n");
            print(implode(",", $orders) . "\n");
        }
    }

    /**
     * @return mixed
     */
    #[\Override]
    public function placeBuyOrder()
    {
        if (BinanceBotSettings::getInstance()->debug) {
            print(__FUNCTION__ . ":" . __LINE__ . " Place buy order:\n");
        }

        /** @var array<string, mixed> $weightedStocks */
        $weightedStocks = $this->BinanceBotCandles->getBestStocks();
        $weightedStocksKeys = array_keys($weightedStocks);
        $delim = count($weightedStocks) > BinanceBotSettings::getInstance()->max_open_buy_orders * 2
            ? BinanceBotSettings::getInstance()->max_open_buy_orders * 2 : count($weightedStocks);
        $pennyStocks = array();

        if (BinanceBotSettings::getInstance()->debug) {
            print(__FUNCTION__ . ":" . __LINE__ . " Weighted stocks\n");
            print(implode(",", $weightedStocksKeys) . "\n");
        }

        for ($t = 0; $t < $delim; $t++) {
            $symbol = $weightedStocksKeys[$t];

            if ($this->BinanceBotPrices->getPrice($symbol) < BinanceBotSettings::getInstance()->max_unit_price) {
                if (BinanceBotSettings::getInstance()->debug) {
                    print(__FUNCTION__ . ":" . __LINE__ . " Unit price $symbol < "
                        . BinanceBotSettings::getInstance()->max_unit_price . "\n");
                }

                $change = $this->api->prevDay($symbol)['priceChangePercent'];
                $trigger = BinanceBotSettings::getInstance()->downward_trigger_percent;
                if ($change < $trigger) {
                    if (BinanceBotSettings::getInstance()->debug) {
                        print(__FUNCTION__ . ":" . __LINE__ . " Percent $symbol "
                            . $this->api->prevDay($symbol)['priceChangePercent'] . " < "
                                . BinanceBotSettings::getInstance()->downward_trigger_percent . "\n");
                    }

                    if (count($this->BinanceBotOrders->getAllOpenSellOrdersBySymbol($symbol)) > 0) {
                        continue;
                    }
                    if (count($this->BinanceBotOrders->getAllOpenBuyOrdersBySymbol($symbol)) > 0) {
                        continue;
                    }

                    $gotSymbol = $this->BinanceBotHoldings->getHoldings(substr(
                        $symbol,
                        0,
                        strlen(BinanceBotSettings::getInstance()->base_currency) * -1
                    ));

                    if ($gotSymbol != false) {
                        if ($gotSymbol > BinanceBotSettings::getInstance()->ignore_less_than_amount_dollars) {
                            continue;
                        }
                    }

                    $pennyStocks[$symbol] = $this->BinanceBotPrices->getPrice($symbol);
                }
            }
        }

        // no stocks meet criteria
        if (count($pennyStocks) == 0) {
            $this->BinanceBotPrices->forceupdate();
            return 0;
        }

        $buysymbol = array_rand($pennyStocks);

        if (BinanceBotSettings::getInstance()->debug) {
            print(__FUNCTION__ . ":" . __LINE__ . " Penny stocks\n");
            print(implode(",", $pennyStocks) . "\n");
        }

        list($symbolPeriodLow, $symbolPeriodHigh,
            $symbolPeriodAvg) = $this->BinanceBotCandles->getSymbolLowHighAvgAtInterval(
                $buysymbol,
                BinanceBotSettings::getInstance()->investment_period
            );

        if ($symbolPeriodLow == 0 && $symbolPeriodHigh == 0 && $symbolPeriodAvg == 0) {
            return;
        }

        $avgByBuyPercent = $symbolPeriodAvg * BinanceBotSettings::getInstance()->buy_at_percent;

        $buy_price = ($avgByBuyPercent < $symbolPeriodLow) ? $symbolPeriodLow : $avgByBuyPercent;

        $quantity = round(BinanceBotSettings::getInstance()->buy_total_amount / $buy_price);

        if (BinanceBotSettings::getInstance()->test == false) {
            try {
                $response = $this->BinanceBotOrders->placeBuyOrder($buysymbol, sprintf("%f", $buy_price), $quantity);
                if (BinanceBotSettings::getInstance()->debug) {
                    print(__FUNCTION__ . ":" . __LINE__ . " Buy order result:\n");
                    print_r($response);
                }
            } catch (Exception $e) {
                print(__FUNCTION__ . ":" . __LINE__ . " Error: " . $e->getMessage() . "\n");
            }
        }

        printf(
            "\nPeriod low: %f, high: %f, average: %f, sought: %f\n",
            $symbolPeriodLow,
            $symbolPeriodHigh,
            $symbolPeriodAvg,
            $avgByBuyPercent
        );
        printf("Buy %s of %s @ %f\n", $quantity, $buysymbol, $buy_price);
    }

    /**
     * @return int
     */
    #[\Override]
    public function placeSellOrder()
    {
        if (BinanceBotSettings::getInstance()->debug) {
            print(__FUNCTION__ . ":" . __LINE__ . " Place sell order:\n");
        }

        return $this->sellAvailableHoldings(
            function ($symbol, $data, $orderdetails) {
                list($symbolPeriodLow, $symbolPeriodHigh,
                    $symbolPeriodAvg) = $this->BinanceBotCandles->getSymbolLowHighAvgAtInterval(
                        $symbol
                        . BinanceBotSettings::getInstance()->base_currency,
                        BinanceBotSettings::getInstance()->investment_period
                    );

                if ($symbolPeriodLow == 0 && $symbolPeriodHigh == 0 && $symbolPeriodAvg == 0) {
                    return false;
                }

                $avgBySellPercent = $symbolPeriodAvg * BinanceBotSettings::getInstance()->sell_at_percent;

                printf(
                    "Period low: %f, high: %f, average: %f, sought: %f\n",
                    $symbolPeriodLow,
                    $symbolPeriodHigh,
                    $symbolPeriodAvg,
                    $avgBySellPercent
                );

                $sell_price = ($avgBySellPercent > $symbolPeriodHigh) ? $symbolPeriodHigh : $avgBySellPercent;
                $sell_price = ($sell_price < $orderdetails['price'])
                    ? $orderdetails['price'] * BinanceBotSettings::getInstance()->sell_at_percent : $sell_price;

                print __FUNCTION__ . ":" . __LINE__ . " " . $symbol . BinanceBotSettings::getInstance()->base_currency
                    . " " . strval(floor($data['available'])) . " * " . sprintf("%f", $sell_price) . "\n";
                printf(
                    $symbol . BinanceBotSettings::getInstance()->base_currency . " %f \n",
                    (floor($data['available']) * $sell_price)
                );

                if (BinanceBotSettings::getInstance()->test == false) {
                    try {
                        $response = $this->BinanceBotOrders->placeSellOrder(
                            $symbol
                            . BinanceBotSettings::getInstance()->base_currency,
                            sprintf("%f", $sell_price),
                            floor($data['available'])
                        );
                        if (BinanceBotSettings::getInstance()->debug) {
                            print(__FUNCTION__ . ":" . __LINE__ . " Sell order result:n");
                            print_r($response);
                        }
                    } catch (Exception $e) {
                        print(__FUNCTION__ . ":" . __LINE__ . " Error: " . $e->getMessage() . "\n");
                    }
                }

                printf(__FUNCTION__ . ":" . __LINE__ . " Sell %s @ %f\n", $symbol, $sell_price);
                return true;
            }
        );
    }
}
