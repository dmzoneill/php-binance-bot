<?php

namespace BinanceBot;

class BinanceBotPrinter
{
    private string $holdingsMaskTitle = "| %4s | %10s | %20s | %20s | %16s | %8s | %16s | %8s | %8s |";
    private string $holdingsMask = "| %4s | %10s | % 20.11f | % 20.11f | % 16.11f | "
        . "% 8.2f | % 16.11f | % 8.2f | % 8.2f |";
    private string $holdingsTotalMask = "%70s % 12.11f   %8.2f   %16.11f   %8.2f   %8.2f";

    /**
     * @var string
     */
    private $holdingsTotalLine = "";

    private string $ordersMaskTitle = "| %4s | %10s | %6s | %7s | %7s | %14s | %14s | "
        . "%10s | %8s | %7s | %7s | %5s | %3s | %3s | %3s |";
    private string $ordersMask = "| %4s | %10s | %6s | %7s | %7d | % 14.10f | % 14.10f | "
        . "% 10.4f | % 8.2f | % 7.2f | % 7.2f | %5s | T1%3s\e[49m | T2%3s\e[49m | T3%3s\e[49m |";
    private string $ordersTotalMask = "%68s  % 11.10f                % 8.2f  % 8.2f";

    /**
     * @var string
     */
    private $ordersTotalLine = "";

    private mixed $outputLines = array();
    private mixed $api = null;
    private mixed $holdings = null;
    private mixed $orders = null;
    private mixed $candles = null;

    public function __construct(mixed $arrs)
    {
        $this->api = $arrs[0];
        $this->holdings = $arrs[1];
        $this->orders = $arrs[2];
        $this->candles = $arrs[4];

        $this->holdingsTotalLine = str_repeat("-", 136);
        $this->ordersTotalLine = str_repeat("-", 152);
    }

    private function clear(): void
    {
        for ($i = 0; $i < count($this->outputLines) + 7; $i++) {
            echo "\r\033[K\033[1A\r\033[K\r";
        }

        $this->outputLines = array();
    }

    private function printLines(): void
    {
        for ($i = 0; $i < count($this->outputLines); $i++) {
            print $this->outputLines[$i] . "\n";
        }
    }

    private function addLine(string $line): void
    {
        $this->outputLines[] = " " . $line;
    }

    private function printHeading(): void
    {
        list( $cumbuy, $cumsell, $buyfilled, $sellfilled ) = $this->orders->calculateOrderSummaryBTCEarnings();

        $exchangeline = "Exchange Rate BTC = $" . BinanceBotPrices::getBTCUSD();
        $exchangeline .= " - €" . BinanceBotPrices::getBTCEUR() . " - " . sha1(strval(time()));

        $exchangelineBase = "Exchange Rate " . BinanceBotSettings::getInstance()->base_currency . " = $"
            . BinanceBotPrices::getBaseCurrencyUSD();
        $exchangelineBase .= " - €" . BinanceBotPrices::getBaseCurrencyEUR();

        $orderStats = "Max Buy/Sell orders = " . BinanceBotSettings::getInstance()->max_open_buy_orders . "/"
            . BinanceBotSettings::getInstance()->max_open_sell_orders;
        $orderStats .= sprintf(" - Accumulated: % 10.8f " . BinanceBotSettings::getInstance()->base_currency
            . " ( $% 5.2f )", ( $cumsell - $cumbuy ), ( $cumsell - $cumbuy ) * BinanceBotPrices::getBaseCurrencyUSD());

        $apiStats = "Api requests: " . $this->api->getRequestCount() . ", total of " . $this->api->getTransfered();
        $apiStats .= sprintf(" - Bought: %s, Sold: %s\n", $buyfilled, $sellfilled);

        $this->addLine($exchangeline);
        $this->addLine($exchangelineBase);
        $this->addLine($orderStats);
        $this->addLine($apiStats);
    }

