import { ready } from './ready';

function get_time() {
  const searchParams = new URLSearchParams(window.location.search);
  if (searchParams.has('fake_time')) {
    return(parseInt(searchParams.get('fake_time')));
  }
  return(Math.round(Date.now() / 1000));
}

function update_shifts_highlight_hidden(dtables_trs, date_now, hide_old) {
  // Highlight all shift rows that are currently happening
  // Remove past rows if option is set
  dtables_trs.forEach(function(element) {
    if (hide_old && element.dataset.endTime < date_now) {
      element.parentElement.hidden = true;
    } else {
      element.parentElement.hidden = false;
    }
    if (element.dataset.startTime < date_now && element.dataset.endTime >= date_now) {
      element.classList.add("highlight");
    } else {
      element.classList.remove("highlight");
    }
  });
}

function update_shifts_display(dtables_trs, on_timer) {
  var date_now = get_time();
  var hide_old = document.getElementById('hide_past').checked;

  if (!on_timer && false) {
    console.log("There should be auto-reload code here");
  }

  update_shifts_highlight_hidden(dtables_trs, date_now, hide_old);
}

ready(() => {

  var dtables = document.querySelectorAll('table.dashboard-table');
  if (dtables.length === 0) return;

  var dtables_trs = document.querySelectorAll('table.dashboard-table > tbody > tr');

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
  });

  // Handlers for checkboxes
  document.querySelector('input[name="hide_past"]').addEventListener('change', (event) => {
    update_shifts_highlight_hidden(dtables_trs, get_time(), event.target.checked)
  });
  //document.querySelector('input[name="reload"]');

  update_shifts_display(dtables_trs, false);
  setInterval(update_shifts_display, 60000, dtables_trs, true);
});
