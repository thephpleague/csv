$(function() {
    $('.versions').change(function (e) {
        location.href = $(this).find('option:selected').data('url');
    });
});

(function (w) {
  var d = w.document,
      headerList = d.querySelector('main').querySelectorAll("h2[id]"),
      uri = location.href.split('#', 2).pop();

  for (var i = 0, header, link; header = headerList[i]; ++i) {
    link = d.createElement("a");
    link.className = "header-permalink";
    link.title = "Permalink";
    link.href = uri + "#" + header.id;
    link.innerHTML = "&#182;";
    header.appendChild(link);
  }
})(window);