<?php

namespace ProcessWire;

use RockPageBuilder\BlockSettingsArray;

/**
 * @author Bernhard Baumrock, 18.11.2021
 * @license COMMERCIAL DO NOT DISTRIBUTE
 * @link https://www.baumrock.com
 */
class RockFields extends WireData implements Module
{

  /** @var WireArray */
  public $fields;

  public $path;

  public static function getModuleInfo()
  {
    return [
      'title' => 'RockFields',
      'version' => '2.0.0',
      'summary' => 'ProcessWire module to define runtime fields with JSON storage',

      // set autoload priority
      // needs to be more than RockPageBuilder (90)
      // it is also more than default (true=100) so that regular autoload
      // modules can access rockfields in their init() method
      'autoload' => 150,

      'singular' => true,
      'icon' => 'code',
      'requires' => [],
      'installs' => [],
    ];
  }

  public function __construct()
  {
    $this->fields = $this->wire(new WireArray());
    $this->path = $this->wire->config->paths($this);
  }

  public function init()
  {
    $this->wire('rockfields', $this);
    $this->addHookAfter("Inputfield::processInput", $this, "processInput");
    $this->addHookMethod("Page::rockfieldValue", $this, "rockfieldValue");
  }

  /**
   * Add field to rockfields
   *
   * Usage:
   * $rockfields->add([
   *   'inputfield' => function(...),
   *   'sleep' => function(...)
   * ]);
   */
  public function add($data)
  {
    require_once("RockFieldsField.php");
    $field = $this->wire(new RockFieldsField());
    $field->setArray($data);
    $this->fields->add($field);

    // if addAfter or addBefore was set we attach the hook that adds the
    // field to ProcessPageEdit::buildForm
    /**
     * Usage:
     * $rf->add([
     *   'name' => ...,
     *   // add field after title field on home template
     *   'addAfter' => ['title', 'template=home'],
     * ]);
     */
    if ($field->addAfter or $field->addBefore) {
      $this->wire->addHookAfter("ProcessPageEdit::buildForm", function ($event) use ($field) {
        if ($data = $field->addAfter) $type = 'After';
        elseif ($data = $field->addBefore) $type = 'Before';

        $page = $event->object->getPage();
        $selector = "id>0";
        if (count($data) > 1) $selector = $data[1] ?: "id>0";
        if (!$page->matches($selector)) return;

        $form = $event->return;
        $existingItem = $form->get($data[0]);
        if (!$existingItem) return;
        $inputfield = $this->getInputfield($page, $field->name);
        $method = "insert$type";
        $form->$method($inputfield, $existingItem);
      });
    }

    // if the field has a property "add" we add it to the given template
    // a value of TRUE means that we add the field to all forms
    /**
     * Usage:
     * $rf->add([
     *   'name' => ...,
     *   'add' => 'template=home', // add field to buildFormContent of home
     * ]);
     */
    if ($field->add === true) $field->add = " ";
    if ($field->add instanceof Template) $field->add = "template={$field->add}";
    if (is_string($field->add)) {
      $this->wire->addHookAfter("ProcessPageEdit::buildFormContent", function ($event) use ($field) {
        $page = $event->object->getPage();
        $selector = $field->add . ",id>0";
        if (!$page->matches($selector)) return;
        $event->return->add($this->getInputfield($page, $field->name));
      });
    }

    // if a buildForm callback was provided we add the hook now
    if ($callback = $field->buildForm) {
      $this->wire->addHookAfter("ProcessPageEdit::buildForm", function ($event) use ($callback) {
        $callback->__invoke($event);
      });
    }
  }

