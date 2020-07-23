function RockMatrix() {
}

// DOM helpers

  // return the field's root element
  RockMatrix.prototype.$root = function(e) {
    let $el = $(e.target);
    return $el.closest('.InputfieldRockMatrix');
  }

// event handlers

  // click on add new item button
  RockMatrix.prototype.clickAdd = function(e) {
    e.preventDefault();

    // get link
    let $a = $(e.target).closest('a');
    let href = $a.attr('href');

    // send ajax request
    $.get(href, function(html) {
      alert(html);
    });
  }

var RockMatrix = new RockMatrix();

// listeners
$(document).on('click', '.InputfieldRockMatrix .rm-buttons a', RockMatrix.clickAdd)
