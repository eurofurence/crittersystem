import { ready } from './ready';

function highlight_current_shifts(dtables_trs) {
  var date_now = Math.round(Date.now() / 1000);
  dtables_trs.forEach(function(element) {
    if (element.dataset.startTime < date_now && element.dataset.endTime >= date_now ) {
      element.classList.add("highlight");
    } else {
      element.classList.remove("highlight");
    }
  });
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

  // Highlight all shifts happening now every minute
  highlight_current_shifts(dtables_trs);
  setInterval(highlight_current_shifts, 60000, dtables_trs);
});
