"use strict";

function RockMatrixItem(el) {
  // save root dom element to this instance
  this.$ = this.$root(el);
}

// DOM functions

  RockMatrixItem.prototype.$root = function(el) {
    return $(el).closest('.rm-item');
  }

// Helpers

  // get json ready for the textarea
  RockMatrixItem.prototype.getJSON = function() {
    return {
      id: this.$.data('page'),
      open: !this.$.hasClass('InputfieldStateCollapsed'),
    };
  }
