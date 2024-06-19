(() => {
  let contentHeaders= document.querySelectorAll("main h2[id]");
  if (!document.querySelector('html').classList.contains('homepage') && contentHeaders) {
    const uri = new URL(location.href);
    contentHeaders.forEach((header) => {
      uri.hash = header.id;
      let link = document.createElement("a");
      link.classList.add("header-permalink");
      link.title = "Permalink";
      link.href = uri.toString();
      link.innerHTML = "&#182;";
      header.appendChild(link);
    });
  }

  let codeSnippet = document.querySelectorAll('.content .language-php.highlighter-rouge');
  codeSnippet.forEach((snippet) => {
    let notification = document.createElement("div");
    notification.classList.add('copy-snippet-notification', 'hidden', 'rounded', 'p-2');
    snippet.appendChild(notification);

    let link = document.createElement("span");
    link.classList.add("copy-snippet");
    link.innerHTML = "copy 📋";
    link.addEventListener('click', function (e) {
        let snippetParent = e.target.parentNode;
        let notification = snippetParent.querySelector('.copy-snippet-notification');
        let content = snippetParent.querySelector('pre').textContent;
        try {
          navigator.clipboard.writeText(content);
          notification.innerHTML = 'Copied!';
          notification.classList.add('bg-black');
          notification.classList.remove('hidden');
          setTimeout(() => {
            notification.classList.add('hidden');
            notification.classList.remove('bg-black');
          }, 500);
        } catch (err) {
          console.error('Failed to copy: ', err);
          notification.innerHTML = 'Copy failed!';
          notification.classList.add('bg-red-800');
          notification.classList.remove('hidden');
          setTimeout(() => {
            notification.classList.add('hidden');
            notification.classList.remove('bg-red-800');
          }, 500);
        }
      }, false);
    snippet.appendChild(link);
  });
})();
