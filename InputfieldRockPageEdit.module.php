<?php namespace ProcessWire;
/**
 * @author Bernhard Baumrock, 18.07.2020
 * @license COMMERCIAL DO NOT DISTRIBUTE
 * @link https://www.baumrock.com
 */
class InputfieldRockPageEdit extends InputfieldMarkup {

  /**
   * The page being edited in this inputfield
   * This is NOT the parent page edited in ProcessPageEdit
   */
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
    $fs->rmitem = $page->id;
    $fs->id = "rpe_$page";
    $fs->label = $this->getLabel($page);
    $fs->icon = $page->info()->icon;
    $fs->notes = $page->info()->description;
    $fs->class = $this->class; // if any classes where set add them now
    $fs->wrapAttr('data-page', $page->id);
    $fs->addClass('InputfieldRockPageEdit');
    $fs->import($this->getInputfields($page));

    // set collapsed state
    // is set in InputfieldRockMatrix::getItemInputfield
    $fs->collapsed = $this->getCollapsedState();

    // set additional markup
    $fs->setMarkup([
      "id={$fs->id}" => $this->getMarkupArray($fs),
    ]);

    foreach($fs->children() as $f) {
      $f->name .= $form->suffix;

      // open wrapper if field has an error
      if(count($f->getErrors())) $fs->collapsed = Inputfield::collapsedNo;

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
   * Get markup string for wrapper
   * @return string
   */
  public function getMarkupArray($wrapper) {
    $markup = $wrapper->getMarkup();

    // actions
    $markup['item_label'] = str_replace(
      "{out}",
      "{out}".$this->renderActions($wrapper),
      $markup['item_label']
    );

    return $markup;
  }

  /**
   * Render actions for this item
   */
  public function renderActions($wrapper) {
    $page = $this->wire->pages->get($wrapper->rmitem);
    $out = "<span class='rm-actions'>";
    $out .= $this->renderAction('trash', [
      'label' => __('Mark for deletion'),
    ]);
    $out .= $this->renderAction('untrash', [
      'label' => __('Undo deletion'),
      'icon' => 'undo',
    ]);
    $out .= "</span>";
    return $out;
  }


  public function renderAction($action, $data) {
    $opt = $this->wire(new WireData()); /** @var WireData $opt */
    $opt->setArray($data);
    $icon = $opt->icon ?: $action;
    return
      "<a href='#'
        class='rm-action rm-action-$action'
        uk-tooltip='{$opt->label}'
        data-action='$action'>
        <i class='fa fa-$icon'></i>"
      ."</a>";
  }

  /**
   * Get collapsed state of matrix item
   */
  public function getCollapsedState() {
    return $this->collapsed;
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
