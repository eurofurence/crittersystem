/* ShiftManagerV2 — bootstrap & polling + minimal filters (date, locations)
 * Add this file to webpack entry and build. The UI will enhance Twig placeholders.
 */

(function () {
  const ROOT_ID = 'shift-manager-v2';
  const root = document.getElementById(ROOT_ID);
  if (!root) return;

  // Configurable polling interval (ms). Can be overridden globally before this script loads.
  const DEFAULT_INTERVAL = 10000;
  const pollInterval = window.ShiftManagerV2_POLL_INTERVAL || DEFAULT_INTERVAL;

  // CSRF setup: assumes a meta tag or global provided by backend
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  // Global state for current filters and date
  let currentState = { date: undefined, start: '00:00', end: '23:30', locations: [], shift_types: [], critter_types: [] };

  // Event dates for countdown functionality - read from script tag data attributes
  function getEventDates() {
    const scriptTag = document.querySelector('script[src*="shiftManagerV2.js"]');
    if (scriptTag) {
      return {
        buildup_start: scriptTag.getAttribute('data-buildup-start') || null,
        event_start: scriptTag.getAttribute('data-event-start') || null,
        event_end: scriptTag.getAttribute('data-event-end') || null,
        teardown_end: scriptTag.getAttribute('data-teardown-end') || null
      };
    }
    return {
      buildup_start: null,
      event_start: null,
      event_end: null,
      teardown_end: null
    };
  }
  
  const eventDates = getEventDates();

  // Note: Choices.js instances are accessed directly via element.choices property

  function fetchJSON(url, options = {}) {
    const headers = new Headers(options.headers || {});
    headers.set('Accept', 'application/json');
    if (csrfToken) headers.set('X-CSRF-TOKEN', csrfToken);
    return fetch(url, { credentials: 'same-origin', ...options, headers }).then((r) => {
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      return r.json();
    });
  }

  function el(id) {
    return document.getElementById(id);
  }

  function formatTime(ts) {
    const d = new Date(ts * 1000);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  // Countdown functionality
  function calculateCountdown(selectedDate) {
    if (!selectedDate) return '';
    
    const selected = new Date(selectedDate + 'T00:00:00');
    const now = new Date();
    
    // Convert event dates to Date objects if available
    const dates = {
      buildup_start: eventDates.buildup_start ? new Date(eventDates.buildup_start) : null,
      event_start: eventDates.event_start ? new Date(eventDates.event_start) : null,
      event_end: eventDates.event_end ? new Date(eventDates.event_end) : null,
      teardown_end: eventDates.teardown_end ? new Date(eventDates.teardown_end) : null
    };

    // Determine which phase the selected date is in
    let phase = '';
    let targetDate = null;
    let isCountingDown = true;

    if (dates.teardown_end && selected > dates.teardown_end) {
      phase = 'After Event';
      return phase;
    } else if (dates.event_end && selected >= dates.event_end) {
      phase = 'Teardown';
      if (dates.teardown_end) {
        targetDate = dates.teardown_end;
        isCountingDown = false; // counting up to teardown end
      }
    } else if (dates.event_start && selected >= dates.event_start) {
      phase = 'Event Days';
      if (dates.event_end) {
        targetDate = dates.event_end;
        isCountingDown = false; // counting up to event end
      }
    } else if (dates.buildup_start && selected >= dates.buildup_start) {
      phase = 'Buildup';
      if (dates.event_start) {
        targetDate = dates.event_start;
        isCountingDown = false; // counting up to event start
      }
    } else if (dates.buildup_start && selected < dates.buildup_start) {
      phase = 'Pre-Event';
      targetDate = dates.buildup_start;
      isCountingDown = true; // counting down to buildup
    } else {
      return ''; // No event dates configured
    }

    if (!targetDate) return phase;

    // Calculate days difference
    const timeDiff = targetDate.getTime() - selected.getTime();
    const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24));

    let countdownText = '';
    if (daysDiff === 0) {
      countdownText = 'Today!';
    } else if (daysDiff === 1) {
      countdownText = isCountingDown ? '1 day to go' : 'Tomorrow';
    } else if (daysDiff === -1) {
      countdownText = 'Yesterday';
    } else if (daysDiff > 0) {
      countdownText = isCountingDown ? `${daysDiff} days to go` : `${daysDiff} days ahead`;
    } else {
      countdownText = `${Math.abs(daysDiff)} days ago`;
    }

    return `${phase} • ${countdownText}`;
  }

  function updateCountdownDisplay(selectedDate) {
    const countdownEl = el('smv2-countdown');
    if (countdownEl) {
      const countdownText = calculateCountdown(selectedDate);
      countdownEl.textContent = countdownText;
      
      // Add some visual styling based on phase
      countdownEl.className = 'text-muted small';
      if (countdownText.includes('Today')) {
        countdownEl.className = 'text-warning fw-semibold small';
      } else if (countdownText.includes('Event Days')) {
        countdownEl.className = 'text-success small';
      } else if (countdownText.includes('Buildup') || countdownText.includes('Teardown')) {
        countdownEl.className = 'text-info small';
      }
    }
  }

  function minutesFromMidnight(ts) {
    const d = new Date(ts * 1000);
    return d.getHours() * 60 + d.getMinutes();
  }

  function renderTimeline(data, state = {}) {
    const tl = el('smv2-timeline');
    if (!tl) return;
    
    // Clean up existing tooltips before re-rendering
    cleanupTooltips();
    
    // Parse user's time range
    const startTime = state.start || '00:00';
    const endTime = state.end || '23:30';
    const [startHour, startMin] = startTime.split(':').map(Number);
    const [endHour, endMin] = endTime.split(':').map(Number);
    
    // Convert to minutes from midnight
    const startMinutes = startHour * 60 + startMin;
    const endMinutes = endHour * 60 + endMin;
    
    // Handle edge case where end time is before start time (shouldn't happen with proper UI)
    let durationMinutes = endMinutes - startMinutes;
    if (durationMinutes <= 0) {
      durationMinutes = 24 * 60; // Show full day if invalid range
    }
    
    // Calculate height: 60px per hour
    const heightPx = Math.max(120, (durationMinutes / 60) * 60); // minimum 2 hours display
    const apiLocations = data && Array.isArray(data.locations) ? data.locations : [];
    const shifts = data && Array.isArray(data.shifts) ? data.shifts : [];

    // Group shifts by location
    const byLoc = new Map();
    for (const s of shifts) {
      if (!byLoc.has(s.location_id)) byLoc.set(s.location_id, []);
      byLoc.get(s.location_id).push(s);
    }

    // Build columns as union of API locations and any locations referenced by shifts
    const locMap = new Map();
    for (const l of apiLocations) {
      // normalize id as string for stable Map key
      locMap.set(String(l.id), { id: l.id, name: l.name });
    }
    for (const s of shifts) {
      const key = String(s.location_id);
      if (!locMap.has(key)) {
        locMap.set(key, { id: s.location_id, name: s.location || `#${s.location_id}` });
      }
    }
    // Only render locations that actually have shifts in the current window
    const locations = Array.from(locMap.values()).filter((l) => (byLoc.get(l.id) || []).length > 0);

    // Compute simple lanes per location
    function assignLanes(items) {
      items.sort((a, b) => a.start_ts - b.start_ts);
      const lanes = []; // each lane stores last end_ts
      const result = [];
      for (const it of items) {
        let lane = 0;
        while (lane < lanes.length && it.start_ts < lanes[lane]) lane += 1;
        if (lane === lanes.length) lanes.push(it.end_ts);
        else lanes[lane] = it.end_ts;
        result.push({ item: it, lane, laneCount: null });
      }
      const laneCount = lanes.length;
      for (const r of result) r.laneCount = laneCount;
      return result;
    }

    const colWidth = locations.length ? 100 / locations.length : 100;
    const headerCols = locations
      .map((l) => `<div class="p-2 border-end" style="flex:0 0 ${colWidth}%;">${l.name}</div>`)
      .join('');

    // Generate time labels for the selected range
    const timeLabels = [];
    let currentMinutes = startMinutes;
    
    // If duration is invalid (full day fallback), generate full day labels
    if (durationMinutes >= 24 * 60) {
      for (let i = 0; i <= 24; i++) {
        const hh = String(i).padStart(2, '0');
        timeLabels.push(`<div style="height:60px;line-height:60px;">${hh}:00</div>`);
      }
    } else {
      // Generate labels for selected time range
      while (currentMinutes <= endMinutes) {
        const hours = Math.floor(currentMinutes / 60) % 24; // Handle day overflow
        const hh = String(hours).padStart(2, '0');
        timeLabels.push(`<div style="height:60px;line-height:60px;">${hh}:00</div>`);
        currentMinutes += 60;
      }
    }
    
    const timeLabelsHTML = timeLabels.join('');

    let bodyCols = '';
    for (const loc of locations) {
      const allItems = byLoc.get(loc.id) || [];
      
      // Filter shifts that overlap with the selected time range
      const items = allItems.filter(item => {
        const shiftStartMinutes = minutesFromMidnight(item.start_ts);
        const shiftEndMinutes = minutesFromMidnight(item.end_ts);
        
        // Show shift if it overlaps with the selected time window
        return shiftEndMinutes > startMinutes && shiftStartMinutes < endMinutes;
      });
      
      const placed = assignLanes(items);
      const blocks = placed
        .map(({ item, lane, laneCount }) => {
          const shiftStartMinutes = minutesFromMidnight(item.start_ts);
          const shiftEndMinutes = minutesFromMidnight(item.end_ts);
          
          // Calculate position relative to the selected time window (convert minutes to pixels)
          const topMinutes = Math.max(0, shiftStartMinutes - startMinutes);
          const top = topMinutes; // Keep in minutes for now - will convert to pixels below
          
          // Calculate duration, clipped to the visible window
          const visibleStart = Math.max(shiftStartMinutes, startMinutes);
          const visibleEnd = Math.min(shiftEndMinutes, endMinutes);
          const durationMinutes = Math.max(15, visibleEnd - visibleStart); // min 15m
          const duration = durationMinutes; // Keep in minutes for now - will convert to pixels below
          const widthPct = 100 / Math.max(1, laneCount);
          const leftPct = lane * widthPct;
          // Determine if user can apply (check eligibility)
          const canApply = item.eligibility?.can_apply === true;
          const isGrayedOut = !canApply && item.eligibility?.needs_cert; // Gray if user lacks critter type certification
          
          let color, bg;
          if (isGrayedOut) {
            bg = 'rgba(128,128,128,0.3)'; // Gray background for non-applicable shifts
            color = '#6c757d'; // Gray border
          } else if (item.status === 'red') {
            bg = 'rgba(220,53,69,0.15)';
            color = '#dc3545';
          } else if (item.status === 'yellow') {
            bg = 'rgba(255,193,7,0.20)';
            color = '#ffc107';
          } else {
            bg = 'rgba(40,167,69,0.15)';
            color = '#28a745';
          }
          
          // Build capacity display
          const assigned = item.assigned ?? 0;
          const required = item.required ?? 1;
          const capacityText = `${assigned}/${required}`;
          
          // Get assigned users grouped by critter type
          const assignments = item.assignments || [];
          
          // Build tooltip content with more information
          let tooltipContent = `${item.title || 'Shift'}
Time: ${formatTime(item.start_ts)}–${formatTime(item.end_ts)}
Location: ${item.location || 'Unknown'}
Capacity: ${capacityText}`;

          if (assignments.length > 0) {
            tooltipContent += '\n\nAssigned:';
            assignments.forEach(group => {
              tooltipContent += `\n${group.angel_type_name}:`;
              group.users.forEach(user => {
                const staffBadge = user.is_staff ? ' 👤' : '';
                tooltipContent += `\n  • ${user.user_name}${staffBadge}`;
              });
            });
          }
          
          if (!canApply && item.eligibility) {
            if (item.eligibility.capacity_full) {
              tooltipContent += '\n❌ Shift is full';
            } else if (item.eligibility.overlaps) {
              tooltipContent += '\n❌ Overlaps with your shift';
            } else if (item.eligibility.needs_cert) {
              tooltipContent += '\n❌ Missing required certification';
            }
          } else if (canApply) {
            tooltipContent += '\n✅ You can apply for this shift';
          }
          
          const aria = `aria-label="${(item.title || 'Shift').replace(/"/g, '')} ${formatTime(item.start_ts)}–${formatTime(item.end_ts)} at ${(item.location || '').replace(/"/g, '')}, capacity ${capacityText}"`;
          
          // Build user names display for the block - show all users grouped by type
          let usersDisplay = '';
          if (assignments.length > 0 && duration > 45) {
            const usersByType = assignments.map(group => {
              const users = group.users.map(user => {
                const staffBadge = user.is_staff ? '👤' : '';
                return `${user.user_name}${staffBadge}`;
              });
              return `${group.angel_type_name}: ${users.join(', ')}`;
            });
            
            // For very tall blocks, show multiple lines; for shorter ones, show condensed
            if (duration > 80) {
              // Multi-line display for tall blocks
              usersDisplay = usersByType.map(line => 
                `<div class="text-truncate" style="font-size:0.6rem;line-height:1.2;color:rgba(0,0,0,0.8);">${line}</div>`
              ).join('');
            } else {
              // Single line condensed display
              const condensed = usersByType.join(' | ');
              usersDisplay = `<div class="text-truncate" style="font-size:0.6rem;line-height:1;color:rgba(0,0,0,0.7);">${condensed}</div>`;
            }
          }

          return (
            `<div class="position-absolute small smv2-open-modal ${isGrayedOut ? 'smv2-shift-grayed' : ''}" data-shift-id="${item.id}" role="gridcell" tabindex="0" ${aria} 
              title="${tooltipContent.replace(/"/g, '&quot;')}" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-html="true" 
              data-bs-title="${tooltipContent.replace(/\n/g, '<br>').replace(/"/g, '&quot;')}"
              style="top:${top}px;left:${leftPct}%;width:${widthPct}%;height:${duration}px;padding:2px;background:${bg};border:1px solid ${color};border-radius:4px;overflow:hidden;cursor:pointer;">` +
            `<div class="text-truncate fw-semibold" style="font-size:0.75rem;line-height:1.1;">${item.title || 'Shift'}</div>` +
            (duration > 30 ? `<div class="text-truncate text-muted" style="font-size:0.65rem;line-height:1;">${capacityText}</div>` : '') +
            usersDisplay +
            `</div>`
          );
        })
        .join('');
      bodyCols += `<div class="position-relative border-end" style="flex:0 0 ${colWidth}%;height:${heightPx}px">${blocks}</div>`;
    }

    tl.innerHTML = `
      <div class="d-flex border-bottom bg-dark">
        <div class="p-2 border-end bg-dark" style="flex:0 0 70px"></div>
        <div class="d-flex flex-grow-1" style="overflow-x:auto">${headerCols}</div>
      </div>
      <div class="d-flex">
        <div class="border-end small text-muted" style="flex:0 0 70px;height:${heightPx}px">${timeLabelsHTML}</div>
        <div class="d-flex flex-grow-1" style="overflow-x:auto">${bodyCols}</div>
      </div>`;
    
    // Initialize tooltips after rendering timeline
    setTimeout(() => initializeTooltips(), 100);
  }

  function updateUI(data) {
    // Keep last full payload accessible for Apply flows
    try { window.__SMV2_LAST_DATA__ = data; } catch (e) { /* ignore */ }
    // Update KPI cards in the existing partial structure
    if (data && data.kpis) {
      const fillRate = typeof data.kpis.fill_rate === 'number' ? Math.round(data.kpis.fill_rate * 100) : 0;

      const kpiShiftsToday = el('kpi-shifts-today');
      const kpiOpenPositions = el('kpi-open-positions');
      const kpiFillRate = el('kpi-fill-rate');
      const kpiEmptyShifts = el('kpi-empty-shifts');
      const kpiNoShows = el('kpi-no-shows');
      const kpiConflicts = el('kpi-conflicts');

      if (kpiShiftsToday) kpiShiftsToday.textContent = data.kpis.shifts_today || 0;
      if (kpiOpenPositions) kpiOpenPositions.textContent = data.kpis.open_positions || 0;
      if (kpiFillRate) kpiFillRate.textContent = `${fillRate}%`;
      if (kpiEmptyShifts) kpiEmptyShifts.textContent = data.kpis.empty_shifts || 0;
      if (kpiNoShows) kpiNoShows.textContent = data.kpis.no_shows_today || 0;
      if (kpiConflicts) kpiConflicts.textContent = data.kpis.conflicts || 0;
    }
    const list = el('smv2-list');
    if (list) {
      if (!data || !Array.isArray(data.shifts)) {
        list.innerHTML = '<div class="text-muted">No data</div>';
        return;
      }
      const persona = document.getElementById('shift-manager-v2')?.dataset?.persona || 'public';
      const items = data.shifts
        .slice(0, 50)
        .map((s) => {
          const time = `${formatTime(s.start_ts)} – ${formatTime(s.end_ts)}`;
          const capacity = `${s.assigned ?? '—'}/${s.required ?? '—'}`;
          const color = s.status === 'red' ? '#dc3545' : s.status === 'yellow' ? '#ffc107' : '#28a745';
          const statusDot = `<span class="ms-2 align-middle rounded-circle d-inline-block" style="width:10px;height:10px;background:${color}"></span>`;
          let cta = '';
          if (persona === 'staff' || persona === 'public') {
            if (s.is_assigned) {
              const canCancel = s.can_cancel !== false;
              const disabled = canCancel ? '' : ' disabled';
              const title = canCancel ? '' : ' title="You can no longer cancel this shift (unsubscribe window closed)."';
              cta = `<div class="ms-3"><button class="btn btn-sm btn-danger smv2-cancel${disabled}" data-action="cancel" data-entry-id="${s.my_entry_id}"${title}>Cancel</button></div>`;
            } else {
              const canApply = s.eligibility?.can_apply === true;
              const disabled = canApply ? '' : ' disabled';
              let reason = '';
              if (s.eligibility?.capacity_full) {
                reason = 'Shift is full';
              } else if (s.eligibility?.overlaps) {
                reason = 'Overlaps with your shift';
              } else if (s.eligibility?.needs_cert) {
                reason = 'Missing certification';
              }
              const title = reason ? ` title="${reason}"` : '';
              const ariaDisabled = ` aria-disabled="${canApply ? 'false' : 'true'}"`;
              const ariaLabel = reason ? ` aria-label="Apply${canApply ? '' : ` (disabled: ${reason})`}"` : '';
              cta = `<div class="ms-3"><button class="btn btn-sm btn-primary smv2-apply${disabled}"${title}${ariaDisabled}${ariaLabel} data-action="apply" data-shift-id="${s.id}">Apply</button></div>`;
            }
          }
          const itemAria = `aria-label="${(s.title || 'Shift').replace(/"/g, '')} at ${(s.location || '').replace(/"/g, '')}, ${time}, capacity ${capacity}"`;
          return `<div class="card mb-2 smv2-open-modal" role="listitem" data-shift-id="${s.id}" ${itemAria}>
          <div class="card-body p-2">
            <div class="d-flex justify-content-between align-items-center">
              <div class="d-flex align-items-center">
                <div class="fw-semibold me-2">${s.title || 'Shift'}
                  <small class="text-muted">@ ${s.location || ''}</small>
                </div>
                ${cta}
              </div>
              <div class="text-end small">
                <div>${time}${statusDot}</div>
                <div class="text-muted">${capacity}</div>
              </div>
            </div>
          </div>
        </div>`;
        })
        .join('');
      list.innerHTML = items || '<div class="text-muted">No shifts in this window.</div>';
    }
    renderTimeline(data, currentState);
  }

  function renderTopbar(data, state) {
    // Populate existing partial elements instead of replacing HTML
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    const defaultDate = `${yyyy}-${mm}-${dd}`;

    const selectedDate = (state && state.date) || defaultDate;
    const selectedLocations = new Set((state && state.locations) || []);
    const selectedShiftTypes = new Set((state && state.shift_types) || []);
    const selectedCritterTypes = new Set((state && state.critter_types) || []);

    // Update date boxes if available
    const dateBoxes = document.querySelector('.smv2-date-boxes');
    if (dateBoxes && data && data.dates) {
      dateBoxes.innerHTML = data.dates.map(d => `
        <button type="button" class="btn btn-secondary btn-sm me-1 ${d.date === selectedDate ? 'active' : ''}"
                data-date="${d.date}">
          ${d.display}
        </button>
      `).join('');

      // Add click handlers for date buttons
      dateBoxes.addEventListener('click', (e) => {
        if (e.target.matches('[data-date]')) {
          const newDate = e.target.getAttribute('data-date');
          currentState.date = newDate;
          // Update active state
          dateBoxes.querySelectorAll('button').forEach(b => b.classList.remove('active'));
          e.target.classList.add('active');
          // Update countdown display
          updateCountdownDisplay(newDate);
          loadData(currentState).then((resp) => {
            updateUI(resp);
          });
        }
      });
    }

    // Update time selectors
    const timeStart = el('smv2-time-start');
    const timeEnd = el('smv2-time-end');
    if (timeStart) timeStart.value = state?.start || '00:00';
    if (timeEnd) timeEnd.value = state?.end || '23:30';

    // Update countdown display for the selected date
    updateCountdownDisplay(selectedDate);

    // Update filter dropdowns with Choices.js
    const locSelect = el('smv2-filter-locations');
    const stSelect = el('smv2-filter-shift-types');
    const ctSelect = el('smv2-filter-critter-types');

    // Update Locations dropdown (Choices.js initialized by forms.js)
    if (locSelect && data && Array.isArray(data.locations)) {
      const choices = data.locations.map(l => ({
        value: String(l.id),
        label: l.name,
        selected: selectedLocations.has(String(l.id))
      }));

      if (locSelect.choices) {
        locSelect.choices.clearStore();
        locSelect.choices.setChoices(choices, 'value', 'label', true);
      } else {
        // Fallback to native select if Choices.js not initialized
        locSelect.innerHTML = data.locations.map(l =>
          `<option value="${l.id}" ${selectedLocations.has(String(l.id)) ? 'selected' : ''}>${l.name}</option>`
        ).join('');
      }
    }

    // Update Shift Types dropdown (Choices.js initialized by forms.js)
    if (stSelect && data && Array.isArray(data.shift_types)) {
      const choices = data.shift_types.map(t => ({
        value: String(t.id),
        label: t.name,
        selected: selectedShiftTypes.has(String(t.id))
      }));

      if (stSelect.choices) {
        stSelect.choices.clearStore();
        stSelect.choices.setChoices(choices, 'value', 'label', true);
      } else {
        // Fallback to native select if Choices.js not initialized
        stSelect.innerHTML = data.shift_types.map(t =>
          `<option value="${t.id}" ${selectedShiftTypes.has(String(t.id)) ? 'selected' : ''}>${t.name}</option>`
        ).join('');
      }
    }

    // Update Critter Types dropdown (Choices.js initialized by forms.js)
    if (ctSelect && data && Array.isArray(data.critter_types)) {
      const choices = data.critter_types.map(t => ({
        value: String(t.id),
        label: t.name,
        selected: selectedCritterTypes.has(String(t.id))
      }));

      if (ctSelect.choices) {
        ctSelect.choices.clearStore();
        ctSelect.choices.setChoices(choices, 'value', 'label', true);
      } else {
        // Fallback to native select if Choices.js not initialized
        ctSelect.innerHTML = data.critter_types.map(t =>
          `<option value="${t.id}" ${selectedCritterTypes.has(String(t.id)) ? 'selected' : ''}>${t.name}</option>`
        ).join('');
      }
    }

    // Get references to existing elements
    const dateEl = timeStart; // We'll use time inputs for date tracking now
    const startEl = timeStart;
    const endEl = timeEnd;
    const locEl = locSelect;
    const stEl = stSelect;
    const ctEl = ctSelect;
    const btn = el('smv2-refresh');

    function gatherState() {
      // Use Choices.js if available, otherwise fall back to native select
      let locs = [];
      let sts = [];
      let cts = [];

      if (locEl?.choices) {
        locs = locEl.choices.getValue(true);
      } else if (locEl) {
        locs = Array.from(locEl.selectedOptions).map(o => o.value);
      }

      if (stEl?.choices) {
        sts = stEl.choices.getValue(true);
      } else if (stEl) {
        sts = Array.from(stEl.selectedOptions).map(o => o.value);
      }

      if (ctEl?.choices) {
        cts = ctEl.choices.getValue(true);
      } else if (ctEl) {
        cts = Array.from(ctEl.selectedOptions).map(o => o.value);
      }

      return {
        date: currentState.date || defaultDate,
        start: startEl?.value || '00:00',
        end: endEl?.value || '23:30',
        locations: Array.isArray(locs) ? locs : [locs].filter(Boolean),
        shift_types: Array.isArray(sts) ? sts : [sts].filter(Boolean),
        critter_types: Array.isArray(cts) ? cts : [cts].filter(Boolean),
      };
    }

    function triggerRefresh(skipTopbarRender = false) {
      const s = gatherState();
      currentState = { ...currentState, ...s }; // Update global state
      loadData(s).then((resp) => {
        if (!skipTopbarRender) {
          renderTopbar(resp, s);
        }
        updateUI(resp);
      });
    }

    // Add event listeners to existing elements
    if (startEl) startEl.addEventListener('change', triggerRefresh);
    if (endEl) endEl.addEventListener('change', triggerRefresh);
    if (btn) btn.addEventListener('click', triggerRefresh);

    // Add change listeners for existing Choices.js instances
    // Skip topbar rendering to avoid overriding user selections
    if (locEl) {
      locEl.addEventListener('change', () => triggerRefresh(true));
    }
    if (stEl) {
      stEl.addEventListener('change', () => triggerRefresh(true));
    }
    if (ctEl) {
      ctEl.addEventListener('change', () => triggerRefresh(true));
    }
  }

  function loadData(state) {
    const params = new URLSearchParams();
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    params.set('date', (state && state.date) || `${yyyy}-${mm}-${dd}`);
    params.set('start', state?.start || '00:00');
    params.set('end', state?.end || '23:59');
    (state && state.locations ? state.locations : []).forEach((id) => params.append('locations[]', id));
    (state && state.shift_types ? state.shift_types : []).forEach((id) => params.append('shift_types[]', id));
    (state && state.critter_types ? state.critter_types : []).forEach((id) => params.append('critter_types[]', id));
    if (state?.q) params.set('q', state.q);

    // Fetch both shifts data and dates in parallel
    return Promise.all([
      fetchJSON(`/api/v2/shift-manager/shifts?${params.toString()}`),
      fetchJSON('/api/v2/shift-manager/dates')
    ]).then(([shiftsData, datesData]) => {
      // Merge the data
      return {
        ...shiftsData,
        dates: datesData.ok ? datesData.dates : [],
        today: datesData.ok ? datesData.today : `${yyyy}-${mm}-${dd}`
      };
    });
  }

  function initialState() {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    return { date: `${yyyy}-${mm}-${dd}`, start: '00:00', end: '23:30', locations: [], shift_types: [], critter_types: [] };
  }

  function loadOnce() {
    const s = initialState();
    currentState = { ...currentState, ...s }; // Initialize global state
    return loadData(s)
      .then((resp) => {
        renderTopbar(resp, s);
        updateUI(resp);
      })
      .catch((e) => console.warn('SMV2 fetch failed', e));
  }

  function postForm(url, data) {
    const headers = new Headers();
    headers.set('Accept', 'application/json');
    if (csrfToken) headers.set('X-CSRF-TOKEN', csrfToken);
    const body = new URLSearchParams();
    Object.keys(data || {}).forEach((k) => body.append(k, data[k]));
    return fetch(url, { method: 'POST', credentials: 'same-origin', headers, body })
      .then((r) => r.json());
  }

  function ensureToastContainer() {
    let c = document.getElementById('smv2-toasts');
    if (!c) {
      c = document.createElement('div');
      c.id = 'smv2-toasts';
      c.className = 'toast-container position-fixed bottom-0 end-0 p-3';
      document.body.appendChild(c);
    }
    return c;
  }

  function showToast(message, type = 'info') {
    const container = ensureToastContainer();
    const id = `t${Math.random().toString(36).slice(2)}`;
    const bg = type === 'success' ? 'bg-success' : type === 'error' ? 'bg-danger' : 'bg-secondary';
    const el = document.createElement('div');
    el.className = `toast text-white ${bg}`;
    el.id = id;
    el.setAttribute('role', 'status');
    el.setAttribute('aria-live', 'polite');
    el.setAttribute('aria-atomic', 'true');
    el.innerHTML = `<div class="d-flex"><div class="toast-body">${message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>`;
    container.appendChild(el);
    try {
      // Bootstrap 5 toast
      // eslint-disable-next-line no-undef
      const toast = new bootstrap.Toast(el, { delay: 2500 });
      toast.show();
    } catch (e) {
      // Fallback
      el.classList.add('show');
      setTimeout(() => el.remove(), 3000);
    }
  }

  function errorMessage(code, fallback) {
    switch (code) {
    case 'unsubscribe_window_closed':
      return 'You can no longer cancel this shift (unsubscribe window closed).';
    case 'already_assigned':
      return 'You are already assigned to this shift.';
    case 'overlap':
      return 'This shift overlaps with one of your other shifts.';
    case 'shift_in_past':
      return 'This shift is in the past.';
    case 'shift_not_found':
      return 'Shift not found.';
    case 'entry_not_found':
      return 'Assignment not found.';
    case 'no_eligible_slot':
      return 'No eligible slot is available for you on this shift.';
    case 'no_users_added':
      return 'No users were added (duplicates, overlaps, or not selected).';
    default:
      return fallback || 'Operation failed';
    }
  }

  function currentStateFromDom() {
    // Return the current global state instead of trying to read from non-existent DOM elements
    // This ensures consistency with the user's current selections
    return { ...currentState };
  }

  function refreshOnce() {
    // Use the current global state instead of trying to read from DOM
    const state = currentState.date ? { ...currentState } : initialState();
    return loadData(state).then((resp) => {
      // Do not touch topbar on immediate refresh to avoid flicker
      updateUI(resp);
    });
  }

  function attachEvents() {
    const persona = document.getElementById('shift-manager-v2')?.dataset?.persona || 'public';
    if (persona === 'manager') {
      // Open manager modal on click
      document.addEventListener('click', (ev) => {
        const target = ev.target.closest('.smv2-open-modal');
        if (target) {
          const sid = target.getAttribute('data-shift-id');
          if (sid) {
            openManagerModal(sid);
          }
        }
      });
    } else {
      // Open staff/public apply modal on click
      document.addEventListener('click', (ev) => {
        const target = ev.target.closest('.smv2-open-modal');
        if (target) {
          const sid = target.getAttribute('data-shift-id');
          if (sid) {
            // Find payload by id
            const data = window.__SMV2_LAST_DATA__;
            const payload = data && data.shifts ? data.shifts.find((x) => String(x.id) === String(sid)) : null;
            if (payload) {
              // Check if user can apply
              if (payload.eligibility?.can_apply === true) {
                openSpApplyModal(sid, payload);
              } else {
                // Show explanation modal for why user can't apply
                showIneligibilityModal(payload);
              }
            }
          }
        }
      });
    }
    const list = el('smv2-list');
    if (!list) return;
    list.addEventListener('click', (ev) => {
      const applyBtn = ev.target.closest('button.smv2-apply[data-action="apply"]');
      if (applyBtn) {
        if (applyBtn.classList.contains('disabled')) return;
        const shiftId = applyBtn.getAttribute('data-shift-id');
        // Try to pick a specific angel type if multiple are eligible
        let data = { shift_id: shiftId };
        let payload = null;
        try {
          const dataState = window.__SMV2_LAST_DATA__;
          payload = dataState && dataState.shifts ? dataState.shifts.find((x) => String(x.id) === String(shiftId)) : null;
          const elig = payload && Array.isArray(payload.eligible_angel_types) ? payload.eligible_angel_types : [];
          if (elig.length === 1) {
            data.angel_type_id = String(elig[0].id);
          }
        } catch (e) { /* ignore */ }
        // Open Staff/Public modal for type selection and apply
        if (payload) openSpApplyModal(shiftId, payload);
        return;
      }
      const cancelBtn = ev.target.closest('button.smv2-cancel[data-action="cancel"]');
      if (cancelBtn) {
        const entryId = cancelBtn.getAttribute('data-entry-id');
        postForm('/api/v2/shift-manager/cancel', { shift_entry_id: entryId })
          .then((resp) => {
            if (resp && resp.ok) {
              showToast('Canceled successfully', 'success');
              return refreshOnce();
            }
            const errCode = (resp && resp.error) || '';
            showToast(errorMessage(errCode, 'Cancel failed'), 'error');
            return undefined;
          })
          .catch(() => showToast('Cancel request failed', 'error'));
      }
    });
  }

  function openSpApplyModal(shiftId, payload) {
    const modalEl = document.getElementById('smv2-sp-modal');
    if (!modalEl) return;
    const titleEl = document.getElementById('smv2SpModalLabel');
    const summary = document.getElementById('smv2-sp-summary');
    const applyBtn = document.getElementById('smv2-sp-apply');
    const cancelBtn = document.getElementById('smv2-sp-cancel');
    const myEntryId = payload.my_entry_id || null;
    const eligMsg = document.getElementById('smv2-sp-elig');
    const typePicker = document.getElementById('smv2-sp-type-picker');
    const typeSelect = document.getElementById('smv2-sp-type-select');

    // Fill basics
    const time = `${formatTime(payload.start_ts)} – ${formatTime(payload.end_ts)}`;
    if (titleEl) titleEl.textContent = `${payload.title || 'Shift'} @ ${payload.location || ''}`;
    if (summary) summary.textContent = `${time} • ${payload.location || ''} • Required ${payload.required ?? '—'} • Assigned ${payload.assigned ?? '—'}`;

    // Configure Apply/Cancel state
    const isAssigned = !!payload.is_assigned;
    applyBtn.disabled = !payload.eligibility?.can_apply;
    cancelBtn.classList.toggle('d-none', !isAssigned);
    cancelBtn.onclick = () => {
      if (!myEntryId) return;
      postForm('/api/v2/shift-manager/cancel', { shift_entry_id: String(myEntryId) })
        .then((resp) => {
          if (resp && resp.ok) {
            showToast('Canceled successfully', 'success');
            // eslint-disable-next-line no-undef
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.hide();
            return refreshOnce();
          }
          const errCode = (resp && resp.error) || '';
          showToast(errorMessage(errCode, 'Cancel failed'), 'error');
          return undefined;
        })
        .catch(() => showToast('Cancel request failed', 'error'));
    };

    // Critter type picker
    const elig = Array.isArray(payload.eligible_angel_types) ? payload.eligible_angel_types : [];
    typeSelect.innerHTML = '';
    if (elig.length > 1) {
      typePicker.classList.remove('d-none');
      elig.forEach((e) => {
        const opt = document.createElement('option');
        opt.value = String(e.id);
        opt.textContent = `${e.name} (#${e.id})`;
        typeSelect.appendChild(opt);
      });
    } else {
      typePicker.classList.add('d-none');
    }

    eligMsg.textContent = payload.eligibility?.can_apply ? '' : (payload.eligibility?.capacity_full ? 'Shift is full' : (payload.eligibility?.overlaps ? 'Overlaps with your shift' : (payload.eligibility?.needs_cert ? 'Missing certification' : '')));

    // Wire Apply
    applyBtn.onclick = () => {
      const data = { shift_id: String(shiftId) };
      if (!typePicker.classList.contains('d-none') && typeSelect.value) {
        data.angel_type_id = typeSelect.value;
      } else if (elig.length === 1) {
        data.angel_type_id = String(elig[0].id);
      }
      postForm('/api/v2/shift-manager/apply', data)
        .then((resp) => {
          if (resp && resp.ok) {
            showToast('Applied successfully', 'success');
            // eslint-disable-next-line no-undef
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.hide();
            return refreshOnce();
          }
          const errCode = (resp && resp.error) || '';
          showToast(errorMessage(errCode, 'Apply failed'), 'error');
          return undefined;
        })
        .catch(() => showToast('Apply request failed', 'error'));
    };

    // eslint-disable-next-line no-undef
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  }

  function renderInitial() {
    // Render placeholders; first data load
    loadOnce();
    attachEvents();
  }

  let timer;
  function startPolling() {
    clearInterval(timer);
    timer = setInterval(() => {
      // Use the current global state instead of reading from DOM elements
      // This ensures the polling respects user's selected date and filters
      let state = { ...currentState };
      
      // Only update from DOM elements if they exist and currentState is incomplete
      if (!state.date) {
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        state.date = `${yyyy}-${mm}-${dd}`;
      }
      
      // Update time selectors if they have changed
      const timeStart = el('smv2-time-start');
      const timeEnd = el('smv2-time-end');
      if (timeStart && timeStart.value !== state.start) {
        state.start = timeStart.value;
        currentState.start = timeStart.value; // Keep global state in sync
      }
      if (timeEnd && timeEnd.value !== state.end) {
        state.end = timeEnd.value;
        currentState.end = timeEnd.value; // Keep global state in sync
      }
      
      // Get current selections from Choices.js if they exist
      const locEl = el('smv2-filter-locations');
      const stEl = el('smv2-filter-shift-types');
      const ctEl = el('smv2-filter-critter-types');
      
      if (locEl?.choices) {
        const selectedLocs = locEl.choices.getValue(true);
        state.locations = Array.isArray(selectedLocs) ? selectedLocs : [selectedLocs].filter(Boolean);
      }
      if (stEl?.choices) {
        const selectedSts = stEl.choices.getValue(true);
        state.shift_types = Array.isArray(selectedSts) ? selectedSts : [selectedSts].filter(Boolean);
      }
      if (ctEl?.choices) {
        const selectedCts = ctEl.choices.getValue(true);
        state.critter_types = Array.isArray(selectedCts) ? selectedCts : [selectedCts].filter(Boolean);
      }
      
      loadData(state).then((resp) => {
        // Do not re-render the topbar during polling to avoid interrupting user input
        updateUI(resp);
      });
    }, pollInterval);
  }

  // Manager modal helpers
  function renderAssignments(container, assignments) {
    if (!container) return;
    if (!Array.isArray(assignments) || assignments.length === 0) {
      container.innerHTML = '<div class="text-muted">No assignments yet.</div>';
      return;
    }
    container.innerHTML = assignments.map((a) => {
      const checked = a.freeloaded ? 'checked' : '';
      const angelTypeName = a.angel_type_name || 'Unknown';
      return `<div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
        <div class="flex-grow-1">
          <div class="fw-semibold">${a.user_name || (`#${a.user_id}`)}</div>
          <small class="text-muted">${angelTypeName}</small>
        </div>
        <div class="d-flex align-items-center gap-2">
          <div class="form-check form-switch me-2">
            <input class="form-check-input smv2-noshow" type="checkbox" role="switch" id="noshow-${a.entry_id}" data-entry-id="${a.entry_id}" ${checked}>
            <label class="form-check-label small" for="noshow-${a.entry_id}">No‑show</label>
          </div>
          <button class="btn btn-sm btn-outline-danger smv2-unassign" data-entry-id="${a.entry_id}">
            <i class="bi bi-person-dash"></i> Remove
          </button>
        </div>
      </div>`;
    }).join('');
  }

  function openManagerModal(shiftId) {
    const persona = document.getElementById('shift-manager-v2')?.dataset?.persona || 'public';
    if (persona !== 'manager') return;
    
    fetchJSON(`/api/v2/shift-manager/shift/${shiftId}`)
      .then((resp) => {
        if (!resp || !resp.ok) {
          showToast(`Failed to load shift: ${resp?.error || 'Unknown error'}`, 'error');
          return;
        }
        
        const title = `${resp.shift?.title || 'Shift'} @ ${resp.shift?.location || ''}`;
        const h = document.getElementById('smv2ModalLabel');
        if (h) h.textContent = title;
        renderAssignments(document.getElementById('smv2-assignments'), resp.assignments || []);
        
        // Populate critter type dropdown with the shift's needed types
        const angelTypeSelect = document.getElementById('smv2-assign-angeltype');
        
        if (angelTypeSelect) {
          // If Choices.js is already initialized, destroy it first
          if (angelTypeSelect.choices) {
            angelTypeSelect.choices.destroy();
          }
          
          if (resp.needed && Array.isArray(resp.needed) && resp.needed.length > 0) {
            const options = resp.needed.map(n => 
              `<option value="${n.angel_type_id}">${n.angel_type_name || 'Unknown'} (${n.count} needed)</option>`
            ).join('');
            angelTypeSelect.innerHTML = '<option value="">Select critter type…</option>' + options;
          } else {
            angelTypeSelect.innerHTML = '<option value="">No critter types needed</option>';
          }
          
          // Reinitialize Choices.js if available
          if (typeof Choices !== 'undefined') {
            try {
              angelTypeSelect.choices = new Choices(angelTypeSelect, {
                searchEnabled: false,
                itemSelectText: '',
                shouldSort: false
              });
            } catch (error) {
              console.error('Error initializing Choices.js:', error);
              // Fall back to regular select if Choices.js fails
            }
          }
        }
        // Wire actions
        const modalEl = document.getElementById('smv2-modal');
        if (modalEl) {
          // eslint-disable-next-line no-undef
          const modal = new bootstrap.Modal(modalEl);
          modal.show();
        }
        // Candidate search
        const search = document.getElementById('smv2-assign-search');
        const candidates = document.getElementById('smv2-assign-candidates');
        const assignBtn = document.getElementById('smv2-assign-selected');
        
        if (search && candidates && assignBtn) {
          let t;
          const run = () => {
            const q = search.value.trim();
            if (q.length < 2) {
              candidates.innerHTML = '<div class="text-muted small">Enter at least 2 characters to search</div>';
              assignBtn.disabled = true;
              return;
            }
            
            fetchJSON(`/api/v2/shift-manager/users?q=${encodeURIComponent(q)}`)
              .then((r) => {
                if (!r || !r.ok) { 
                  candidates.innerHTML = '<div class="text-muted small">No candidates found</div>'; 
                  assignBtn.disabled = true;
                  return; 
                }
                const list = (r.users || []).map((u) => `
                  <div class="form-check">
                    <input class="form-check-input candidate-checkbox" type="checkbox" id="cand-${u.id}" value="${u.id}">
                    <label class="form-check-label" for="cand-${u.id}">
                      <strong>${u.name}</strong> <span class="text-muted small">(ID: ${u.id})</span>
                    </label>
                  </div>`).join('');
                candidates.innerHTML = list || '<div class="text-muted small">No candidates found</div>';
                
                // Update button state when checkboxes change
                const checkboxes = candidates.querySelectorAll('.candidate-checkbox');
                checkboxes.forEach(cb => {
                  cb.addEventListener('change', updateAssignButtonState);
                });
                updateAssignButtonState();
              })
              .catch(() => { 
                candidates.innerHTML = '<div class="text-muted small">Search failed</div>'; 
                assignBtn.disabled = true;
              });
          };
          
          const updateAssignButtonState = () => {
            const selected = candidates.querySelectorAll('.candidate-checkbox:checked');
            const angelTypeSelected = document.getElementById('smv2-assign-angeltype').value;
            assignBtn.disabled = selected.length === 0 || !angelTypeSelected;
          };
          
          // Listen for angel type changes
          document.getElementById('smv2-assign-angeltype').addEventListener('change', updateAssignButtonState);
          
          search.oninput = () => { 
            clearTimeout(t); 
            t = setTimeout(run, 300); 
          };
          
          // Initial state
          candidates.innerHTML = '<div class="text-muted small">Search for people to assign to this shift</div>';
          assignBtn.disabled = true;
        }
        // Unassign handler (delegated)
        const assignWrap = document.getElementById('smv2-assignments');
        if (assignWrap) {
          assignWrap.onclick = (ev) => {
            const btn = ev.target.closest('button.smv2-unassign');
            if (btn) {
              const entryId = btn.getAttribute('data-entry-id');
              postForm('/api/v2/shift-manager/unassign', { shift_entry_id: entryId })
                .then((r) => {
                  if (r && r.ok) {
                    showToast('Removed assignment', 'success');
                    // Refresh modal data
                    return fetchJSON(`/api/v2/shift-manager/shift/${shiftId}`).then((r2) => renderAssignments(assignWrap, r2.assignments || []));
                  }
                  showToast(errorMessage(r?.error, 'Unassign failed'), 'error');
                  return undefined;
                })
                .catch(() => showToast('Unassign request failed', 'error'));
              return;
            }
            const toggle = ev.target.closest('input.smv2-noshow');
            if (toggle) {
              const entryId = toggle.getAttribute('data-entry-id');
              const freeloaded = toggle.checked ? '1' : '0';
              postForm('/api/v2/shift-manager/noshow', { shift_entry_id: entryId, freeloaded })
                .then((r) => {
                  if (r && r.ok) {
                    showToast(r.freeloaded ? 'Marked as no‑show' : 'No‑show removed', 'success');
                    return fetchJSON(`/api/v2/shift-manager/shift/${shiftId}`).then((r2) => renderAssignments(assignWrap, r2.assignments || []));
                  }
                  showToast(errorMessage(r?.error, 'No‑show update failed'), 'error');
                  return undefined;
                })
                .catch(() => showToast('No‑show request failed', 'error'));
            }
          };
        }
        // Assign Selected via candidates
        const assignSelectedBtn = document.getElementById('smv2-assign-selected');
        if (assignSelectedBtn) {
          assignSelectedBtn.onclick = (e) => {
            e.preventDefault();
            const angelTypeId = document.getElementById('smv2-assign-angeltype')?.value || '';
            const checks = document.querySelectorAll('#smv2-assign-candidates .candidate-checkbox:checked');
            const ids = Array.from(checks).map((c) => c.value);
            
            if (!angelTypeId) {
              showToast('Please select a critter type', 'error');
              return false;
            }
            if (ids.length === 0) {
              showToast('Please select people to assign', 'error');
              return false;
            }
            
            // Show loading state
            assignSelectedBtn.disabled = true;
            assignSelectedBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Adding...';
            
            const data = new URLSearchParams();
            data.append('shift_id', String(shiftId));
            data.append('angel_type_id', String(angelTypeId));
            ids.forEach((id) => data.append('user_ids[]', id));
            const headers = new Headers();
            headers.set('Accept', 'application/json');
            if (csrfToken) headers.set('X-CSRF-TOKEN', csrfToken);
            
            fetch('/api/v2/shift-manager/assign', { method: 'POST', credentials: 'same-origin', headers, body: data })
              .then((r) => r.json())
              .then((r) => {
                if (r && r.ok) {
                  showToast(`Successfully assigned ${ids.length} person(s)`, 'success');
                  // Clear selections and search
                  document.getElementById('smv2-assign-search').value = '';
                  document.getElementById('smv2-assign-candidates').innerHTML = '<div class="text-muted small">Search for people to assign to this shift</div>';
                  // Refresh assignments
                  return fetchJSON(`/api/v2/shift-manager/shift/${shiftId}`).then((r2) => {
                    renderAssignments(document.getElementById('smv2-assignments'), r2.assignments || []);
                    // Restore button state
                    assignSelectedBtn.innerHTML = '<i class="bi bi-person-plus"></i> Add Selected';
                    assignSelectedBtn.disabled = true;
                  });
                }
                showToast(errorMessage(r?.error, 'Assignment failed'), 'error');
                // Restore button state
                assignSelectedBtn.innerHTML = '<i class="bi bi-person-plus"></i> Add Selected';
                assignSelectedBtn.disabled = false;
                return undefined;
              })
              .catch(() => {
                showToast('Assignment request failed', 'error');
                // Restore button state
                assignSelectedBtn.innerHTML = '<i class="bi bi-person-plus"></i> Add Selected';
                assignSelectedBtn.disabled = false;
              });
            return false;
          };
        }
        // Worklog form
        const wform = document.getElementById('smv2-worklog-form');
        if (wform) {
          wform.onsubmit = (e) => {
            e.preventDefault();
            const userId = document.getElementById('smv2-worklog-user')?.value || '';
            const mins = document.getElementById('smv2-worklog-mins')?.value || '';
            const comment = document.getElementById('smv2-worklog-comment')?.value || '';
            if (!comment) { showToast('Comment is required for worklog.', 'error'); return false; }
            postForm('/api/v2/shift-manager/worklog', { user_id: userId, minutes: mins, comment })
              .then((r) => {
                if (r && r.ok) {
                  showToast('Worklog added', 'success');
                } else {
                  showToast(errorMessage(r?.error, 'Worklog failed'), 'error');
                }
              })
              .catch(() => showToast('Worklog request failed', 'error'));
            return false;
          };
        }
      })
      .catch(() => showToast('Failed to load shift: Network or server error', 'error'));
  }

  // Show modal explaining why user can't apply for a shift
  function showIneligibilityModal(shift) {
    let reasons = [];
    let title = 'Cannot Apply for This Shift';
    
    if (shift.eligibility?.capacity_full) {
      reasons.push('❌ This shift is currently full');
    }
    if (shift.eligibility?.overlaps) {
      reasons.push('❌ This shift overlaps with one of your existing shifts');
    }
    if (shift.eligibility?.needs_cert) {
      reasons.push('❌ You do not have the required certification/critter type for this shift');
    }
    
    if (reasons.length === 0) {
      reasons.push('❓ You cannot apply for this shift at this time');
    }
    
    const reasonsList = reasons.map(r => `<li class="mb-2">${r}</li>`).join('');
    
    const modalHtml = `
      <div class="modal fade" id="smv2-ineligibility-modal" tabindex="-1" aria-labelledby="smv2-ineligibility-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
              <h5 class="modal-title" id="smv2-ineligibility-title">${title}</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <h6 class="fw-semibold">${shift.title || 'Shift'}</h6>
                <p class="text-muted mb-2">
                  <i class="bi bi-clock me-1"></i> ${formatTime(shift.start_ts)} - ${formatTime(shift.end_ts)}<br>
                  <i class="bi bi-geo-alt me-1"></i> ${shift.location || 'Unknown location'}<br>
                  <i class="bi bi-people me-1"></i> ${shift.assigned || 0}/${shift.required || 1} assigned
                </p>
              </div>
              <div class="alert alert-warning">
                <strong>Reason(s) you cannot apply:</strong>
                <ul class="mb-0 mt-2">
                  ${reasonsList}
                </ul>
              </div>
              ${shift.eligibility?.needs_cert ? 
                '<div class="alert alert-info"><strong>Note:</strong> If you believe you should have access to this shift type, please contact your supervisor or admin.</div>' : 
                ''}
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
      </div>
    `;
    
    // Remove existing modal if any
    const existing = document.getElementById('smv2-ineligibility-modal');
    if (existing) existing.remove();
    
    // Add modal to DOM
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modalEl = document.getElementById('smv2-ineligibility-modal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
    
    // Clean up modal after hiding
    modalEl.addEventListener('hidden.bs.modal', () => {
      modalEl.remove();
    });
  }

  // Initialize Bootstrap tooltips for timeline grid cells
  function initializeTooltips() {
    // Initialize tooltips on existing elements
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl, {
      html: true,
      trigger: 'hover focus',
      placement: 'top'
    }));
    
    // Store tooltip instances for cleanup
    window.__SMV2_TOOLTIPS__ = tooltipList;
  }

  // Clean up existing tooltips before re-rendering
  function cleanupTooltips() {
    if (window.__SMV2_TOOLTIPS__) {
      window.__SMV2_TOOLTIPS__.forEach(tooltip => tooltip.dispose());
      window.__SMV2_TOOLTIPS__ = [];
    }
  }

  // Initialize additional event handlers for new partials
  function initializeTopBarHandlers() {
    const datePrev = el('smv2-date-prev');
    const dateNext = el('smv2-date-next');
    const search = el('smv2-search');
    const viewModeButtons = document.querySelectorAll('input[name="smv2-view-mode"]');

    if (datePrev) {
      datePrev.addEventListener('click', () => {
        const current = new Date(currentState.date);
        current.setDate(current.getDate() - 1);
        const newDate = current.toISOString().split('T')[0];
        currentState.date = newDate;
        // Update countdown display immediately
        updateCountdownDisplay(newDate);
        loadData(currentState).then((resp) => {
          renderTopbar(resp, currentState);
          updateUI(resp);
        });
      });
    }

    if (dateNext) {
      dateNext.addEventListener('click', () => {
        const current = new Date(currentState.date);
        current.setDate(current.getDate() + 1);
        const newDate = current.toISOString().split('T')[0];
        currentState.date = newDate;
        // Update countdown display immediately
        updateCountdownDisplay(newDate);
        loadData(currentState).then((resp) => {
          renderTopbar(resp, currentState);
          updateUI(resp);
        });
      });
    }

    if (search) {
      let searchTimeout;
      search.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
          currentState.q = search.value;
          loadData(currentState).then((resp) => {
            updateUI(resp);
          });
        }, 500);
      });
    }

    viewModeButtons.forEach(btn => {
      btn.addEventListener('change', () => {
        const timeline = el('smv2-timeline');
        const list = el('smv2-list');
        if (btn.value === 'timeline') {
          timeline?.classList.remove('d-none', 'btn-secondary');
          list?.classList.add('d-none', 'btn-info');
        } else {
          timeline?.classList.add('d-none', 'btn-secondary');
          list?.classList.remove('d-none', 'btn-info');
        }
      });
    });
  }

  renderInitial();
  initializeTopBarHandlers();
  startPolling();
})();
