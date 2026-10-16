(function () {
  // Confirm dialogs for destructive buttons
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-confirm]');
    if (b && !confirm(b.getAttribute('data-confirm'))) e.preventDefault();
  });

  // Sign-up form: the 2nd choice can't be the same as the 1st
  var prim = document.querySelector('.acts[data-group="primary"]');
  var sec = document.querySelector('.acts[data-group="secondary"]');
  if (prim && sec) {
    var sync = function () {
      var p = prim.querySelector('input:checked');
      sec.querySelectorAll('.act').forEach(function (lbl) {
        var inp = lbl.querySelector('input');
        var same = p && inp.value !== '' && inp.value === p.value;
        lbl.classList.toggle('is-disabled', !!same);
        if (same && inp.checked) { inp.checked = false; sec.querySelector('input[value=""]').checked = true; }
      });
    };
    prim.addEventListener('change', sync);
    sync();
  }

  // Check-in: search + instant toggle without page reload
  var search = document.getElementById('ci-search');
  if (search) {
    search.addEventListener('input', function () {
      var t = search.value.trim().toLowerCase();
      document.querySelectorAll('.ci').forEach(function (li) {
        li.classList.toggle('is-hidden', t !== '' && li.dataset.search.indexOf(t) === -1);
      });
    });
  }
  document.querySelectorAll('.ci-form').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = f.querySelector('button');
      btn.disabled = true;
      fetch(location.href, { method: 'POST', body: new FormData(f), headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d.ok) throw new Error();
          var li = f.closest('.ci');
          li.classList.toggle('is-in', !!d.in);
          f.querySelector('input[name="in"]').value = d.in ? '0' : '1';
          btn.textContent = d.in ? '✔ Checked in' : 'Check in';
          var c = document.getElementById('incount');
          if (c) c.textContent = document.querySelectorAll('.ci.is-in').length;
        })
        .catch(function () { f.submit(); })
        .finally(function () { btn.disabled = false; });
    });
  });
})();
