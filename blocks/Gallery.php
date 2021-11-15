<?php namespace RMBlock;

use ProcessWire\FieldtypeFile;
use ProcessWire\HookEvent;
use ProcessWire\RockMatrix;

class Gallery extends \RockMatrix\Block {

  const prefix = RockMatrix::prefix."gallery_";
  const tags = RockMatrix::tags;

  const field_images = self::prefix."images";

  public function info() {
    return parent::info()->setArray([
      'icon' => 'picture-o',
      'title' => 'Gallery',
      'description' => 'Insert an image gallery',
    ]);
  }

  public function init() {
    $tpl = "template=".$this->getTpl();
    $this->addHookAfter("Pages::saveReady($tpl,id=0)", $this, "onCreate");
  }

  public function buildForm($fs) {
    if($f = $fs->get('title')) {
      $f->label = 'Gallery Headline';
    }
  }

  public function getLabel() {
    return $this->title ?: $this->info()->title;
  }

  public function migrate() {
    parent::migrate();
    $this->rm()->migrate([
      'fields' => [
        self::field_images => [
          'type' => 'image',
          'maxSize' => 3, // max 3MP resolution
          'maxFiles' => 0,
          'descriptionRows' => 1, // for description
          'tags' => self::tags,
          'extensions' => "JPG JPEG PNG",
          'label' => 'Bilder',
          'icon' => 'picture-o',
          'outputFormat' => FieldtypeFile::outputFormatArray,
        ],
      ],
      'templates' => [
        $this->getTplName() => [
          'fields' => [
            'title',
            self::field_images,
          ],
        ],
      ],
    ]);
    $this->rm()->setFieldData("title", ['required' => 0], $this->getTpl());
  }

  public function onCreate(HookEvent $event) {
    $page = $event->arguments(0);
    $page->title = "Gallery";
  }

  public function render() {
    $images = $this->get(self::field_images);
    if(!$images OR !$images->count()) return;

    $out = '';
    if($this->title) $out = "<h3>{$this->title}</h3>";

    $out .= "<div class='uk-margin uk-child-width-1-2 uk-child-width-1-3@s uk-child-width-1-4@m uk-child-width-1-6@l uk-grid-small' uk-grid uk-lightbox>";
    foreach($images as $img) {
      $tag = "<img src='{$img->size(300,300)->url}' class='uk-transition-scale-up uk-transition-opaque'>";
      $caption = '';
      if($cap = $img->description) {
        $cap = $this->wire->sanitizer->entities($cap);
        $caption = " data-caption='$cap'";
        $cap = "<div class='uk-text-small'>$cap</div>";
      }
      $link = "<a href='{$img->maxSize(1920,1920)->url}'$caption>$tag</a>";
      $out .= "<div class='uk-transition-toggle'>$link{$cap}</div>";
    }
    $out .= "</div>";
    return $out;
  }

}
