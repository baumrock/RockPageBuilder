<?php namespace ProcessWire;
/**
 * @author Bernhard Baumrock, 18.07.2020
 * @license Licensed under MIT
 * @link https://www.baumrock.com
 */
class InputfieldRockPageEdit extends InputfieldMarkup {

  public $editPage;

  public static function getModuleInfo() {
    return [
      'title' => 'InputfieldRockPageEdit',
      'version' => '0.0.1',
      'summary' => 'Page Edit field for RockMatrix',
      'autoload' => false,
      'singular' => false,
      'icon' => 'edit',
      'requires' => ['RockMatrix'],
      'installs' => [],
    ];
  }

  /**
   * Render this inputfield
   */
  public function ___render() {
    $page = $this->editPage;
    $w = $this->wire(new InputfieldWrapper());
    $fs = $this->wire(new InputfieldFieldset());

    $w->add($fs);
    $fs->label = $this->getLabel($page);
    $fs->addClass('InputfieldRockPageEdit');
    $fs->import($this->getInputfields($page));
    foreach($fs->children() as $f) {
      $suffix = "_repeater$page";
      $f->name .= $suffix;

      // changes for file inputfields
      if(!$f instanceof InputfieldFile) continue;
      $f->wrapAttr('data-fnsx', $suffix);
      $f->wrapClass('InputfieldRepeaterItem');
      $f->wrapAttr('data-page', $page->id);
      $f->wrapAttr('data-type', 1);
      $f->wrapAttr('data-typeName', '');
      $f->wrapAttr('data-editUrl', $page->editUrl());
    }

    return $w->render();
  }

  /**
   * Get Inputfields Wrapper for given page (to be edited)
   *
   * @param Page $page
   * @return InputfieldWrapper
   */
  public function ___getInputfields($page) {
    $fields = $page->getInputfields()->children();
    foreach($fields as $f) {
      $type = $f->hasField->type;
      // prevent recursion
      if($type instanceof FieldtypeRockMatrix) $fields->remove($f);
      // sharing of pages not possible inside matrix
      if($type instanceof FieldtypeRockShare) $fields->remove($f);
    }
    return $fields;
  }

  /**
   * Get label for item
   */
  public function ___getLabel($page) {
    return $page->get('title|id');
  }

  /**
   * Process input
   *
   * @param WireInputData $input
   * @return $this
   *
   */
  public function ___processInput(WireInputData $input) {
    $old = (string)$this->value;
    $ids = $input->{$this->name};
    $ids = [1067];
    $items = $this->pages->getById($ids);

    // loop all repeater items
    foreach($items as $page) {
      $page->trackChanges(true);

      $form = $this->getWrapper();
      bdb($input);
      $form->processInput($input);
      if(!$form->getErrors()) {
        // recursively save all inputfields
        $this->saveChildren($page, $form->children());

        // save repeater page if changed
        if(count($page->getChanges(true))) $page->save();
      }
    }

    // save ids to database
    $new = (string)$items;
    if($old !== $new) {
      $this->trackChange('value');
      $this->value = $items;
    }
  }

  /**
   * Save all child inputfields
   * @return void
   */
  public function saveChildren($page, $children) {
    $suffix = "_repeater$page";
    foreach($children as $field) {
      if($field->children) {
        $this->saveChildren($page, $field->children);
      }
      else {
        $name = substr($field->name, 0, -strlen($suffix));
        bd($name, 'name');
        bd($field, 'field');
        if($field->useLanguages) {
          foreach($this->languages() as $lang) {
            bd($lang, 'lang');
          }
        }
        else $page->$name = $field->value;
      }
    }
  }

  /**
  * Config inputfields
  * @param InputfieldWrapper $inputfields
  */
  public function getModuleConfigInputfields($inputfields) {
    return $inputfields;
  }
}
