import { ready } from './ready';

// Get current time or fake time
function get_time() {
  const searchParams = new URLSearchParams(window.location.search);
  if (searchParams.has('fake_time')) {
    const start = parseInt(document
      .querySelector('table.dashboard-table > tbody > tr[data-start-time]')
      .dataset.startTime
    )
    return(start + parseInt(searchParams.get('fake_time')));
  }
  return(Math.round(Date.now() / 1000));
}

// Get string representation of current search string with one
// modification applied
function get_mod_search_string(key, value) {
  const search_string = new URLSearchParams(document.location.search);
  search_string.set(key, value);
  return search_string.toString();
}

// Change the current URI's search params to given search_string
function update_search_params(search_string) {
  window.history.replaceState(
    '',
    '',
    document.location.origin + document.location.pathname + '?' + search_string
  );
}

// Highlight all shift rows that are currently happening
// Remove past rows if option is set
function update_shifts_highlight_hidden(date_now, hide_old) {
  const dtables_trs = document.querySelectorAll('table.dashboard-table > tbody > tr');
  dtables_trs.forEach(function(element) {
    element.parentElement.hidden = (hide_old && element.dataset.endTime < date_now);

    if (element.dataset.startTime < date_now && element.dataset.endTime >= date_now) {
      element.classList.add("highlight");
    } else {
      element.classList.remove("highlight");
    }
  });
}

// Pull a whole new table from the web server and whack it in place
function update_shifts_data(date_now, hide_old) {
  const search_string = get_mod_search_string('rand', Math.floor(Math.random() * 99999999));
  const request = new Request(document.location.origin + document.location.pathname + '?' + search_string);
  fetch(request)
    .then(response => response.text())
    .then(text => {
      const parser = new DOMParser();
      const rdoc = parser.parseFromString(text, "text/html");
      document.querySelector('table.dashboard-table')
        .replaceWith(rdoc.querySelector('table.dashboard-table'));
    })
    .then(x => {
      update_shifts_highlight_hidden(date_now, hide_old);
      attach_dtable_listeners(true);
    });
}

function update_shifts_display(on_timer) {
  const date_now = get_time();
  const hide_old = document.getElementById('hide_past').checked;
  const reload = document.getElementById('reload').checked;

  if (!on_timer || !reload) {
    update_shifts_highlight_hidden(date_now, hide_old);
  }
  if (on_timer && reload) {
    update_shifts_data(date_now, hide_old);
  }
}

function attach_dtable_listeners(do_tooltips) {
  var dtables = document.querySelectorAll('table.dashboard-table');

  // Highlight all squares indicating the same user on hover
  dtables.forEach(function(element) {
    element.addEventListener('mouseover', (event) => {
      if (event.target.tagName !== 'SPAN') return true;
      if (!event.target.parentElement.dataset.critterId) return true;
      document.querySelectorAll(
        `[data-critter-id="${event.target.parentElement.dataset.critterId}"]`).forEach(
          (element) => {
            element.classList.add("secondary-highlight");
          });
    });
    element.addEventListener('mouseout', (event) => {
      if (event.target.tagName !== 'SPAN') return true;
      if (!event.target.parentElement.dataset.critterId) return true;
      document.querySelectorAll(
        `[data-critter-id="${event.target.parentElement.dataset.critterId}"]`).forEach(
          (element) => {
            element.classList.remove("secondary-highlight");
          });
    });
    if (do_tooltips) {
      // Remove current tooltips
      document.querySelectorAll('div.bs-tooltip-auto').forEach((element) => element.remove());
      // https://getbootstrap.com/docs/5.1/components/tooltips/#example-enable-tooltips-everywhere
      var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
      var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
      })
    }
  });
}

ready(() => {

  if (document.querySelector('table.dashboard-table').length === 0) return;

  attach_dtable_listeners(false);

  // Handlers for checkboxes
  document.getElementById('hide_past').addEventListener('change', (event) => {
    update_search_params(get_mod_search_string('hide_past', event.target.checked ? 1 : 0));
    update_shifts_highlight_hidden(get_time(), event.target.checked);
  });
  document.getElementById('reload').addEventListener('change', (event) => {
    update_search_params(get_mod_search_string('reload', event.target.checked ? 1 : 0));
    if (event.target.checked) {
      update_shifts_data();
    }
  });

  update_shifts_display(false);

  const searchParams = new URLSearchParams(window.location.search);
  const update_interval = searchParams.has('fake_time') ? 5000 : 60000;
  setInterval(update_shifts_display, update_interval, true);
});
