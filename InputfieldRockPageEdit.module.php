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
    return $this->buildForm($page)->render();
  }

  /**
   * Build the form that shows the page edit for this page
   * @return InputfieldWrapper
   */
  public function ___buildForm($page) {
    $fs = $this->wire(new InputfieldFieldset());
    $form = $this->wire(new InputfieldWrapper());

    // add properties to form that are needed for buildForm hooks
    $form->suffix = "_repeater$page";

    /** @var InputfieldRepeater $r */
    $r = $this->modules->get('InputfieldRepeater');

    $form->add($fs);
    $fs->label = $this->getLabel($page);
    $fs->addClass('InputfieldRockPageEdit');
    $fs->import($this->getInputfields($page));
    foreach($fs->children() as $f) {
      $f->name .= $form->suffix;

      // changes for file inputfields
      if(!$f instanceof InputfieldFile) continue;
      $f->wrapAttr('data-fnsx', $form->suffix);
      $itemType = $r->getRepeaterItemType($page);
      $itemTypeName = $r->getRepeaterItemTypeName($itemType);
      $f->wrapClass('InputfieldRepeaterItem');
      $f->wrapAttr('data-page', $page->id);
      $f->wrapAttr('data-type', $itemType);
      $f->wrapAttr('data-typeName', $itemTypeName);
      $f->wrapAttr('data-editUrl', $page->editUrl());
    }
    return $form;
  }

  /**
   * Get Inputfields Wrapper for given page (to be edited)
   *
   * @param Page $page
   * @return InputfieldWrapper
   */
  public function ___getInputfields($page) {
    $fields = $page->getInputfields();
    foreach($fields->children() as $f) {
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
    $page = $this->editPage;
    $page->trackChanges(true);
    $form = $this->buildForm($page);
    $form->processInput($input);

    if(!$form->getErrors()) {
      // recursively save all inputfields
      $fields = $form->children();
      $this->setFieldValues($page, $fields);

      // save repeater page if changed
      if(count($page->getChanges(true))) $page->save();
    }

    // $page = $this->editPage;
    // $old = (string)$this->value;

    // // save ids to database
    // $new = (string)$items;
    // if($old !== $new) {
    //   $this->trackChange('value');
    //   $this->value = $items;
    // }
  }

  /**
   * Save all child inputfields
   * @return void
   */
  public function setFieldValues($page, $fields) {
    $suffix = "_repeater$page";
    foreach($fields as $field) {
      if($field->children) $this->setFieldValues($page, $field->children);
      else {
        $name = substr($field->name, 0, -strlen($suffix));
        // TODO support multilang
        // if($field->useLanguages) {
        //   foreach($this->languages() as $lang) {
        //     bd($lang, 'lang');
        //   }
        // }
        $page->$name = $field->value;
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
