<?php namespace RMBlock;
use ProcessWire\HookEvent;
use ProcessWire\InputfieldWrapper;
use ProcessWire\RockMatrix;
use ProcessWire\Template;

class Children extends \RockMatrix\Block {

  const prefix = RockMatrix::prefix."children_";
  const tags = RockMatrix::tags;

  const field_levels = self::prefix."levels";

  public function info() {
    return parent::info()->setArray([
      'title' => 'Unterseiten',
      'icon' => 'sitemap',
      'description' => 'Fügt eine Übersicht der Unterseiten ein.',
    ]);
  }

  public function init() {
    $tpl = "template=".$this->getTpl();
    $this->addHookAfter("Pages::saveReady($tpl,id=0)", $this, "onCreate");
  }

  public function buildForm(InputfieldWrapper $fs) {
    if($f = $fs->get('title')) {
      $f->label = 'Überschrift';
      $f->required = false;
      $f->notes = "Hier kann eine Überschrift angegeben werden, die über der Liste mit den Unterseiten angezeigt wird.";
    }
  }

  public function getLabel() {
    return $this->title ?: $this->info()->title;
  }

  public function migrate() {
    parent::migrate();
    $this->rm()->migrate([
      'fields' => [
        self::field_levels => [
          'type' => 'integer',
          'tags' => self::tags,
          'label' => $this->_('Levels'),
          'notes' => $this->_('Maximum Number of levels to show (0 = unlimited)'),
          'columnWidth' => 30,
          'inputType' => 'number',
          'size' => 1,
          'min' => 0,
          'max' => 9,
          'icon' => 'sitemap',
        ],
      ],
      'templates' => [
        $this->getTplName() => [
          'fields' => [
            'title',
            self::field_levels,
          ],
          'noSettings' => 0,
          'noChildren' => 1,
          'flags' => Template::flagSystem,
        ],
      ],
    ]);
    $this->rm()->setFieldData("title", ['columnWidth' => 70], $this->getTpl());
  }

  public function render() {
    $out = '';

    if($this->title) $out .= "<h3>{$this->title}</h3>";

    $out .= "<ul class='rmx-children'>";
    $out .= $this->renderChildren($this->getMatrixPage());
    $out .= "</ul>";

    return $out;
  }

  /**
   * Render children of page
   * @return string
   */
  public function renderChildren($parent, $level = 1) {
    $maxLevel = $this->get(self::field_levels) ?: 99;

    $out = '';
    foreach($parent->children() as $page) {
      $out .= "<li><a href='{$page->url}'>{$page->title}</a></li>";
      if($page->numChildren() AND $level < $maxLevel) {
        $out .= "<ul>".$this->renderChildren($page, $level+1)."</ul>";
      }
    }
    return $out;
  }

  public function onCreate(HookEvent $event) {
    $page = $event->arguments(0);
    $page->title = "";
    $page->set(self::field_levels, 0);
    $page->name = uniqid();
  }

  public function searchIndex() {
    $out = '';
    foreach($this->getMatrixPage()->children() as $page) {
      $out .= "* {$page->title}\n";
    }
    return $out;
  }

  public function uninstall() {
    parent::uninstall();
  }
}
