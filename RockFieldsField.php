<?php

namespace ProcessWire;

class RockFieldsField extends WireData
{

  /**
   * Add stylesheet to $config->styles
   *
   * Some predefined styles can be accessed via a single key path:
   * $field->addStyle('checkboxes');
   *
   * @return void
   */
  public function addStyle($path)
  {
    $config = $this->wire->config;

    // some predefined styles
    if ($path == 'checkboxes') {
      $path = 'wire/modules/Inputfield/InputfieldCheckboxes/InputfieldCheckboxes.css';
    }

    if (!is_file($path)) $path = $config->paths->root . ltrim($path, "/");
    if (!is_file($path)) return;
    $url = str_replace($config->paths->root, $config->urls->root, $path);
    $this->wire->config->styles->add($url);
  }

  /**
   * Shortcut for getting submitted input value of a rockfield
   * @return mixed
   */
  public function getInput($name, $type = 'text')
  {
    $fieldname = $this->name() . "_$name";
    return $this->input->get($fieldname, $type);
  }

  /**
   * Shortcut for getting submitted input value of a rockfield and returning it as array
   * @return array
   */
  public function getInputArray($name, $type = 'text')
  {
    $name = trim($name, "*"); // remove default value asterisc
    return [$name => $this->getInput($name, $type)];
  }

  /**
   * Get input of multi-language field
   *
   * Returns an array of key value pairs:
   * [
   *   'foo' => 'foo value default lang',
   *   'foo__1230' => 'foo value lang 1230',
   *   'foo__1234' => 'foo value lang 1234',
   * ]
   * @return array
   */
  public function getLanguageInput($name, $type = 'text')
  {
    $arr = [];
    $input = $this->input;
    $fieldname = $this->name() . "_$name";
    $arr[$name] = $input->get($fieldname, $type);
    foreach ($this->wire->languages->findNonDefault() as $lang) {
      $fieldname = $this->name() . "_" . $name . "__" . $lang;
      $arr[$name . "__$lang"] = $input->get($fieldname, $type);
    }
    return $arr;
  }

  /**
   * Get inputfield with given name of given type (text/options/...)
   * Shortcut for creating your fields manually
   * See comments inside this method for all available fieldtype shortcuts!!
   * @return Inputfield
   */
  public function input($name, $type = 'text', $data = [])
  {
    $values = $this->values;
    $type = strtolower($type);
    switch ($type) {

        /**
       * Checkbox
       */
      case 'checkbox':
        $f = new InputfieldCheckbox();
        $f->setArray($data);
        // if label is an empty string we add a space to prevent the
        // checkbox from rendering it's name instead of the empty label
        if ($f->label === '') $f->label = ' ';
        $f->attr('checked', $values->$name ? 'checked' : '');
        break;


        /**
         * Checkboxes
         *
         * Set options as third parameter. In your sleep method make sure to
         * set the "array" sanitizer: $field->getInputArray('demo-checkboxes', 'array'),
         *
         * Example:
         * $field->input("demo-checkboxes", "checkboxes", [
         *   '100' => 'One hundred',
         *   '200' => 'Two hundred',
         *   '300' => 'Three hundred',
         * ])
         */
      case 'checkboxes':
        $f = new InputfieldCheckboxes();
        foreach ($data as $k => $v) $f->addOption($k, $v);
        $f->value = $values->$name;
        break;

        /**
         * Radio buttons
         *
         * Set options as third parameter
         *
         * You can set a default option by adding an asterisc which will set
         * required=1 and defaultValue=xx
         *
         * Example:
         * $field->input("iconsize", "radios", [
         *   's' => 'Small Icon',
         *   '*m' => 'Medium Icon',
         *   'l' => 'Large Icon',
         * ])
         */
      case 'radios':
        $f = new InputfieldRadios();
        $f->optionColumns = 1;
        foreach ($data as $k => $v) {
          if (strpos($k, "*") !== false) {
            $k = trim($k, "*");
            $f->required = 1;
            $f->defaultValue = $k;
          }
          $f->addOption($k, $v);
        }
        $f->value = $values->$name;
        break;

        /**
         * Select field
         *
         * Set options as third parameter
         *
         * You can set a default option by adding an asterisc which will set
         * required=1 and defaultValue=xx
         *
         * Example:
         * $field->input("iconsize", "select", [
         *   's' => 'Small Icon',
         *   '*m' => 'Medium Icon',
         *   'l' => 'Large Icon',
         * ])
         */
      case 'select':
        $f = new InputfieldSelect();
        foreach ($data as $k => $v) {
          if (strpos($k, "*") !== false) {
            $k = trim($k, "*");
            $f->required = 1;
            $f->defaultValue = $k;
          }
          $f->addOption($k, $v);
        }
        $f->value = $values->$name;
        break;

        /**
         * Textarea
         */
      case 'textarea':
        $f = new InputfieldTextarea();
        $f->value = $values->$name;
        break;

        /**
         * Textarea Multilang
         */
      case 'textarealanguage':
        $f = new InputfieldTextarea();
        $f->useLanguages = true;
        $f->value = $values->$name;
        foreach ($this->wire->languages->findNonDefault() as $lang) {
          $f->set("value$lang", $values->{$name . "__$lang"});
        }
        break;

        /**
         * Text Multilang
         */
      case 'textlanguage':
        $f = new InputfieldText();
        $f->useLanguages = true;
        $f->value = $values->$name;
        foreach ($this->wire->languages->findNonDefault() as $lang) {
          $f->set("value$lang", $values->{$name . "__$lang"});
        }
        break;

        /**
         * Simple textfield input
         */
      default:
        $f = new InputfieldText();
        $f->value = $values->$name;
        break;
    }
    $f->sleepName = $name;
    $f->name = $this->name() . "_$name";
    return $f;
  }

  public function name()
  {
    return "{$this->name}_repeater{$this->forPage}";
  }

  public function shortName()
  {
    $arr = explode("_repeater", $this->name);
    return $arr[0];
  }

  /**
   * Render inputfields as uikit table
   *
   * Usage: See RockMatrix Image Block as example!
   * $field->table([
   *   'My foo label' => $foo->render(),
   *   'My bar label' => $bar->render(),
   * ]);
   *
   * @return string
   */
  public static function table($rows)
  {
    if (!$rows) return;
    $out = "<table class='uk-table uk-table-small uk-table-striped
      uk-table-middle uk-margin-remove'>";
    foreach ($rows as $label => $markup) {
      if ($markup instanceof Inputfield) $markup = $markup->render();
      $out .= "<tr style='border:0'>
          <td class='uk-text-nowrap'>$label</td>
          <td class='uk-width-expand'>$markup</td>
        </tr>";
    }
    $out .= "</table>";
    return $out;
  }
}
