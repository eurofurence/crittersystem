import { ready } from './ready';

ready(() => {

  const dtables = document.querySelectorAll('table.dashboard-table');
  if (dtables.length === 0) return;

  // Highlight all squares indicating the same user on hover
  dtables.forEach(function(element) {
    element.addEventListener('mouseover', (event) => {
      if (event.target.tagName !== 'SPAN') return true;
      console.log(event.target.parentElement.dataset.critterId);
      console.log(!event.target.parentElement.dataset.critterId);
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

  // Highlight all shifts happening now
  // TODO
});
