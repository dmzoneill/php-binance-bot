<?php

namespace BinanceBot;

class BasicTransactionStrategy extends TransactionStrategy
{
    /**
     * @return mixed
     */
    #[\Override]
    public function placeBuyOrder()
    {
        $pennyStocks = array();

        $sorted = $this->BinanceBotPrices->getAllPrices();
        asort($sorted);

        foreach ($sorted as $symbol => $price) {
            if (
                substr($symbol, strlen(BinanceBotSettings::getInstance()->base_currency) * -1)
                != BinanceBotSettings::getInstance()->base_currency
            ) {
                continue;
            }
            if (count($this->BinanceBotOrders->getAllOpenBuyOrdersBySymbol($symbol)) > 0) {
                continue;
            }

            if ($price < BinanceBotSettings::getInstance()->max_unit_price) {
                $change = $this->api->prevDay($symbol)['priceChangePercent'];
                $trigger = BinanceBotSettings::getInstance()->downward_trigger_percent;
                if ($change < $trigger) {
                    $openOrders = $this->BinanceBotOrders->getAllOpenSellOrdersBySymbol($symbol
                        . BinanceBotSettings::getInstance()->base_currency);
                    if (count($openOrders) > 0) {
                        continue;
                    }

                    $retracements = $this->BinanceBotCandles->getBestStocks();
                    $retracementskeys = array_keys($this->BinanceBotCandles->getBestStocks());
                    $delim = count($retracements) > BinanceBotSettings::getInstance()->max_open_buy_orders * 2
                        ? BinanceBotSettings::getInstance()->max_open_buy_orders * 2 : count($retracements);
                    $found = false;

                    for ($t = 0; $t < $delim; $t++) {
                        if ($retracementskeys[$t] == $symbol) {
                            $found = true;
                            break;
                        }
                    }

                    if ($found == true) {
                        $pennyStocks[$symbol] = $price;
                    }
                }
            }
        }
        // no stocks meet criteria
        if (count($pennyStocks) == 0) {
            $this->BinanceBotPrices->forceupdate();
            return 0;
        }

        $buysymbol = array_rand($pennyStocks);
        $asset = substr($buysymbol, 0, strlen(BinanceBotSettings::getInstance()->base_currency) * -1);

        // make sure we dont own this stock already
        if ($this->BinanceBotHoldings->getHoldings($asset) != false) {
            $held = $this->BinanceBotHoldings->getHoldings($asset);
            $ignoreBelow = BinanceBotSettings::getInstance()->ignore_less_than_amount_dollars;
            if ($held > $ignoreBelow) {
                // already own some of this stock, no need to buy more
                print "Already own " . $pennyStocks[$buysymbol] . "\n";
                return;
            }
        }

        $buyAmount = BinanceBotSettings::getInstance()->buy_total_amount;
        $buyAt = BinanceBotSettings::getInstance()->buy_at_percent;
        $quantity = round($buyAmount / ($pennyStocks[$buysymbol] * $buyAt));

        $this->BinanceBotOrders->placeBuyOrder($buysymbol, sprintf(
            "%f",
            $pennyStocks[$buysymbol] * BinanceBotSettings::getInstance()->buy_at_percent
        ), $quantity);
        printf(
            "Buy %s of %s @ %f\n",
            $quantity,
            $buysymbol,
            ($pennyStocks[$buysymbol] * BinanceBotSettings::getInstance()->buy_at_percent)
        );
    }

    /**
     * @return int
     */
    #[\Override]
    public function placeSellOrder()
    {
        return $this->sellAvailableHoldings(
            function ($symbol, $data, $orderdetails) {
                $sellPrice = $orderdetails['price'] * BinanceBotSettings::getInstance()->sell_at_percent;
                print $symbol . BinanceBotSettings::getInstance()->base_currency . " "
                    . strval(floor($data['available']))
                    . " * " . sprintf("%f", $sellPrice) . "\n";
                printf(
                    $symbol . BinanceBotSettings::getInstance()->base_currency . " %f \n",
                    (floor($data['available']) * $sellPrice)
                );
                $this->BinanceBotOrders->placeSellOrder(
                    $symbol . BinanceBotSettings::getInstance()->base_currency,
                    sprintf("%f", $sellPrice),
                    floor($data['available'])
                );
                printf("Sell %s @ %f\n", $symbol, $sellPrice);
                return true;
            }
        );
    }
}
