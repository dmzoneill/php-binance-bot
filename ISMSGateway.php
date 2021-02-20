<?php

namespace BinanceBot;

interface ISMSGateway
{
   public function send( $message );
}
