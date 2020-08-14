"use strict";

function RockMatrixItem(e) {
  // save root dom element to this instance
  this.$ = this.$root(e);
}

// DOM functions

  RockMatrixItem.prototype.$root = function(e) {
    let el = e.target; // param = event
    if(!el) el = e; // param = dom element
    return $(el).closest('.rmx-item');
  }

// API

  // add trash flag
  RockMatrixItem.prototype.trash = function() {
    this.$
      .addClass('rmx-trash')
      .trigger('changed');
  }

  // remove trash flag
  RockMatrixItem.prototype.untrash = function() {
    this.$
      .removeClass('rmx-trash')
      .trigger('changed');
  }

// Helpers

  // get json ready for the textarea
  RockMatrixItem.prototype.getJSON = function() {
    let trash = this.$.hasClass('rmx-trash') ? 1 : 0;
    let changed = this.$.find('.InputfieldStateChanged').length;

    // check if the item was added on this request
    // this ensures that added items are saved at least once which triggers
    // a processInput and checks for field erros (eg empty required fields)
    let added = this.$.hasClass('rmx-added') ? 1 : 0;

    return {
      id: this.$.data('page'),
      trash,
      changed: changed+trash+added,
    };
  }
