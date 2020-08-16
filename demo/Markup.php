<?php namespace RMDemo;
use ProcessWire\HookEvent;
use ProcessWire\InputfieldWrapper;
class Markup extends \RockMatrix\Block {

  public function info() {
    return parent::info()->setArray([
      'icon' => 'code',
    ]);
  }

  public function init() {
    // hook demo
    $this->addHookAfter("Pages::saveReady", $this, "saveReady");
  }

  public function render() {
    return "This block does only render custom markup";
  }

  public function buildForm(InputfieldWrapper $fs) {
    $fs->remove('title');
    $fs->add([
      'type' => 'markup',
      'name' => 'info',
      'value' => 'This block has no inputfields',
    ]);
  }

  public function buildFormMatrix(InputfieldWrapper $fs) {
    // apply the regular buildForm for page edit
    $this->buildForm($fs);

    // and then add a link to edit this page
    $f = $fs->get('info');
    $f->value .= " <a href='{$this->editUrl}' class='uk-margin-left'>
      <i class='fa fa-edit'></i> edit</a>";
  }

  public function saveReady(HookEvent $event) {
    $page = $event->arguments(0);
    if($page->template !== $this->getTpl()) return;
    if(!$page->id) {
      $page->title = "Markup Block";
      $page->name = $this->wire->pages->names()->uniqueRandomPageName(3);
    }
  }

}
