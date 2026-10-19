<?php

namespace BinanceBot;

abstract class TransactionStrategy implements ITransactionStrategy
{
    /** @var mixed */
    protected $api = null;

    /** @var mixed */
    protected $BinanceBotPrices = null;

    /** @var mixed */
    protected $BinanceBotOrders = null;

    /** @var mixed */
    protected $BinanceBotHoldings = null;

    /** @var mixed */
    protected $BinanceBotCandles = null;

    /**
     * @param array<int, mixed> $arrs
     */
    public function __construct($arrs)
    {
        $this->api = $arrs[0];
        $this->BinanceBotHoldings = $arrs[1];
        $this->BinanceBotOrders = $arrs[2];
        $this->BinanceBotPrices = $arrs[3];
        $this->BinanceBotCandles = $arrs[4];
    }

    /**
     * @param mixed $symbol
     * @param mixed $TotalPriceUSD
     * @param mixed $quantity
     * @return float
     */
    #[\Override]
    public function getBuySpread($symbol, $TotalPriceUSD, $quantity)
    {
        $btcUsd = BinanceBotPrices::getBTCUSD();
        $price = $this->BinanceBotPrices->getPrice($symbol);

        return round($TotalPriceUSD - ($btcUsd * ($price * $quantity)), 2);
    }

    /**
     * @param mixed $symbol
     * @param mixed $TotalPriceUSD
     * @param mixed $quantity
     * @return float
     */
    #[\Override]
    public function getSellSpread($symbol, $TotalPriceUSD, $quantity)
    {
        if ($this->BinanceBotHoldings->getHoldings($symbol) != false) {
            $btcUsd = BinanceBotPrices::getBTCUSD();
            $price = $this->BinanceBotPrices->getPrice($symbol);

            return round($TotalPriceUSD - ($btcUsd * ($price * $quantity)), 2);
        }

        return 0.0;
    }

    /**
     * @param array<string, mixed> $order
     * @return float
     */
    private function orderDiffPercent($order)
    {
        $orderTotal = $order['price'] * $order['origQty'];
        $currentTotal = $this->BinanceBotPrices->getPrice($order['symbol']) * $order['origQty'];
        $diff = $orderTotal - $currentTotal;

        return round((abs($diff) / $orderTotal) * 100);
    }

    /**
     * @return void
     */
    protected function cancelAdditionalBuyOrders()
    {
        $orders = $this->BinanceBotOrders->getAllOpenBuyOrders();

        usort(
            $orders,
            function ($a, $b) {
                return $this->orderDiffPercent($a) < $this->orderDiffPercent($b) ? 1 : 0;
            }
        );

        $excess = count($orders) - 1 - BinanceBotSettings::getInstance()->max_open_buy_orders;
        for ($i = 0; $i < $excess; $i++) {
            $this->cancelTrackedBuyOrder($orders[$i]['symbol'], $orders[$i]['orderId']);
        }
    }

    /**
     * @param mixed $symbol
     * @param mixed $orderId
     * @return void
     */
    protected function cancelTrackedBuyOrder($symbol, $orderId)
    {
        $this->BinanceBotOrders->cancelBuyOrder($symbol, $orderId);
    }

    /**
     * @param callable(mixed, array<string, mixed>, array<string, mixed>): bool $place
     * @return int
     */
    protected function sellAvailableHoldings($place)
    {
        $numorders = 0;

        foreach ($this->BinanceBotHoldings->getBalances() as $symbol => $data) {
            if ($symbol == BinanceBotSettings::getInstance()->base_currency) {
                continue;
            }

            $pair = $symbol . BinanceBotSettings::getInstance()->base_currency;
            $openOrders = $this->BinanceBotOrders->getAllOpenSellOrdersBySymbol($pair);
            if (count($openOrders) > 0) {
                continue;
            }

            if ($data['available'] >= 1.00) {
                $this->beforeSellOrders($symbol, $data);
                $orders = array_reverse($this->BinanceBotOrders->getAllBuyOrders($pair));
                $this->afterSellOrdersLoaded($symbol, $orders);

                foreach ($orders as $orderdetails) {
                    if (isset($orderdetails['status']) == false) {
                        continue;
                    }

                    if ($place($symbol, $data, $orderdetails) === true) {
                        $numorders++;
                    }
                    break;
                }
            }
        }

        return $numorders;
    }

