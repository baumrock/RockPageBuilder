/**
 * RockPageBuilder JS for the PW backend
 * This is loaded whenever a RPB block is edited.
 */

// add support for showIf on settings
(() => {
  function updateShowIf() {
    // Find the closest table element to the event target
    // Iterate over each .rpb-setting element
    $(".rpb-setting").each(function (i, el) {
      let $form = $(el).closest(".InputfieldForm");
      const $row = $(this); // Current row
      const showIf = $row.attr("showIf"); // Get the showIf attribute value
      if (showIf) {
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
        if (targetValue == valueToMatch) $row.show();
        else $row.hide();
      } else {
        // If there's no showIf attribute, always show the row
        $row.show();
      }
    });
  }
  // monitor changes to all settings fields
  document.addEventListener("change", updateShowIf);
  $(document).ready(updateShowIf);
})();
