<?php namespace RMBlock;

use ProcessWire\HookEvent;
use ProcessWire\InputfieldCheckbox;
use ProcessWire\InputfieldRadios;
use ProcessWire\InputfieldText;
use ProcessWire\RockFieldInput;
use ProcessWire\RockMatrix;

class Image extends \RockMatrix\Block {

  const prefix = RockMatrix::prefix."image_";
  const tags = RockMatrix::tags;

  const field_image = self::prefix."images";
  const field_options = self::prefix."options";

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
    $this->addRockFields();
  }

  public function addRockFields() {
    // options field
    if(!$this->wire->rockfields) return;
    $this->wire->rockfields->add([
      'name' => self::field_options,
      'inputfield' => function($field, $values) {
        $name = $field->name();

        $text = new InputfieldText();
        $text->name = $name."_text";
        $text->value = $values->text;

        $sizes = new InputfieldRadios();
        $sizes->name = $name."_size";
        $sizes->addOptions([
          'small' => 'Klein',
          'medium' => 'Mittel',
          'large' => 'Groß',
        ]);
        $sizes->value = $values->size ?: 'small';
        $sizes->optionColumns = 1;

        $align = new InputfieldRadios();
        $align->name = $name."_align";
        $align->addOptions([
          'left' => 'Links',
          'center' => 'Zentriert',
          'right' => 'Rechts',
        ]);
        $align->value = $values->align ?: 'left';
        $align->optionColumns = 1;

        $float = new InputfieldCheckbox();
        $float->name = $name."_float";
        $float->attr('checked', $values->float ? 'checked' : '');
        $float->label = ' ';

        $link = new InputfieldCheckbox();
        $link->name = $name."_link";
        $link->attr('checked', $values->link OR $values->link===null ? 'checked' : '');
        $link->label = ' ';

        return [
          'label' => false,
          'icon' => 'bolt',
          'value' => $field->table([
            'Beschriftung / Copyright' => $text->render(),
            'Bildgröße' => "<div class='InputfieldRadios'>".$sizes->render()."</div>",
            'Ausrichtung' => "<div class='InputfieldRadios'>".$align->render()."</div>",
            'Text umfließt Bild' => $float->render(),
            'Großes Bild verlinken' => $link->render(),
          ]),
        ];
      },
      'sleep' => function($field, RockFieldInput $input) {
        $name = $field->name();
        return [
          'text' => $input->get($name."_text"),
          'size' => $input->get($name."_size"),
          'align' => $input->get($name."_align"),
          'float' => $input->get($name."_float", 'bool'),
          'link' => $input->get($name."_link", 'bool'),
        ];
      },
    ]);
  }

  public function buildForm($fs) {
    $fs->remove('title');

    if($this->wire->rockfields) {
      $fs->add($this->wire->rockfields
        ->getInputfield($this, self::field_options));
    }
  }

  public function getLabel() {
    return $this->title ?: $this->info()->title;
  }

  public function migrate() {
    parent::migrate();
    $this->rm()->deleteField('rockmatrix_image_label');
    $this->rm()->migrate([
      'fields' => [
        self::field_image => [
          'type' => 'image',
          'maxSize' => 3, // max 3MP resolution
          'maxFiles' => 1,
          'descriptionRows' => 0,
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
    $page = $this;
    $image = $page->get(self::field_image);
    if(!$image) return;
    $sanitizer = $this->wire->sanitizer;

    $opt = $page->rockfieldValue(self::field_options);
    $size = 200;
    if($opt->size == 'medium') $size = 400;
    elseif($opt->size == 'large') $size = 600;

    $align = "align-".$opt->align;

    $label = $opt->text;
    $alt = $sanitizer->entities($label ?: $image->basename);
    if($label) $label = "<div class='label uk-text-small'>$label</div>";

    $img = "<img data-src='{$image->maxSize($size,$size)->url}' alt='$alt' uk-img>";
    if($opt->link) {
      $cap = $sanitizer->entities($opt->text);
      if($cap) $cap = " data-caption='$cap'";
      $img = "<a href='{$image->maxSize(1600,1600)->url}'$cap
        data-barba-prevent>$img</a>";
    }

    $float = '';
    if($opt->float) {
      if($opt->align != 'center') $float = 'float-'.$opt->align;
    }

    return "<div class='rmblock-image $align size-{$opt->size} $float'>
      <div class='container' uk-lightbox>$img $label</div>
      </div>";
  }

}
