"use strict";

function RockMatrixItem(e) {
  // save root dom element to this instance
  this.$ = this.$root(e);
}

// DOM functions

  RockMatrixItem.prototype.$root = function(e) {
    let el = e.target; // param = event
    if(!el) el = e; // param = dom element
    return $(el).closest('.rm-item');
  }

// API

  // add trash flag
  RockMatrixItem.prototype.trash = function() {
    this.$
      .addClass('rm-trash')
      .trigger('changed');
  }

  // remove trash flag
  RockMatrixItem.prototype.untrash = function() {
    this.$
      .removeClass('rm-trash')
      .trigger('changed');
  }

// Helpers

  // get json ready for the textarea
  RockMatrixItem.prototype.getJSON = function() {
    return {
      id: this.$.data('page'),
      open: this.$.hasClass('InputfieldStateCollapsed') ? 0 : 1,
      trash: this.$.hasClass('rm-trash') ? 1 : 0,
    };
  }
