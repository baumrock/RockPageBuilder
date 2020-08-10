<?php namespace ProcessWire;
/**
 * @author Bernhard Baumrock, 10.08.2020
 * @license COMMERCIAL DO NOT DISTRIBUTE
 * @link https://www.baumrock.com
 */
class InputfieldRockMatrix extends InputfieldTextarea {

  /** @var RockMatrix */
  public $master;

  public static function getModuleInfo() {
    return [
      'title' => 'RockMatrix',
      'version' => '0.0.1',
      'summary' => 'Your module description',
      'icon' => 'cubes',
      'requires' => ['RockMatrix'],
      'installs' => [],
    ];
  }

  public function init() {
    $this->master = $this->wire->modules->get('RockMatrix');
  }

  /**
  * Process the Inputfield's input
  * @return $this
  */
  public function ___processInput($input) {
    $this->message('process input!');
    return false;
  }

  /**
  * Render the Inputfield
  * @return string
  */
  public function ___render() {
    $page = $this->process->getPage();
    $out = '';

    if(count($this->master->getAllowedBlocks($this, $page))) {
      $buttons = $this->renderButtons($page);
      $out .= "<div class='rm-buttons-container'>"
        ."<small>".__('Add content').":</small><br>$buttons</div>";
    }

    return $out;
  }

  /**
   * Get button to add a new page having this template
   */
  public function ___renderButton($block, $page) {
    $field = $this->hasField;

    /** @var InputfieldButton $b */
    $b = $this->wire('modules')->get('InputfieldButton');
    $b->secondary = true;
    $b->small = true;
    if($block) {
      $info = $block->info();
      $b->value = $info->get('title') ?: $block->className;
      $b->icon = $info->icon;
      if($info->description) $b->attr('uk-tooltip', $info->description);
      $b->href = "tbd"; //$this->getEndpoint("new/?block={$info->name}&page=$page&field={$field->id}");

      // fix issue https://github.com/processwire/processwire-issues/issues/1220
      $b->addHookAfter("render", function($event) {
        $out = substr($event->return, 2);
        $event->return = "<a tabindex='-1'".$out;
      });
    }
    return $b->render();
  }

  /**
   * Render buttons to add a block to the current field
   * @return string
   */
  public function ___renderButtons($page) {
    $out = '<div class="rm-buttons">';
    foreach($this->master->getAllowedBlocks($this, $page) as $block) {
      $out .= $this->renderButton($block, $page);
    }
    $out .= "</div>";
    return $out;
  }

}