  /**
   * @return Inputfield
   */
  public function getInputfield($page, $name, $returnFalse = false)
  {
    $f = $this->wire(new InputfieldMarkup);
    $f->setArray([
      'name' => $name,
      'label' => 'RockFields',
      'value' => "RockFields field $name not found",
    ]);

    $field = $this->fields->get("name=$name");
    if (!$field) return $returnFalse ? false : $f;

    // save the page reference to the field object
    $field->forPage = $page;

    // get values from meta data
    $values = $this->wire(new WireData());
    /** @var WireData $values */
    $values->setArray($page->meta("rockfield-" . $field->name) ?: []);

    // inputfield callback
    $field->values = $values; // save values to field instance
    $callback = $field->inputfield;
    $inputfield = '';
    if (is_array($callback)) {
      // inputfield is provided as array, this can mean 2 things
      // 1: ['type'=>'markup','label'=>'foo','value'=>'bar']
      // 2: [$object, "method"]

      // check for option 1
      if ($this->isInputfield($callback)) {
        // we got a regular inputfield as array syntax (markup-only field)
        $inputfield = $callback;
      } else {
        // callback is provided as array [$object, "method"]
        // allows to provide rockfields in classes (eg RockMatrix)
        $obj = $callback[0];
        $method = $callback[1];

        if ($method == 'settingsTable') {
          // special method of RockPageBuilder
          $settings = $obj->$method($field);
          if ($settings instanceof BlockSettingsArray) {
            $rows = new WireArray();
            $arr = [];
            $prop = "label";
            if ($lang = $this->wire->user->language and !$lang->isDefault()) {
              $prop = "label$lang|label";
            }
            // get multilang labels and values for all settings
            foreach ($settings as $item) {
              $item->label = $item->get($prop);
              $rows->add($item);
            }
            $settings = $arr;
            $inputfield = RockFieldsField::table($rows);
          } else {
            $inputfield = RockFieldsField::table($settings);
          }
        } else $inputfield = $obj->$method($field);
        $f->skipLabel = Inputfield::skipLabelBlank;
      }
    } elseif (is_callable($callback)) {
      $inputfield = $callback($field);
      $f->skipLabel = Inputfield::skipLabelBlank;
    }

    if (!$inputfield) return false;

    // set inputfield attributes
    if (is_string($inputfield)) {
      // the inputfield is a string
      // this means only the value property was provided (for shorter syntax)
      $inputfield = [
        'type' => 'markup',
        'label' => $this->_('Settings'),
        'icon' => 'cogs',
        // show settings field by default (rockmatrix)
        'collapsed' => Inputfield::collapsedNo,
        'value' => $inputfield,
      ];
    }
    if (is_array($inputfield)) $f->setArray($inputfield);

    return $f;
  }

  /**
   * Is the array an inputfield?
   * @return bool
   */
  public function isInputfield($array)
  {
    foreach ($array as $k => $v) {
      if (!is_string($k)) return false;
    }
    return true;
  }

  /**
   * Prepare data array for storage
   * See https://i.imgur.com/UZbgqlD.png
   * @return array
   */
  public function prepareData(array $data)
  {
    foreach ($data as $k => $v) {
      if (is_int($k) and is_array($v)) {
        // data was provided as array, eg from language input
        // [0 => ['demo'=>'demo DE', 'demo__1230'=>'demo EN']]
        $values = $v;
        unset($data[$k]);
        foreach ($values as $vk => $vv) $data[$vk] = $vv;
      }
    }
    return $data;
  }

