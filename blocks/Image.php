<?php namespace RMBlock;

use ProcessWire\HookEvent;
use ProcessWire\RockMatrix;

class Image extends \RockMatrix\Block {

  const prefix = RockMatrix::prefix."image_";
  const tags = RockMatrix::tags;

  const field_image = self::prefix."images";

  public function info() {
    return parent::info()->setArray([
      'icon' => 'picture-o',
      'title' => 'Image',
      'description' => 'Insert a single image',
    ]);
  }

  public function init() {
    $tpl = "template=".$this->getTpl();
    $this->addHookAfter("Pages::saveReady($tpl,id=0)", $this, "onCreate");
  }

  public function buildForm($fs) {
    $fs->remove('title');
  }

  public function getLabel() {
    return $this->title ?: $this->info()->title;
  }

  public function migrate() {
    parent::migrate();
    $this->rm()->migrate([
      'fields' => [
        self::field_image => [
          'type' => 'image',
          'maxSize' => 3, // max 3MP resolution
          'maxFiles' => 1,
          'descriptionRows' => 1, // for copyright
          'tags' => self::tags,
          'extensions' => "JPG JPEG PNG GIF",
          'label' => 'Bild',
          'icon' => 'picture-o',
        ],
      ],
      'templates' => [
        $this->getTplName() => [
          'fields' => [
            'title',
            self::field_image,
          ],
        ],
      ],
    ]);
    $this->rm()->setFieldData("title", ['required' => 0], $this->getTpl());
  }

  public function onCreate(HookEvent $event) {
    $page = $event->arguments(0);
    $page->title = "Image";
  }

  public function render() {
    $image = $this->get(self::field_image);
    if(!$image) return;
    return "<div class='rmblock-image'><img src='{$image->url}'></div>";
  }

}
