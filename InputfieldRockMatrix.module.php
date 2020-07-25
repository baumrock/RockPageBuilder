<?php namespace ProcessWire;
/**
 * @author Bernhard Baumrock, 18.07.2020
 * @license Licensed under MIT
 * @link https://www.baumrock.com
 */
class InputfieldRockMatrix extends InputfieldTextarea {

  /** @var RockMatrix */
  public $master;

  public static function getModuleInfo() {
    return [
      'title' => 'InputfieldRockMatrix',
      'version' => '0.0.1',
      'summary' => 'RockMatrix Inputfield',
      'autoload' => false,
      'singular' => false,
      'icon' => 'bolt',
      'requires' => ['RockMatrix'],
      'installs' => [],
    ];
  }

  public function init() {
    $this->master = $this->modules->get('RockMatrix');
  }

  /**
   * Inputfield is ready to render
   */
  public function renderReady() {
    $file = $this->className.".js";
    $path = $this->config->paths($this).$file;
    $m = "?m=".filemtime($path);
    $this->config->scripts->add($this->config->urls($this).$file.$m);

    $url = $this->config->urls($this);
    $file = $url.$this->className.".less";
    $less = $this->modules->get('RockLESS'); /** @var RockLESS $less */
    if($less) $less->addToConfig($file);
    else $this->config->styles->add("$file.css");
  }

  /**
   * Render this inputfield
   */
  public function ___render() {
    if(!$this->process instanceof ProcessPageEdit) {
      return "This field is only supported on page edit";
    }
    $page = $this->process->getPage();
    $out = $this->renderItems($page);

    // render buttons to add a new page
    $buttons = $this->renderButtons($page);
    if($buttons) {
      $out .= "<div class='rm-buttons-container uk-margin-top'>"
        ."<small>".__('Add content').":</small><br>$buttons</div>";
    }
    else $out .= $this->setupInfo('allowed-templates');

    return $out;
  }

  /**
   * Render child items
   */
  public function ___renderItems($page) {
    $out = '';
    foreach($this->getItems($page) as $item) {
      $out .= $this->renderItem($item);
    }
    return $out;
  }

  /**
   * Render a single page edit field
   */
  public function ___renderItem($page) {
    return $this->getItemInputfield($page)->render();
  }

  /**
   * Get inputfield of item
   */
  public function getItemInputfield($page) {
    /** @var InputfieldRockPageEdit $f */
    $f = $this->wire('modules')->get('InputfieldRockPageEdit');
    $f->editPage = $page;
    return $f;
  }

  /**
   * Process input
   *
   * @param WireInputData $input
   * @return $this
   *
   */
  public function ___processInput(WireInputData $input) {
    $iteminput = $this->getIteminput($input);

    // process all items
    $items = $this->getItems($this->process->getPage());
    foreach($items as $item) {
      $f = $this->getItemInputfield($item);
      $f->processInput($input);
    }
  }

  /**
   * Get array of item input data having page id keys
   * @return array
   */
  public function getIteminput($input) {
    $arr = [];
    $suffix = "_repeater";
    foreach($input as $prop=>$val) {
      // skip all non-repeater input
      $i = strpos($prop, $suffix);
      if(!$i) continue;

      $field = substr($prop, 0, $i);
      $id = substr($prop, $i+strlen($suffix));
      if(!array_key_exists($id, $arr)) $arr[$id] = new WireInputData();
      $arr[$id]->$field = $val;
    }
    return $arr;
  }

  /**
   * Get matrix items of this page
   */
  public function ___getItems($page) {
    return $page->children; // TODO make dynamic
  }

  /**
   * Show info to setup the field correctly
   * @return string
   */
  public function setupInfo($what) {
    return $this->wire->files->render(__DIR__."/info/$what");
  }

  /**
   * Render all buttons for this inputfield
   */
  public function renderButtons($page) {
    $out = '<div class="rm-buttons">';
    foreach($this->master->getAllowedBlocks($this, $page) as $blockname) {
      $out .= $this->getBlockButton($blockname, $page);
    }
    $out .= "</div>";
    return $out;
  }

  /**
   * Get button to add a new page having this template
   */
  public function ___getBlockButton($blockname, $page) {
    $block = $this->master->getBlock($blockname);
    $field = $this->hasField;

    /** @var InputfieldButton $b */
    $b = $this->wire('modules')->get('InputfieldButton');
    $b->secondary = true;
    $b->small = true;
    if($block) {
      $info = $block->info();
      $b->value = $info->get('title|name');
      $b->icon = $info->icon;
      $b->href = $this->getEndpoint("new/?block={$info->name}&page=$page&field={$field->id}");
    }
    else {
      // no template found
      if(!$this->config->debug) return;
      $b->icon = "exclamation-triangle";
      $b->value = "$blockname not found";
    }
    return $b->render();
  }

  /**
   * Get api endpoint page
   */
  public function getEndpoint($action = null) {
    $page = $this->pages->get("parent=22,name=".ProcessRockMatrix::pageName);
    return $page->url.$action;
  }

  /**
  * Config inputfields
  * @param InputfieldWrapper $inputfields
  */
  public function getModuleConfigInputfields($inputfields) {
    return $inputfields;
  }
}
