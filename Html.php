<?php

namespace RockPageBuilder;

use ProcessWire\WireData;

class Html extends WireData
{
  public function __construct($str)
  {
    $this->str = $str;
  }

  public function __toString(): string
  {
    return $this->str;
  }
}
