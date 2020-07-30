"use strict";

function RockMatrix() {
  this.init = false;
  this.changeTimer;
}

// DOM helpers

  // return the field's root element
  RockMatrix.prototype.$root = function(e) {
    let el = e.target; // param = event
    if(!el) el = e; // param = dom element
    return $(el).closest('.InputfieldRockMatrix');
  }

  // return all item's list elements
  RockMatrix.prototype.$items = function(e) {
    return this.$root(e).find('.rm-items > ul > li.rm-item');
  }

  // return the items container
  RockMatrix.prototype.$itemsContainer = function(e) {
    return this.$root(e).find('.rm-items');
  }

  // textarea holding field data
  RockMatrix.prototype.$textarea = function(e) {
    return this.$root(e).find('textarea.rm-data');
  }

// helpers

  RockMatrix.prototype.addItem = function(e, json) {
    let $root = this.$root(e);
    let $container = this.$itemsContainer(e);

    // add empty list element
    let $item = $(json.markup);
    $container.append($item);
    this.initItem($item);

    // trigger change
    $root.trigger('changed');
  }

  RockMatrix.prototype.fire = function($action) {
    let action = $action.data('action');
    let item = this.getItem($action[0]);

    if(action === 'trash') item.trash();
    if(action === 'untrash') item.untrash();
  }

  RockMatrix.prototype.getData = function(e) {
    let data = {}
    data.items = RockMatrix.getItems(e, true);
    return data;
  }

  RockMatrix.prototype.getItem = function(e) {
    return new RockMatrixItem(e);
  }

  RockMatrix.prototype.getItems = function(e, json) {
    let items = [];
    let rm = this;
    $.each(RockMatrix.$items(e), function(i, el) {
      let item = rm.getItem(el);
      if(json) items.push(item.getJSON());
      else items.push(item);
    });
    return items;
  }

  RockMatrix.prototype.initItem = function($item) {
    InputfieldsInit($item); // init inputfield
    $item.find('.InputfieldHasFileList').trigger('reloaded'); // init file fields
  }

  RockMatrix.prototype.makeSortable = function(e) {
    let $container = this.$itemsContainer(e);

    // init uikit sortable on container
    UIkit.sortable($container[0], {
      // longer animation duration prevents flicker
      animation: 500,

      // set the handle to the header
      // this ensures that other drag&drop features don't break (eg images)
      handle: '.InputfieldHeader',
    });

    // add class to every sortable element
    // to make it addressable via css
    let $items = this.$items(e);
    if($items.length) {
      $container.removeClass('uk-hidden');
      $.each($items, function(i, el) {
        $(el).parent().addClass('rm-draggable');
      });
    }
    else {
      $container.addClass('uk-hidden');
    }
  }

  RockMatrix.prototype.setTextarea = function(e) {
    let $text = this.$textarea(e);
    $text.val(JSON.stringify(this.getData(e))).change();
  }

  RockMatrix.prototype.spin = function($el, _cls) {
    let cls = _cls || '';
    let $i = $el.find('i.fa').first();
    $i.data('tmpcls', $i.attr('class'));
    $i.attr('class', "fa fa-spinner fa-spin "+cls);
  }

  RockMatrix.prototype.unspin = function($el) {
    let $i = $el.find('i.fa').first();
    $i.attr('class', $i.data('tmpcls'));
    $i.removeAttr('tmpcls');
  }

// event handlers

  // change triggerd
  RockMatrix.prototype.changed = function(e) {
    let rm = this;
    clearTimeout(rm.changeTimer);
    // 10ms debounce for all changes
    rm.changeTimer = setTimeout(function() {
      rm.makeSortable(e);
      rm.setTextarea(e);
      console.log(rm.init ? 'RM changed' : 'RM init');
      rm.init = true;
    }, 5);
  }

  // click on add new item button
  RockMatrix.prototype.clickAdd = function(e) {
    e.preventDefault();

    // get link
    let $a = $(e.target).closest('a');
    let href = $a.attr('href');
    let rm = this;

    // prevent double-click
    if($a.find("i.fa-spin").length) {
      console.log('prevent double click');
      return;
    }

    // show spinner
    rm.spin($a);

    // send ajax request
    $.getJSON(href, function(json) {
      if(json.error) ProcessWire.alert(json.message);
      else rm.addItem(e, json);
    }).fail(function(json) {
      ProcessWire.alert('AJAX Error');
    }).always(function() {
      rm.unspin($a);
    });
  }

  // init
  RockMatrix.prototype.initialize = function(e) {
    this.$root(e).trigger('changed');
  }

var RockMatrix = new RockMatrix();

// listeners

  // init the matrix
  $(document).on('init', '.InputfieldRockMatrix', function(e) {
    RockMatrix.initialize(e);
  });

  // add a new matrix item
  $(document).on('click', '.InputfieldRockMatrix .rm-buttons a', function(e) {
    RockMatrix.clickAdd(e);
  });

  // change event triggered on root element
  $(document).on('changed', '.InputfieldRockMatrix', function(e) {
    RockMatrix.changed(e);
  });

  // items sort oder changed
  $(document).on('stop', '.rm-items', function(e) {
    RockMatrix.changed(e);
  });

  // monitor all inputfields in a rockmatrix field
  $(document).on('change', '.rm-items input, .rm-items textarea', function(e) {
    setTimeout(function() { RockMatrix.changed(e); });
  });

  // monitor inputfield toggles
  $(document).on('opened closed', '.Inputfield.rm-item', function(e) {
    RockMatrix.changed(e);
  });

  // monitor action clicks
  $(document).on('click', '.rm-action', function(e) {
    let $action = $(e.target).closest('.rm-action');
    RockMatrix.fire($action);

    // dont toggle field
    e.preventDefault();
    return false;
  });
