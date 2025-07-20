/**
 * RockPageBuilder JS for the PW backend
 * This is loaded whenever a RPB block is edited.
 */

// add support for showIf on settings
(() => {
  function show($row, showIf) {
    // remove spaces from showIf
    showIf = showIf.trim();
    const $form = $row.closest(".InputfieldForm");

    // we found a showIf attribute
    // Split the showIf value into field name and value to match
    const [fieldToMatch, valueToMatch] = showIf.split("=");
    // find the field to match (other settings field)
    let $fieldToMatch = $form.find(`[data-name="${fieldToMatch}"] :input`);

    // if no other settings field with this name exists, try to find a
    // regular form field with this name
    if (!$fieldToMatch.length) {
      $fieldToMatch = $form.find(`[name="${fieldToMatch}"]`);
    }

    // Determine the value of the field to match, accounting for checkboxes
    let targetValue = $fieldToMatch.val();
    if ($fieldToMatch.is(":checkbox")) {
      targetValue = $fieldToMatch.is(":checked") ? "1" : "0";
    }

    // Show or hide the row based on whether the target value matches
    return targetValue == valueToMatch;
  }

  function updateShowIf() {
    // Find the closest table element to the event target
    // Iterate over each .rpb-setting element
    $(".rpb-setting").each(function (i, el) {
      const $row = $(this); // Current row

      // get the showIf condition and exit if there is none
      const showIf = $row.attr("showIf");
      if (!showIf) return $row.show();

      // use regex to find all occurrences of foo=bar, foo="bar baz", foo='bar baz'
      // replace all matches with their evaluated value as string
      // eg (rockforms_form=Reservation && member=1) || rockforms_form=Contact
      // could return: (true && false) || true
      const evaluatedShowIf = showIf.replace(
        /([a-zA-Z0-9_]+)=("([^"]*)"|'([^']*)'|([a-zA-Z0-9_]+))/g,
        (match, p1, p2, p3, p4, p5) => {
          // p1 = field name
          // p2 = full quoted string (if quoted)
          // p3 = content of double quotes (if double quoted)
          // p4 = content of single quotes (if single quoted)
          // p5 = simple value (if not quoted)
          const value = p3 || p4 || p5; // get the actual value
          return show($row, `${p1}=${value}`);
        }
      );
      // console.log("evaluatedShowIf", evaluatedShowIf);

      // toggle row based on evaluated showif string
      $row.toggle(eval(evaluatedShowIf));
    });
  }
  // monitor changes to all settings fields
  document.addEventListener("change", updateShowIf);
  document.addEventListener("input", updateShowIf);
  $(document).ready(updateShowIf);
})();
