<?php namespace RockMatrix;
abstract class Block extends \ProcessWire\Page {

  /**
   * Method that executes on every modules refresh to migrate db changes
   */
  abstract function migrate();
}
