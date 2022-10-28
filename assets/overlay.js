(function () {
  let sliders = document.querySelectorAll(".rf-overlay-slider");
  for (let i = 0; i < sliders.length; i++) {
    let slider = sliders[i];
    // console.log(slider);
    slider.addEventListener("input", function (e) {
      let overlay = e.target.closest(".rf-overlay").querySelector("img");
      overlay.style.opacity = e.target.value;
    });
  }
  // console.log("fired");
})();
