(function () {
  var scrollBox = document.getElementById('charter-scrollbox');
  var checkbox = document.getElementById('confirm_acceptance');
  var submit = document.getElementById('accept-button');

  if (!scrollBox || !checkbox || !submit) {
    return;
  }

  var hasReachedBottom = false;

  function nearBottom(el) {
    return el.scrollTop + el.clientHeight >= el.scrollHeight - 12;
  }

  function refreshState() {
    submit.disabled = !(hasReachedBottom && checkbox.checked);
  }

  scrollBox.addEventListener('scroll', function () {
    if (nearBottom(scrollBox)) {
      hasReachedBottom = true;
      refreshState();
    }
  });

  checkbox.addEventListener('change', refreshState);
  refreshState();
})();
