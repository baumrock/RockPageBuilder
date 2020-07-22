<?php namespace ProcessWire;
/**
 * @author Bernhard Baumrock, 18.07.2020
 * @license Licensed under MIT
 * @link https://www.baumrock.com
 */
class InputfieldRockMatrix extends InputfieldTextarea {

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

  /**
   * Render this inputfield
   */
  public function ___render() {
    if(!$this->process instanceof ProcessPageEdit) {
      return "This field is only supported on page edit";
    }
    $page = $this->process->getPage();

    $out = '';
    foreach($page->children as $child) {
      $out .= "<a href='{$child->editUrl}'>{$child->title}</a><br>";
    }

    // render buttons to add a new page
    $buttons = $this->renderButtons($page);
    if($buttons) $out .= "<small>".__('Add content').":</small><br>$buttons";
    else $out .= $this->setupInfo('allowed-templates');

    return $out;
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
    $out = '';
    foreach($this->getAllowedTemplates($page) as $tpl) {
      $tpl = $this->wire->templates->get((string)$tpl);
      $out .= $this->getTemplateButton($tpl, $page);
    }
    return $out;
  }

  /**
   * Get button to add a new page having this template
   */
  public function ___getTemplateButton($tpl, $page) {
    $field = $this->hasField;

    /** @var InputfieldButton $b */
    $b = $this->wire('modules')->get('InputfieldButton');
    $b->secondary = true;
    $b->small = true;
    if($tpl) {
      $b->value = $tpl->get('label|name');
      $b->icon = $tpl->icon;
      $b->href = $this->getEndpoint("new/?page=$page&field=$field&tpl=$tpl");
    }
    else {
      // no template found
      if(!$this->config->debug) return;
      $b->icon = "exclamation-triangle";
      $b->value = "TPL not found";
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
   * Get allowed templates for the edited page
   * @return array
   */
  public function ___getAllowedTemplates($page) {
    return [];
  }

  /**
  * Config inputfields
  * @param InputfieldWrapper $inputfields
  */
  public function getModuleConfigInputfields($inputfields) {
    return $inputfields;
  }
}
