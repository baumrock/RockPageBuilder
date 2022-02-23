"use strict";

function RockMatrix() {
  this.editdelay = 5;
  this.submitdelay = this.editdelay+20;

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

  // get the item (block) element
  RockMatrix.prototype.$item = function(e) {
    let el = e.target; // param = event
    if(!el) el = e; // param = dom element
    return $(el).closest('.rmx-item');
  }

  // return all item's list elements
  RockMatrix.prototype.$items = function(e) {
    return this.$root(e).find('.rmx-items > ul > li.rmx-item');
  }

  // return the items container
  RockMatrix.prototype.$itemsContainer = function(e) {
    return this.$root(e).find('.rmx-items');
  }

  // textarea holding field data
  RockMatrix.prototype.$textarea = function(e) {
    return this.$root(e).find('textarea.rmx-data');
  }

// helpers

  RockMatrix.prototype.addItem = function(e, json) {
    let $root = this.$root(e);
    let $container = this.$itemsContainer(e);

    // add item to container
    let $item = $(json.markup);
    $container.append($item);
    $item.find('.rmx-item').addClass('rmx-added');
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
    return RockMatrix.getItems(e, true);
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

    // trigger the reloaded event
    // this initializes file and ckeditor fields (and maybe more)
    $item.find('.Inputfield').trigger('reloaded');
  }

  RockMatrix.prototype.makeSortable = function(e) {
    let $container = this.$itemsContainer(e);

    let not_draggable = $container.find('> ul:not(.rmx-draggable)').length;
    if(!not_draggable) return;

    // init uikit sortable on container
    UIkit.sortable($container[0], {
      // longer animation duration prevents flicker
      animation: 500,

      // set the handle to the header
      // this ensures that other drag&drop features don't break (eg images)
      handle: '.rmx-item > .InputfieldHeader',
    });

    // add class to every sortable element
    // to make it addressable via css
    let $items = this.$items(e);
    if($items.length) {
      $container.removeClass('uk-hidden');
      $.each($items, function(i, el) {
        $(el).parent().addClass('rmx-draggable');
      });
    }
    else {
      $container.addClass('uk-hidden');
    }
  }

  /**
   * Reset CKE after dragging
   */
  RockMatrix.prototype.resetCKEs = function($item) {
    $.each($item.find('div.cke'), function(i, el) {
      let $div = $(el);
      let id = $div.attr('id'); // eg cke_Inputfield_123_text
      id = id.substr(4); // Inputfield_123_text

      // get config for restore
      let $textarea = $div.parent().find('> textarea');
      let config = ProcessWire.config[$textarea.attr('data-configName')];

      CKEDITOR.instances[id].destroy();
      CKEDITOR.replace(id, config);
    });
  }

  RockMatrix.prototype.setTextarea = function(e, preventTrigger) {
    preventTrigger = preventTrigger || false;
    let $text = this.$textarea(e);
    let data = this.getData(e);
    let json = JSON.stringify(data);
    $text.val(json).text(json);
    if(!preventTrigger) $text.change();
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
    // debounce for all changes
    rm.changeTimer = setTimeout(function() {
      rm.makeSortable(e);
      rm.setTextarea(e, true);
      console.log(rm.init ? 'RockMatrix changed' : 'RockMatrix init');
      rm.init = true;

      // trigger pw-panels to fix issue
      // https://github.com/processwire/processwire-requests/issues/176
      pwPanels.init();

    }, this.editdelay);
  }

  // click on add new item button
  RockMatrix.prototype.clickAdd = function(e) {
    e.preventDefault();

    // get link
    let $a = $(e.target).closest('a');
    let href = $a.attr('href');
    let rm = this;

    // prevent double-click
    if($a.find("i.fa-spin").length) return;

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
    this.makeSortable(e.target);
    this.$root(e).trigger('changed');
  }

var RockMatrix = new RockMatrix();

// listeners

  // init the matrix
  $(document).on('init', '.InputfieldRockMatrix', function(e) {
    RockMatrix.initialize(e);
  });

  // add a new matrix item
  $(document).on('click', '.InputfieldRockMatrix .rmx-buttons a', function(e) {
    RockMatrix.clickAdd(e);
  });

  // change event triggered on root element
  $(document).on('changed', '.InputfieldRockMatrix', function(e) {
    RockMatrix.changed(e);
  });

  // items sort oder changed
  $(document).on('stop', '.rmx-items', function(e) {
    RockMatrix.changed(e);
  });

  // whenever a matrix item is moved we reset CKEditor fields
  $(document).on('moved', ".rmx-items.uk-sortable", function(e) {
    let $item = $(e.originalEvent.detail[1]);
    RockMatrix.resetCKEs($item);
  });

  // monitor all inputfields in a rockmatrix field
  $(document).on('change', '.rmx-items input, .rmx-items textarea, .rmx-items select', function(e) {
    setTimeout(function() { RockMatrix.changed(e); });
  });

  // trigger changed event after file uploads
  $(document).on('AjaxUploadDone', function(e) {
    RockMatrix.changed(e);
  });

  // monitor action clicks
  $(document).on('click', '.rmx-action', function(e) {
    let $action = $(e.target).closest('.rmx-action');
    RockMatrix.fire($action);

    // dont toggle field
    e.preventDefault();
    return false;
  });

  // make sure to add InputfieldStateChanged immediately after keydown
  // we do not intercept form.submit() because that somehow brakes the save process
  $(document).on('keydown', '.InputfieldRockMatrix input', function(e) {
    $(e.target).closest('.Inputfield').addClass('InputfieldStateChanged');
    RockMatrix.changed(e);
  });
