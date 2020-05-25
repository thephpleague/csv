(() => {
  const uri = new URL(location.href);

  let headers = document.querySelector('header nav h2');
  if (headers) {
    headers.addEventListener('click', function () {
      this.parentNode.querySelector('ul').classList.toggle('show');
    }, false);
  }

  let contentHeaders = document.querySelectorAll("main h2[id]");
  if (contentHeaders && !document.querySelector('html').classList.contains('homepage')) {
    contentHeaders.forEach((header) => {
      uri.hash = header.id;
      let link = document.createElement("a");
      link.className = "header-permalink";
      link.title = "Permalink";
      link.href = uri.toString();
      link.innerHTML = "&#182;";
      header.appendChild(link);
    });
  }

  const sponsorDiv = document.querySelector('.sponsors');
  if (sponsorDiv) {
    let hideSponsorUntil = localStorage.getItem('hideSponsorUntil');
    if (hideSponsorUntil === null || hideSponsorUntil < (new Date().getTime())) {
      sponsorDiv.classList.remove('hide');
    }

    sponsorDiv.querySelector('a.close').addEventListener('click', function () {
      sponsorDiv.classList.add('hide');
      localStorage.setItem('hideSponsorUntil', new Date().getTime() + (7 * 86400 * 1e4));
    }, false);
  }
})();
