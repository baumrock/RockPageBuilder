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

    $this->preloadBlockAssets();
  }

  /**
   * Render this inputfield
   */
  public function ___render() {
    if(!$this->process instanceof ProcessPageEdit) {
      return "This field is only supported on page edit";
    }
    $page = $this->process->getPage();
    $out = "<div class='rm-items uk-margin-bottom'>"
      .$this->renderItems($page)
      ."</div>";

    // render buttons to add a new page
    $buttons = $this->renderButtons($page);
    if($buttons) {
      $out .= "<div class='rm-buttons-container'>"
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
    $out = "<textarea class='uk-hidden rm-data' name='{$this->name}'>"
      .$this->value->sleep()
      ."</textarea>";
    return $out;
  }

  public function preloadBlockAssets() {
    $page = $this->process->getPage();
    $blocks = $this->master->getAllowedBlocks($this, $page);
    $nullPage = new NullPage();
    foreach($blocks as $block) {
      $block = $this->master->getBlock($block);
      if(!$block) continue;
      if(!$tpl = $block->getTpl()) continue;
      foreach($tpl->fields as $field) {
        $field->getInputfield($nullPage)->renderReady();
      }
    }
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
    $f->collapsed = $this->getItemCollapsedState($page);
    return $f;
  }

  /**
   * Get collapsed state for given page edit inputfield
   * @return bool
   */
  public function getItemCollapsedState($page) {
    return $this->config->ajax
      ? Inputfield::collapsedNo
      : Inputfield::collapsedYes;
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
    $raw = json_decode($json);
    $new->wakeup($json);

    // get raw json data
    $rawitems = [];
    $raw = json_decode($json);
    foreach($raw->items as $v) $rawitems[$v->id] = $v;

    // process all changed items
    $itemChanged = false;
    foreach($new as $item) {
      // get raw item data
      $rawitem = $rawitems[$item->id];

      // we process all items to make sure that required state warnings
      // show up after saving the page!

      // check if item is editable by current user
      if(!$item->editable()) {
        $this->warning("Skipped item $item - not editable!");
        $new->remove($item);
        continue;
      }

      // remove trashed items
      if($rawitem->trash) {
        $item->trash();
        $new->remove($item);
      }

      // bd($rawitem, 'rawitem');

      // set changed flag if item changed
      $itemChanged = true;
      // if($new->itemChanged($item)) $itemChanged = true;
      // else continue;

      // all fine, process input
      // bd($item, 'process input');
      $f = $this->getItemInputfield($item);
      $f->processInput($input);
    }

    bd($new->each('id'), 'new ids');
    bd($new->sleep(), 'new sleep');
    $this->trackChange('value');
    $this->value->trackChange('foo');
    $this->value = $new;

    // // set new value
    // // changes will only be triggerd if the new json is different
    // // this means that changes to the matrix items do not trigger a change!
    // if(!$old->equals($new) OR $itemChanged) {
      // $this->trackChange('value');

      // trigger a change on the pagearray
      // this must be triggered manually because the pagearray does not
      // know about changes that happen on it's pages
      // a pagearray does only track added/removed events
      // $this->value->trackChange('rockmatrix-item-changed');

      // set new value
      // $this->value = $new;
    // }
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
      if($info->description) $b->attr('uk-tooltip', $info->description);
      $b->href = $this->getEndpoint("new/?block={$info->name}&page=$page&field={$field->id}");

      // fix issue https://github.com/processwire/processwire-issues/issues/1220
      $b->addHookAfter("render", function($event) {
        $out = substr($event->return, 2);
        $event->return = "<a tabindex='-1'".$out;
      });
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