  /**
   * Hook inputfield saving
   * @return void
   */
  public function processInput(HookEvent $event)
  {
    if ($event->process != 'ProcessPageEdit') return;

    $field = $event->object;
    $arr = explode("_repeater", $field->name);

    // get field name and page id from foofield_repeater123 syntax
    $fieldname = $arr[0];
    if (count($arr) === 2) $page = $event->pages->get($arr[1]);
    else $page = $event->process->getPage();

    // try to get RockFields field
    $rockfield = $this->fields->get($fieldname);
    if (!$rockfield) return;
    $rockfield->forPage = $page;
    // bd($rockfield, 'rockfield');

    // magic field found, process input
    require_once("RockFieldInput.php");
    $input = $this->wire(new RockFieldInput());
    /** @var RockFieldInput $input */
    $input->setArray($event->arguments(0)->getArray());

    // get the field's sleep callback
    $sleep = $rockfield->sleep;

    // store input on the field object to make it available inside the callback
    $rockfield->input = $input;
    if (count($input) > ini_get('max_input_vars')) {
      $field->error($this->_('max_input_vars limit reached - some data might not have been saved'));
    }

    // execute the callback and provide field as parameter
    if (!$sleep) $data = [];
    elseif (is_array($sleep) and count($sleep) === 2) {
      // callback is provided as array of [$object, "method"]
      // this makes it possible to provide rockfields in classes (RockMatrix)
      $obj = $sleep[0];
      $method = $sleep[1];
      $data = $obj->$method($rockfield);
    } else $data = $sleep($rockfield);

    if (!$data) return;
    $data = $this->prepareData($data);
    $this->save($rockfield, $data);
  }

  /**
   * Get the values of a rockfield
   *
   * Usage: $page->rockfieldValue("myField")->foo
   *
   * The values will be provided in a language agnostig way. If you don't like
   * that behaviour you can set the second parameter to FALSE:
   * $page->rockfieldValue("myField", false)
   */
  public function rockfieldValue(HookEvent $event)
  {
    $page = $event->object;
    $fieldname = $event->arguments(0);
    $field = $this->fields->get($fieldname);
    if (!$field) throw new WireException("Field $fieldname not found");

    $data = $this->wire(new WireData());
    /** @var WireData $data */
    $raw = $page->meta("rockfield-$fieldname") ?: [];
    $data->setArray($raw);

    // populate properties from non-default language?
    // usually language values are stored in the property with a language id suffix
    // that means for the foo property it would be "foo" (default language)
    // and "foo__123" (for language 123).
    $useLang = $event->arguments(1);
    if ($useLang !== false and $this->wire->languages) {
      if (!$this->wire->user->language->isDefault()) {
        $lang = $this->wire->user->language;
        foreach ($raw as $k => $v) {
          if (!strpos($k, "__$lang")) continue;
          $k_nolang = str_replace("__$lang", "", $k);
          if ($v) $data->set($k_nolang, $v);
        }
      }
    }

    $event->return = $data;
  }

  /**
   * Save json data to page meta data
   * @return void
   */
  public function save($field, $json)
  {
    $page = $field->forPage;
    if ($this->wire->config->debug and $this->wire->user->isSuperuser()) {
      if (function_exists("bd")) bd($json, "sleeping... page=$page, field={$field->name}");
    }
    if (!$page->editable()) throw new WireException("You are not allowed to edit page $page");
    $page->meta("rockfield-" . $field->shortName(), $json);
  }

  /**
   * Config inputfields
   * @param InputfieldWrapper $inputfields
   */
  public function getModuleConfigInputfields($inputfields)
  {
    $name = strtolower($this);
    $inputfields->add([
      'type' => 'markup',
      'label' => 'Documentation & Updates',
      'icon' => 'life-ring',
      'value' => "<p>Hey there, coding rockstars! 👋</p>
        <ul>
          <li><a class=uk-text-bold href=https://www.baumrock.com/modules/$name/docs>Read the docs</a> and level up your coding game! 🚀💻😎</li>
          <li><a class=uk-text-bold href=https://www.baumrock.com/rock-monthly>Sign up now for our monthly newsletter</a> and receive the latest updates and exclusive offers right to your inbox! 🚀💻📫</li>
          <li><a class=uk-text-bold href=https://github.com/baumrock/$name>Show some love by starring the project</a> and keep me motivated to build more awesome stuff for you! 🌟💻😊</li>
          <li><a class=uk-text-bold href=https://paypal.me/baumrockcom>Support my work with a donation</a>, and together, we'll keep rocking the coding world! 💖💻💰</li>
        </ul>",
    ]);
  }

  public function __debugInfo()
  {
    return [
      'fields' => $this->fields,
    ];
  }
}
