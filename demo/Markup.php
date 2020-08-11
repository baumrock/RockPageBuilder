<?php namespace RMDemo;
use ProcessWire\InputfieldWrapper;
class Markup extends \RockMatrix\Block {

  public function info() {
    return parent::info()->setArray([
      'icon' => 'code',
    ]);
  }

  public function render() {
    return "This block does only render custom markup";
  }

  public function buildForm(InputfieldWrapper $fs) {
    $fs = parent::___buildForm($fs);
    $fs->remove('title');
    $fs->add([
      'type' => 'markup',
      'name' => 'info',
      'value' => 'This block has no inputfields',
    ]);
    return $fs;
  }

  public function buildFormMatrix(InputfieldWrapper $fs) {
    $fs = $this->buildForm($fs);
    if($f = $fs->get('info')) {
      $edit = "<a href='{$this->editUrl}'>edit item</a>";
      $f->value = "No Inputfields ($edit)";
    }
  }

}
