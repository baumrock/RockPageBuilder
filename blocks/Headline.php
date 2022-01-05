<?php namespace RMBlock;

use ProcessWire\HookEvent;
use ProcessWire\Inputfield;
use ProcessWire\Template;

class Headline extends \RockMatrix\Block {

  public function info() {
    return parent::info()->setArray([
      'icon' => 'header',
      'description' => 'Headline',
    ]);
  }

  public function init() {
    $tpl = "template=".$this->getTpl();
    $this->addHookAfter("Pages::saveReady($tpl,id=0)", $this, "onCreate");
  }

  public function buildForm($fs) {
    if($f = $fs->get('title')) {
      $f->skipLabel = Inputfield::skipLabelMarkup;
      $f->wrapClass('rmx-pd5');
      $f->attr('placeholder', 'Headline');
    }
  }

  public function getLabel() {
    return $this->title ?: $this->info()->title;
  }

  public function migrate() {
    parent::migrate();
    $this->rm()->migrate([
      'templates' => [
        $this->getTplName() => [
          'flags' => Template::flagSystem,
        ],
      ],
    ]);
    $this->rm()->setFieldData("title", ['required' => 0], $this->getTpl());
  }

  public function onCreate(HookEvent $event) {
    $page = $event->arguments(0);
    $page->title = "";
  }

  public function render() {
    return "<h2 class='rmblock-headline'>{$this->title}</h2>";
  }

}
