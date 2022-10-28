(function () {
  let guis = document.querySelectorAll(".rf-gui");
  for (let i = 0; i < guis.length; i++) {
    let gui = guis[i];
    // console.log(gui);
    let img = gui.closest(".rf-overlay").querySelector(".rf-img");

    let opacity = function () {
      img.style.opacity = slider.value;
    };

    // slider
    let slider = gui.querySelector(".rf-overlay-slider");
    slider.addEventListener("input", opacity);
    gui.querySelector(".rf-opacity-0").addEventListener("click", function () {
      slider.value = 0;
      opacity();
    });
    gui.querySelector(".rf-opacity-1").addEventListener("click", function () {
      slider.value = 1;
      opacity();
    });

    // black/white toggle
    let bw = gui.querySelector(".rf-color-off");
    let color = gui.querySelector(".rf-color-on");
    bw.addEventListener("click", function (e) {
      bw.setAttribute("hidden", "hidden");
      color.removeAttribute("hidden");
      img.style.filter = "grayscale(100%)";
    });
    color.addEventListener("click", function (e) {
      color.setAttribute("hidden", "hidden");
      bw.removeAttribute("hidden");
      img.style.filter = "grayscale(0)";
    });
  }
  // console.log("fired");
})();