    public function printHoldings(): void
    {
        $this->addLine("Holdings");
        $this->addLine(" " . $this->holdingsTotalLine);
        $this->addLine(sprintf(
            $this->holdingsMaskTitle,
            '#',
            'Symbol',
            'Available',
            'OnOrder',
            'btcValue',
            'usdValue',
            'btcTotal',
            'usdTotal',
            'eurTotal'
        ));
        $this->addLine(" " . $this->holdingsTotalLine);

        $numid = 1;
        foreach ($this->holdings->getWallet() as $coin) {
            $copy = $coin;
            array_unshift($copy, $numid);
            $this->addLine(vsprintf($this->holdingsMask, $copy));
            $numid++;
        }

        $this->addLine(" " . $this->holdingsTotalLine);
        $this->addLine(sprintf(
            $this->holdingsTotalMask,
            "",
            $this->holdings->getCumBTCValue(),
            $this->holdings->getCumUSDValue(),
            $this->holdings->getCumBTCTotal(),
            $this->holdings->getCumUSDTotal(),
            $this->holdings->getCumEURTotal()
        ));
    }

    public function printBuyOrders(): void
    {
        $this->printLimitOrders(
            "Buy Limits Orders",
            $this->orders->getAllOpenBuyOrders(),
            $this->orders->getBuyOrderTotals()
        );
    }

    public function printSellOrders(): void
    {
        $this->printLimitOrders(
            "Sell Limits Orders",
            $this->orders->getAllOpenSellOrders(),
            $this->orders->getSellOrderTotals()
        );
    }

    private function printLimitOrders(string $title, mixed $orders, mixed $totals): void
    {
        $base = BinanceBotSettings::getInstance()->base_currency;
        $this->addLine($title);
        $this->addLine(" " . $this->ordersTotalLine);
        $this->addLine(sprintf($this->ordersMaskTitle, '#', 'Symbol', 'Side', 'Type', 'Q', $base . '/Unit', 'Total'
            . $base, 'USD/Unit', 'TotalUSD', 'CTotal', 'C/Unit', 'C%', '3m', '15m', '1hr'));
        $this->addLine(" " . $this->ordersTotalLine);

        $numid = 1;
        foreach ($orders as $order) {
            $figures = $this->orders->usdChangeForOrder($order);
            $orderTotalUsd = $figures['orderTotalUsd'];
            $diff = $figures['diff'];
            $diffunit = $order['origQty'] < 1 ? $diff : round($diff / $order['origQty'], 3);
            $diffpercent = round(( abs($diff) / $orderTotalUsd ) * 100);

            $mask = $this->ordersMask;
            foreach (array("T1" => "3m", "T2" => "15m", "T3" => "1h") as $token => $interval) {
                $color = $this->formatTrendColor($this->candles->getTrend($order['symbol'], $interval));
                $replaced = preg_replace("/" . $token . "/", $color, $mask);
                if (!is_string($replaced)) {
                    continue;
                }
                $mask = $replaced;
            }

            $this->addLine(sprintf(
                $mask,
                $numid,
                $order['symbol'],
                $order['side'],
                $order['type'],
                round($order['origQty'], 3),
                $order['price'],
                $order['price'] * $order['origQty'],
                round($order['price'] * BinanceBotPrices::getBaseCurrencyUSD(), 2),
                $orderTotalUsd,
                round($diff, 2),
                $diffunit,
                strval($diffpercent) . "%",
                $this->formatTrend($this->candles->getTrend($order['symbol'], "3m")),
                $this->formatTrend($this->candles->getTrend($order['symbol'], "15m")),
                $this->formatTrend($this->candles->getTrend($order['symbol'], "1h"))
            ));

            $numid++;
        }

        $this->addLine(" " . $this->ordersTotalLine);
        $this->addLine(vsprintf($this->ordersTotalMask, $totals));
    }

    private function formatTrend(mixed $trend): string
    {
        switch ($trend) {
            case -2:
                return " ";

            case -1:
                return " ";

            case 1:
                return " ";

            default:
                return " ";
        }
    }

    private function formatTrendColor(mixed $trend): string
    {
        switch ($trend) {
            case -2:
                return "\e[49m";

            case -1:
                return "\e[41m";

            case 1:
                return "\e[42m";

            default:
                return "\e[49m";
        }
    }

    public function update(): void
    {
        $this->clear();
        $this->printHeading();
        $this->printHoldings();
        $this->printBuyOrders();
        $this->printSellOrders();
        $this->printLines();
    }
}
