"use strict";

/**
 * RockPageBuilder Overlay Feature
 */
(function () {
  function Overlay() {}

  Overlay.prototype.init = function (root) {
    this.id = root.getAttribute("data-id");
    this.root = root;
    this.img = root.querySelector(".rpb-img");
    this.slider = root.querySelector(".rpb-slider");
    this.vslider = root.querySelector(".rpb-vslider");
    this.layersoff = root.querySelector(".rpb-layersoff");
    this.layerson = root.querySelector(".rpb-layerson");
    this.bw = root.querySelector(".rpb-bw");
    this.color = root.querySelector(".rpb-color");
    this.reset = root.querySelector(".rpb-reset");
    this.addEventListeners();
    this.restore();
  };

  Overlay.prototype.addEventListeners = function () {
    let overlay = this;
    this.slider.addEventListener("input", function () {
      overlay.fade(overlay.slider.value);
    });
    this.vslider.addEventListener("input", function () {
      overlay.move(overlay.vslider.value);
    });
    this.slider.addEventListener("change", function () {
      overlay.fade(overlay.slider.value);
    });
    this.layersoff.addEventListener("click", function () {
      overlay.fade(0);
    });
    this.layerson.addEventListener("click", function () {
      overlay.fade(1);
    });
    this.reset.addEventListener("click", function () {
      overlay.fade(0.5);
      overlay.move(0);
      overlay.filter(0);
    });
    this.bw.addEventListener("click", function () {
      overlay.bw.setAttribute("hidden", "hidden");
      overlay.color.removeAttribute("hidden");
      overlay.filter(100);
    });
    this.color.addEventListener("click", function () {
      overlay.color.setAttribute("hidden", "hidden");
      overlay.bw.removeAttribute("hidden");
      overlay.filter(0);
    });
  };

  Overlay.prototype.fade = function (opacity) {
    this.img.style.opacity = opacity;
    this.slider.value = opacity;
    this.save();
  };

  Overlay.prototype.filter = function (val) {
    this.img.style.filter = "grayscale(" + val + "%)";
    this.filterval = val;
    this.save();
  };

  Overlay.prototype.move = function (val) {
    this.img.style.top = val + "px";
    this.vslider.value = val;
    this.save();
  };

  Overlay.prototype.restore = function () {
    let storage = JSON.parse(localStorage.getItem("rpb-overlay-" + this.id));
    this.fade(storage.slider);
    this.filter(storage.filter);
    this.move(storage.move);
  };

  /**
   * Save settings to localstorage
   */
  Overlay.prototype.save = function () {
    localStorage.setItem(
      "rpb-overlay-" + this.id,
      JSON.stringify({
        slider: this.slider.value,
        filter: this.filterval,
        move: this.vslider.value,
      })
    );
  };

  let roots = document.querySelectorAll(".rpb-overlay");
  for (let i = 0; i < roots.length; i++) {
    let overlay = new Overlay();
    overlay.init(roots[i]);
    console.log(overlay);
  }
})();
