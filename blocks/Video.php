<?php namespace RMBlock;

use ProcessWire\HookEvent;
use ProcessWire\Inputfield;

class Video extends \RockMatrix\Block {

  public function info() {
    return parent::info()->setArray([
      'icon' => 'video-camera',
      'description' => 'Embed a video',
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
      $f->attr('placeholder', 'Video URL');
    }
  }

  public function getLabel() {
    return $this->title ?: $this->info()->title;
  }

  public function migrate() {
    parent::migrate();
    $this->rm()->setFieldData("title", ['required' => 0], $this->getTpl());
  }

  public function onCreate(HookEvent $event) {
    $page = $event->arguments(0);
    $page->title = "";
  }

  public function render() {
    $video = $this->wire->modules->get('TextformatterVideoEmbed');
    if(!$video) return "Install TextformatterVideoEmbed
      <a href='{$this->wire->pages->get(2)->url}module/#tab_new_modules'>modules</a>
      <a href='https://github.com/ryancramerdesign/TextformatterVideoEmbed/archive/refs/heads/master.zip'>ZIP</a>";
    $url = $this->title;
    $video->format($url);
    return $url;
  }

}
