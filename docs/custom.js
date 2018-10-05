(() => {
  const uri = new URL(location.href);

  document.querySelector('.versions').addEventListener('change', function() {
    uri.hash = '';
    let path = this.options[this.options.selectedIndex].dataset.url;
    if (undefined === path) {
      return;
    }
    uri.pathname = path;
    location.href = uri.toString();
  }, false);

  document.querySelectorAll("main h2[id]").forEach((heading) => {
    uri.hash = heading.id;
    let link = document.createElement("a");
    link.className = "header-permalink";
    link.title = "Permalink";
    link.href = uri.toString();
    link.innerHTML = "&#182;";
    heading.appendChild(link);
  });
})();