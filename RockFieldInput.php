<?php namespace ProcessWire;
class RockFieldInput extends WireInputData {

  public function get($key, $type = 'text') {
    $val = parent::get($key);
    $s = new Sanitizer();
    return $s->$type($val);
  }

}
