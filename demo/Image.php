<?php namespace RMDemo;
class Image extends \RockMatrix\Block {

  const prefix = "rmdemo_image_";
  const field_img = self::prefix."image";

  public function info() {
    return parent::info()->setArray([
      'icon' => 'picture-o',
    ]);
  }

  public function migrate() {
    parent::migrate();
    $this->rm()->migrate([
      'fields' => [
        self::field_img => [
          'type' => 'image',
          'label' => 'Image Demo',
          'tags' => self::tags,
          'icon' => $this->info()->icon,
          'extensions' => 'jpg jpeg png',
          'maxFiles' => 1,
        ],
      ],
      'templates' => [
        $this->getTplName() => [
          'fields' => [
            'title',
            self::field_img,
          ],
        ],
      ],
    ]);
  }

  public function uninstall() {
    parent::uninstall();
    $this->rm()->deleteField(self::field_img);
  }
}
