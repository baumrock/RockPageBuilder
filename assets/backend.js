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

      // if no AND or OR is found, show the row
      if (!showIf.includes(" AND ") && !showIf.includes(" OR ")) {
        const isVisible = show($row, showIf);
        $row.toggle(isVisible);
        return;
      }

      if (showIf.includes(" AND ") && showIf.includes(" OR ")) {
        console.error("showIf with both AND and OR is not supported");
        return;
      }

      if (showIf.includes(" AND ")) {
        const partsAND = showIf.split(" AND ");
        const isVisible = partsAND.every((part) => show($row, part));
        $row.toggle(isVisible);
        return;
      }

      if (showIf.includes(" OR ")) {
        const partsOR = showIf.split(" OR ");
        const isVisible = partsOR.some((part) => show($row, part));
        $row.toggle(isVisible);
        return;
      }

      // fallback: hide the row
      $row.hide();
    });
  }
  // monitor changes to all settings fields
  document.addEventListener("change", updateShowIf);
  $(document).ready(updateShowIf);
})();
