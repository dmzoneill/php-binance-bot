<?php

namespace BinanceBot;

abstract class BinanceBotService
{
    /** @var mixed */
    protected $api = null;

    /** @var mixed */
    protected $prices = null;

    /** @var mixed */
    protected $smsGateway = null;

    /**
     * @param array<int, mixed> $arrs
     */
    public function __construct($arrs)
    {
        $this->api = $arrs[1];
        $this->prices = $arrs[2];
        $this->smsGateway = $arrs[3];

        if ($arrs[0] == true) {
            @unlink($this->cacheFile());
        }

        $this->update();
    }

    /**
     * @return string
     */
    abstract protected function cacheFile();

    /**
     * @return void
     */
    abstract public function update();
}
