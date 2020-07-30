<?php namespace ProcessWire;
/**
 * @author Bernhard Baumrock, 18.07.2020
 * @license Licensed under MIT
 * @link https://www.baumrock.com
 */
class InputfieldRockMatrix extends Inputfield {

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

    // load vex
    $this->wire('modules')->get('JqueryUI')->use('vex');

    // load JS
    $js = $this->wire->config->urls($this)."RockMatrixItem.js";
    $this->wire->config->scripts->add($js);
  }

  /**
   * Render this inputfield
   */
  public function ___render() {
    if(!$this->process instanceof ProcessPageEdit) {
      return "This field is only supported on page edit";
    }
    $page = $this->process->getPage();
    $out = "<div class='rm-items'>"
      .$this->renderItems($page)
      ."</div>";

    // render buttons to add a new page
    $buttons = $this->renderButtons($page);
    if($buttons) {
      $out .= "<div class='rm-buttons-container uk-margin-top'>"
        ."<small>".__('Add content').":</small><br>$buttons</div>";
    }
    else $out .= $this->setupInfo('allowed-templates');

    $out .= $this->renderInputfield();
    $out .= $this->renderInitTag();
    return $out;
  }

  /**
   * Render Inputfield holding data
   * @return string
   */
  public function renderInputfield() {
    $out = "<textarea class='uk-hidden2 rm-data' name='{$this->name}'>"
      .$this->value->sleep()
      ."</textarea>";
    return $out;
  }

  /**
   * Render init tag
   * @return string
   */
  public function renderInitTag() {
    $out = "<script>$('#wrap_Inputfield_{$this->name}').trigger('init');</script>";
    return $out;
  }

  /**
   * Render child items
   */
  public function ___renderItems($page) {
    $out = '';
    $items = $this->value;
    foreach($items as $item) {
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
    $f->addClass('rm-item');
    $f->editPage = $page;
    $f->collapsed = Inputfield::collapsedYes;
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
    $page = $this->process->getPage();
    $old = $this->value;
    $json = $input->{$this->name};
    $new = new RockMatrixPageArray($page, $this->hasField);

    // get data of hidden textarea and add pages to MatrixPageArray
    // this ensures that only pages are added that have the reference to
    // the current page in the meta data
    $new->wakeup($json);

    // process all changed items
    $itemChanged = false;
    foreach($new as $item) {
      // only process changed items
      if(!$new->itemChanged($item)) continue;

      // check if item is editable by current user
      if(!$item->editable()) {
        $this->warning("Skipped item $item - not editable!");
        $this->value->remove($item);
        continue;
      }

      // all fine, process input
      $itemChanged = true;
      $f = $this->getItemInputfield($item);
      $f->processInput($input);
    }

    // set new value
    // changes will only be triggerd if the new json is different
    // this means that changes to the matrix items do not trigger a change!
    if(!$old->equals($new) OR $itemChanged) {
      $this->trackChange('value');
      $new->changed = time(); // update timestamp of last change

      // trigger a change on the pagearray
      // this must be triggered manually because the pagearray does not
      // know about changes that happen on it's pages
      // a pagearray does only track added/removed events
      $this->value->trackChange('rockmatrix-item-changed');

      // set new value
      $this->value = $new;
    }
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
    $page = $this->pages->get("parent=2,name=".ProcessRockMatrix::pageName);
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