    /**
     * @param mixed $symbol
     * @param array<string, mixed> $data
     * @return void
     */
    protected function beforeSellOrders($symbol, $data)
    {
    }

    /**
     * @param mixed $symbol
     * @param array<int, mixed> $orders
     * @return void
     */
    protected function afterSellOrdersLoaded($symbol, $orders)
    {
    }

    /**
     * @param mixed $side
     * @param int $numOrders
     * @return bool
     */
    protected function shouldAbortForCandles($side, $numOrders)
    {
        return false;
    }

    /**
     * @return bool
     */
    protected function updateCandlesBeforeBuy()
    {
        return true;
    }

    /**
     * @param mixed $side
     * @return void
     */
    #[\Override]
    public function limitOrders($side)
    {
        $exchange_rate = BinanceBotPrices::getBTCUSD();
        $numOrders = 0;

        foreach (array_keys($this->BinanceBotPrices->getAllPrices()) as $symbol) {
            $orders = ($side == "SELL")
                ? $this->BinanceBotOrders->getAllOpenSellOrdersBySymbol($symbol)
                : $this->BinanceBotOrders->getAllOpenBuyOrdersBySymbol($symbol);

            foreach ($orders as $orderdetails) {
                $PriceBTC = $orderdetails['price'];
                $quantity = $orderdetails['origQty'];
                $TotalPriceUSD = $exchange_rate * ($PriceBTC * $quantity);
                $currentPrice = $this->BinanceBotPrices->getPrice($symbol);
                $TotalPriceUSDCurrent = $exchange_rate * ($currentPrice * $quantity);
                $TotalPriceUSDDifferencePercent = round(($TotalPriceUSD / $TotalPriceUSDCurrent) * 100, 2);

                if ($orderdetails['side'] == "SELL") {
                    $this->getSellSpread($symbol, $TotalPriceUSD, $orderdetails['origQty']);
                } else {
                    $this->getBuySpread($symbol, $TotalPriceUSD, $orderdetails['origQty']);
                }

                $settings = BinanceBotSettings::getInstance();
                $belowRange = $TotalPriceUSDDifferencePercent < $settings->cancel_buy_at_lower_percent;
                $aboveRange = $TotalPriceUSDDifferencePercent > $settings->cancel_buy_at_upper_percent;
                if ($side == "BUY" && ($belowRange || $aboveRange)) {
                    $this->cancelTrackedBuyOrder($symbol, $orderdetails['orderId']);
                }

                $numOrders++;
            }
        }

        if ($this->shouldAbortForCandles($side, $numOrders) == true) {
            return;
        }

        if ($side == "BUY") {
            $reserve = BinanceBotSettings::getInstance()->reserve_amount;
            $available = $this->BinanceBotHoldings->getBaseCurrencyAvailable();
            if ($available < $reserve) {
                return;
            }

            while ($numOrders < BinanceBotSettings::getInstance()->max_open_buy_orders) {
                if ($this->updateCandlesBeforeBuy() == true && $this->BinanceBotCandles->update() == true) {
                    break;
                }

                $norders = $this->placeBuyOrder();
                $numOrders++;
                $numOrders = ($norders == 0) ? BinanceBotSettings::getInstance()->max_open_buy_orders : $numOrders;
            }

            if ($numOrders > BinanceBotSettings::getInstance()->max_open_buy_orders) {
                $this->cancelAdditionalBuyOrders();
            }
        } else {
            while ($numOrders < BinanceBotSettings::getInstance()->max_open_sell_orders) {
                $norders = $this->placeSellOrder();
                $numOrders++;
                $numOrders = ($norders == 0) ? BinanceBotSettings::getInstance()->max_open_sell_orders : $numOrders;
            }
        }
    }

    /**
     * @return mixed
     */
    abstract public function placeBuyOrder();

    /**
     * @return mixed
     */
    abstract public function placeSellOrder();
}
