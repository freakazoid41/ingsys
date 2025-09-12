$(document).ready(function () {
  $(".menuToggle").on("click", function () {
    $(".left-bar").toggleClass("small");
  });

  const menu = () => {
    var width = $(window).width();
    if (width < 991) {
      if ($(".left-bar").find(".right-bar-header-menu").length === 0) {
        $(".right-bar-header-menu").clone().appendTo(".left-bar");
      }
    } else {
      $(".left-bar").find(".right-bar-header-menu").remove();
    }
  };

  menu();
  $(window).resize(function () {
    menu();
  });

  $('.mobileButton').on('click', function(){
    $(".left-bar").toggleClass("show");
  })
});
