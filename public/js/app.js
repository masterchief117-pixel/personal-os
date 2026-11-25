(() => {
  "use strict";

  document.querySelectorAll("form[data-confirm]").forEach((form) => {
    form.addEventListener("submit", (event) => {
      const message = form.dataset.confirm || "Continue?";
      if (!window.confirm(message)) event.preventDefault();
    });
  });

  document.querySelectorAll(".refresh-plan-button").forEach((button) => {
    button.addEventListener("click", () => window.location.reload());
  });

  const clock = document.getElementById("liveClock");
  const authForm = document.querySelector(".auth-form");
  if (authForm) {
    authForm.addEventListener("submit", () => {
      const button = authForm.querySelector("button[type='submit']");
      if (!button) return;
      button.disabled = true;
      button.textContent = "Signing in...";
    });
  }
  const dateLabel = document.getElementById("dateLabel");
  const greeting = document.getElementById("greeting");
  const clockZone = document.getElementById("clockZone");
  const modal = document.getElementById("taskModal");
  let modalReturnFocus = null;
  let noteModalReturnFocus = null;
  const taskTitle = document.getElementById("taskTitle");
  const taskForm = document.getElementById("taskForm");
  const taskList = document.getElementById("taskList");
  const toast = document.getElementById("toast");
  const sidebar = document.getElementById("sidebar");
  const menuButton = document.getElementById("menuButton");
  const csrfToken = document.getElementById("csrfToken")?.value || "";
  const csrfHeaders = () => ({ "Content-Type": "application/json", "X-CSRF-TOKEN": csrfToken });

  window.requestAnimationFrame(() => document.body.classList.add("is-loaded"));

  function updateDateTime() {
    const now = new Date();
    if (clock) {
      clock.textContent = new Intl.DateTimeFormat([], {
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
        hour12: false
      }).format(now);
    }
    if (dateLabel) {
      dateLabel.textContent = new Intl.DateTimeFormat([], {
        weekday: "long",
        month: "long",
        day: "numeric"
      }).format(now);
    }
    if (greeting) {
      const hour = now.getHours();
      greeting.textContent = hour < 12 ? "Good morning" : hour < 18 ? "Good afternoon" : "Good evening";
    }
    if (clockZone) {
      clockZone.textContent = Intl.DateTimeFormat().resolvedOptions().timeZone || "Local time";
    }
  }

  updateDateTime();
  window.setInterval(updateDateTime, 1000);

  const weatherIcon = document.getElementById("weatherIcon");
  const weatherTemperature = document.getElementById("weatherTemperature");
  const weatherConditions = document.getElementById("weatherConditions");
  const weatherLocation = document.getElementById("weatherLocation");

  const weatherIcons = {
    "Clear": "☀️",
    "Mainly Clear": "🌤️",
    "Partly Cloudy": "⛅",
    "Partly cloudy": "⛅",
    "Overcast": "☁️",
    "Fog": "🌫️",
    "Depositing Rime Fog": "🌫️",
    "Light Drizzle": "🌦️",
    "Moderate Drizzle": "🌦️",
    "Dense Drizzle": "🌧️",
    "Drizzle": "🌦️",
    "Light Freezing Drizzle": "🌨️",
    "Dense Freezing Drizzle": "🌨️",
    "Freezing Drizzle": "🌨️",
    "Slight Rain": "🌧️",
    "Moderate Rain": "🌧️",
    "Heavy Rain": "🌧️",
    "Rain": "🌧️",
    "Light Freezing Rain": "🌧️",
    "Heavy Freezing Rain": "🌧️",
    "Freezing Rain": "🌧️",
    "Slight Snow": "❄️",
    "Moderate Snow": "❄️",
    "Heavy Snow": "❄️",
    "Snow": "❄️",
    "Snow Grains": "❄️",
    "Slight Rain Showers": "🌦️",
    "Moderate Rain Showers": "🌧️",
    "Violent Rain Showers": "⛈️",
    "Rain Showers": "🌧️",
    "Slight Snow Showers": "🌨️",
    "Heavy Snow Showers": "🌨️",
    "Snow Showers": "🌨️",
    "Thunderstorm": "⛈️",
    "Thunderstorm with Hail": "⛈️",
    "Heavy Thunderstorm": "⛈️",
    "Unknown": "🌡️"
  };

  const apiBasePath = window.location.pathname.startsWith("/personal-os") ? "/personal-os/api" : "/api";

  let currentTempC = null;
  let tempUnit = localStorage.getItem("personal-os-temp-unit") || "C";

  function renderTemperature() {
    if (!weatherTemperature || currentTempC === null) return;
    if (tempUnit === "F") {
      const f = (currentTempC * 9 / 5) + 32;
      weatherTemperature.textContent = `${f.toFixed(1)}°F`;
    } else {
      weatherTemperature.textContent = `${currentTempC.toFixed(1)}°C`;
    }
  }

  const weatherWidget = document.getElementById("weatherWidget");
  if (weatherWidget) {
    weatherWidget.addEventListener("click", () => {
      if (currentTempC === null) return;
      tempUnit = tempUnit === "C" ? "F" : "C";
      localStorage.setItem("personal-os-temp-unit", tempUnit);
      renderTemperature();
      showToast(`Temperature unit: °${tempUnit}`);
    });
    weatherWidget.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        weatherWidget.click();
      }
    });
  }

  function updateDashboardStats() {
    const allRows = Array.from(document.querySelectorAll(".task-row"));
    const totalTasks = allRows.length;
    const completedTasks = allRows.filter((r) => r.classList.contains("is-complete") || r.dataset.status === "completed").length;
    const pendingTasks = Math.max(0, totalTasks - completedTasks);
    const completionRate = totalTasks > 0 ? Math.round((completedTasks / totalTasks) * 100) : 0;

    const sidebarCount = document.getElementById("sidebarTaskCount");
    if (sidebarCount) sidebarCount.textContent = String(pendingTasks);

    const headerCount = document.getElementById("taskHeaderCount");
    if (headerCount) headerCount.textContent = String(totalTasks);

    const ring = document.getElementById("completionRing");
    if (ring) ring.style.setProperty("--progress", String(completionRate));

    const ringText = document.getElementById("completionRateText");
    if (ringText) ringText.textContent = `${completionRate}%`;

    const statCompleted = document.getElementById("statCompletedTasks");
    if (statCompleted) statCompleted.textContent = String(completedTasks);

    const statActive = document.getElementById("statActiveTasks");
    if (statActive) statActive.textContent = String(pendingTasks);

    const statNotes = document.getElementById("statTotalNotes");
    if (statNotes) {
      const notesCount = document.querySelectorAll(".note-card:not(.empty-state)").length;
      statNotes.textContent = String(notesCount);
    }
  }

  async function fetchWeather() {
    if (!weatherTemperature || !weatherConditions || !weatherIcon) return;
    try {
      const response = await fetch(`${apiBasePath}/weather.php`, {
        headers: { Accept: "application/json" },
        cache: "no-store"
      });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.error || "Weather unavailable");

      const temp = Number(payload.temperature_c);
      if (!isNaN(temp)) {
        currentTempC = temp;
        renderTemperature();
      } else {
        weatherTemperature.textContent = "--°";
      }

      const windSpeed = Number(payload.wind_speed_kmh);
      const windInfo = !isNaN(windSpeed) ? ` · ${windSpeed.toFixed(0)} km/h wind` : "";
      weatherConditions.textContent = `${payload.conditions || "Current weather"}${windInfo}`;

      const emoji = payload.emoji || weatherIcons[payload.conditions] || weatherIcons.Unknown;
      weatherIcon.textContent = emoji;
      weatherIcon.setAttribute("title", payload.conditions || "Weather");

      if (weatherLocation && payload.location) {
        weatherLocation.textContent = payload.location.replace(/_/g, " ");
      }
    } catch (error) {
      weatherConditions.textContent = error.message || "Weather unavailable";
      weatherIcon.textContent = "🌡️";
    }
  }

  const diskUsageProgress = document.getElementById("diskUsageProgress");
  const diskUsageBar = document.getElementById("diskUsageBar");
  const diskUsageText = document.getElementById("diskUsageText");
  const serverInfoText = document.getElementById("serverInfoText");

  async function fetchSystemVitals() {
    if (!diskUsageProgress && !diskUsageBar && !diskUsageText && !serverInfoText) return;
    try {
      const response = await fetch(`${apiBasePath}/sysinfo.php`, {
        headers: { Accept: "application/json" },
        cache: "no-store"
      });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.error || "System metrics unavailable");

      const freeGb = payload.disk_free_gb ?? (Number(payload.disk_free_bytes) / (1024 ** 3)).toFixed(1);
      const totalGb = payload.disk_total_gb ?? (Number(payload.disk_total_bytes) / (1024 ** 3)).toFixed(1);
      const usedPercent = Math.max(0, Math.min(100, Number(payload.disk_used_percent ?? 0)));

      if (diskUsageBar) {
        diskUsageBar.style.width = `${usedPercent}%`;
      }
      if (diskUsageProgress) {
        diskUsageProgress.style.setProperty("--progress", `${usedPercent}%`);
        diskUsageProgress.setAttribute("aria-valuenow", usedPercent.toFixed(1));
        diskUsageProgress.setAttribute("aria-valuetext", `${usedPercent.toFixed(1)}% used`);
      }
      if (diskUsageText) {
        diskUsageText.textContent = payload.disk_usage_formatted || `${freeGb} GB free / ${totalGb} GB total`;
      }
      if (serverInfoText) {
        serverInfoText.textContent = `PHP ${payload.php_version} · ${payload.server_os}`;
      }
    } catch (error) {
      if (diskUsageText) diskUsageText.textContent = error.message || "System metrics unavailable";
      if (serverInfoText) serverInfoText.textContent = "Server information unavailable";
    }
  }

  async function fetchTasks() {
    if (!taskList) return;
    try {
      const response = await fetch(`${apiBasePath}/tasks.php`, {
        headers: { Accept: "application/json" },
        cache: "no-store"
      });
      if (!response.ok) return;
      await response.json();
    } catch (_) {
      // Keep SSR render
    }
  }

  async function fetchNotes() {
    const notesGrid = document.querySelector(".notes-grid");
    if (!notesGrid) return;
    try {
      const response = await fetch(`${apiBasePath}/notes.php`, {
        headers: { Accept: "application/json" },
        cache: "no-store"
      });
      if (!response.ok) return;
      await response.json();
    } catch (_) {
      // Keep SSR render
    }
  }

  // Initial page load fetchers & stats calculation
  if (taskList) fetchTasks();
  if (document.querySelector(".notes-grid")) fetchNotes();
  if (weatherTemperature) fetchWeather();
  if (diskUsageProgress || diskUsageBar || diskUsageText || serverInfoText) fetchSystemVitals();
  updateDashboardStats();

  // Network lifecycle synchronization
  window.addEventListener("online", () => {
    showToast("Back online — live sync active");
    if (weatherTemperature) fetchWeather();
    if (diskUsageProgress) fetchSystemVitals();
    if (taskList) fetchTasks();
  });
  window.addEventListener("offline", () => {
    showToast("Offline mode — local cache active");
  });

  // Periodic background refresh intervals: weather every 30 minutes, system vitals every 5 minutes
  if (weatherTemperature) {
    window.setInterval(fetchWeather, 30 * 60 * 1000);
  }
  if (diskUsageProgress || diskUsageBar || diskUsageText || serverInfoText) {
    window.setInterval(fetchSystemVitals, 5 * 60 * 1000);
  }

  function openModal(returnFocusTarget = document.activeElement) {
    if (!modal) return;
    modalReturnFocus = returnFocusTarget;
    modal.classList.add("open");
    modal.setAttribute("aria-hidden", "false");
    window.setTimeout(() => taskTitle && taskTitle.focus(), 180);
  }

  function closeModal() {
    if (!modal) return;
    modal.classList.remove("open");
    modal.setAttribute("aria-hidden", "true");
    if (modalReturnFocus instanceof HTMLElement) modalReturnFocus.focus();
  }

  ["addTaskButton", "sidebarAddTask"].forEach((id) => {
    const button = document.getElementById(id);
    if (button) button.addEventListener("click", () => openModal());
  });

  document.querySelectorAll(".modal-close").forEach((button) => {
    button.addEventListener("click", closeModal);
  });

  if (modal) {
    modal.addEventListener("click", (event) => {
      if (event.target === modal) closeModal();
    });
  }

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeModal();
      closeNoteModal();
    }
    const isTyping = event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement || event.target instanceof HTMLSelectElement;
    if (!isTyping && event.key.toLowerCase() === "n") openModal();
  });

  if (taskForm) {
    taskForm.addEventListener("submit", (event) => {
      event.preventDefault();
      const data = new FormData(taskForm);
      const title = String(data.get("title") || "").trim();
      if (!title) {
        taskTitle.focus();
        return;
      }

      const category = String(data.get("category") || "Personal");
      const priority = String(data.get("priority") || "medium");
      const dueAt = String(data.get("due_at") || "");
      const recurrence = String(data.get("recurrence") || "none");
      const row = document.createElement("article");
      row.className = "task-row";
      row.dataset.status = "todo";
      row.dataset.category = category;
      row.dataset.priority = priority;
      row.innerHTML =
        '<button class="task-check" type="button" aria-label="Toggle task">' +
        '<svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg></button>' +
        "<div class=\"task-copy\"><h3></h3><div class=\"task-meta\"><span class=\"category\"></span></div></div>" +
        '<span class="priority"></span><button class="task-menu" type="button" aria-label="Task options">•••</button>';
      row.querySelector("h3").textContent = title;
      row.querySelector(".category").textContent = category;
      const priorityElement = row.querySelector(".priority");
      priorityElement.textContent = priority;
      priorityElement.classList.add(`priority-${priority}`);

      const emptyState = taskList && taskList.querySelector(".empty-state");
      if (emptyState) emptyState.remove();
      if (taskList) taskList.prepend(row);

      const submitButton = taskForm.querySelector("button[type='submit']");
      if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = "Adding...";
      }

      fetch(`${apiBasePath}/tasks.php`, {
        method: "POST",
        headers: csrfHeaders(),
        body: JSON.stringify({ title, category, priority, due_at: dueAt, recurrence })
      })
        .then(async (response) => {
          const payload = await response.json();
          if (!response.ok) throw new Error(payload.error || "Could not add task");
          row.dataset.taskId = String(payload.id);
          taskForm.reset();
          closeModal();
          showToast("Task added");
          updateDashboardStats();
          if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = "Add task";
          }
        })
        .catch((error) => {
          row.remove();
          showToast(error.message);
          updateDashboardStats();
          if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = "Add task";
          }
        });
    });
  }

  if (taskList) {
    taskList.addEventListener("click", (event) => {
      const check = event.target.closest(".task-check");
      if (check) {
      const row = check.closest(".task-row");
      const previousStatus = row.dataset.status || "todo";
      const wasComplete = row.classList.contains("is-complete");
      const nextStatus = row.classList.contains("is-complete") ? "todo" : "completed";
      row.classList.toggle("is-complete");
      row.dataset.status = row.classList.contains("is-complete") ? "completed" : "todo";
      updateDashboardStats();
      if (row.dataset.taskId) {
        fetch(`${apiBasePath}/tasks.php`, {
          method: "PATCH",
          headers: csrfHeaders(),
          body: JSON.stringify({ id: Number(row.dataset.taskId), status: nextStatus })
        }).then(async (response) => {
          const payload = await response.json();
          if (!response.ok) throw new Error(payload.error || "Could not save task status");
          updateKanbanCard(row.dataset.taskId, nextStatus);
        }).catch((error) => {
          row.classList.toggle("is-complete", wasComplete);
          row.dataset.status = previousStatus;
          updateDashboardStats();
          showToast(error.message);
        });
      }
      showToast(row.classList.contains("is-complete") ? "Task completed" : "Task reopened");
        return;
      }

      const menu = event.target.closest(".task-menu");
      if (menu) {
        const row = menu.closest(".task-row");
        if (!window.confirm("Delete this task?")) return;
        fetch(`${apiBasePath}/tasks.php`, {
          method: "DELETE",
          headers: csrfHeaders(),
          body: JSON.stringify({ id: Number(row.dataset.taskId) })
        }).then((response) => {
          if (!response.ok) throw new Error("Could not delete task");
          row.remove();
          updateDashboardStats();
          showToast("Task deleted");
        }).catch((error) => showToast(error.message));
      }
    });
  }

  if (menuButton && sidebar) {
    menuButton.addEventListener("click", () => {
      const isOpen = sidebar.classList.toggle("open");
      menuButton.setAttribute("aria-expanded", String(isOpen));
    });

    document.querySelectorAll(".nav-item").forEach((item) => {
      item.addEventListener("click", () => {
        sidebar.classList.remove("open");
        menuButton.setAttribute("aria-expanded", "false");
      });
    });
  }

  const addNoteButton = document.getElementById("addNoteButton");
  const noteModal = document.getElementById("noteModal");
  const noteForm = document.getElementById("noteForm");
  const noteTitle = document.getElementById("noteTitle");

  function closeNoteModal() {
    if (!noteModal) return;
    noteModal.classList.remove("open");
    noteModal.setAttribute("aria-hidden", "true");
    if (noteModalReturnFocus instanceof HTMLElement) noteModalReturnFocus.focus();
  }

  function openNoteModal(returnFocusTarget = document.activeElement) {
    if (!noteModal) return;
    noteModalReturnFocus = returnFocusTarget;
    noteModal.classList.add("open");
    noteModal.setAttribute("aria-hidden", "false");
    window.setTimeout(() => noteTitle && noteTitle.focus(), 180);
  }

  if (addNoteButton && noteModal) {
    addNoteButton.addEventListener("click", () => openNoteModal());
  }
  document.querySelectorAll(".note-modal-close").forEach((button) => {
    button.addEventListener("click", closeNoteModal);
  });
  if (noteModal) {
    noteModal.addEventListener("click", (event) => {
      if (event.target === noteModal) closeNoteModal();
    });
  }

  if (noteForm) {
    noteForm.addEventListener("submit", (event) => {
      event.preventDefault();
      const data = new FormData(noteForm);
      const title = String(data.get("title") || "").trim();
      const content = String(data.get("content") || "").trim();
      const color = String(data.get("color") || "violet");
      const is_pinned = data.has("is_pinned");
      const payload = { title, content, color, is_pinned };
      fetch(`${apiBasePath}/notes.php`, {
        method: "POST",
        headers: csrfHeaders(),
        body: JSON.stringify(payload)
      })
        .then(async (response) => {
          const result = await response.json();
          if (!response.ok) throw new Error(result.error || "Could not add note");
          const notesGrid = document.querySelector(".notes-grid");
          if (notesGrid) {
            const emptyState = notesGrid.querySelector(".empty-state");
            if (emptyState) emptyState.remove();
            const noteCard = document.createElement("article");
            noteCard.className = `note-card note-${color}`;
            noteCard.id = `note-${result.id}`;
            noteCard.dataset.noteId = String(result.id);
            if (is_pinned) {
              const pin = document.createElement("span");
              pin.className = "pin";
              pin.setAttribute("aria-label", "Pinned");
              pin.textContent = "◆";
              noteCard.appendChild(pin);
            }
            const delBtn = document.createElement("button");
            delBtn.className = "note-delete";
            delBtn.type = "button";
            delBtn.setAttribute("aria-label", "Delete note");
            delBtn.setAttribute("title", "Delete note");
            delBtn.textContent = "×";
            noteCard.appendChild(delBtn);

            const h3 = document.createElement("h3");
            h3.textContent = title || "Untitled";
            const p = document.createElement("p");
            p.textContent = content;
            const time = document.createElement("time");
            time.textContent = "Just now";
            noteCard.appendChild(h3);
            noteCard.appendChild(p);
            noteCard.appendChild(time);
            notesGrid.prepend(noteCard);
          }
          noteForm.reset();
          closeNoteModal();
          showToast("Note saved");
          updateDashboardStats();
        })
        .catch((error) => showToast(error.message));
    });
  }

  const notesGrid = document.querySelector(".notes-grid");
  if (notesGrid) {
    notesGrid.addEventListener("click", (event) => {
      const deleteButton = event.target.closest(".note-delete");
      if (!deleteButton) return;
      const card = deleteButton.closest(".note-card");
      if (!card || !card.dataset.noteId) return;
      if (!window.confirm("Delete this note?")) return;

      fetch(`${apiBasePath}/notes.php`, {
        method: "DELETE",
        headers: csrfHeaders(),
        body: JSON.stringify({ id: Number(card.dataset.noteId) })
      })
        .then(async (response) => {
          if (!response.ok) throw new Error("Could not delete note");
          card.remove();
          updateDashboardStats();
          showToast("Note deleted");
        })
        .catch((error) => showToast(error.message));
    });
  }

  function showToast(message) {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add("show");
    window.clearTimeout(showToast.timeout);
    showToast.timeout = window.setTimeout(() => toast.classList.remove("show"), 2200);
  }

  const searchOverlay = document.getElementById("searchOverlay");
  const globalSearchButton = document.getElementById("globalSearchButton");
  const globalSearch = document.getElementById("globalSearch");
  const closeSearch = document.getElementById("closeSearch");
  const commandResults = document.getElementById("commandResults");
  const commandEmpty = document.getElementById("commandEmpty");
  const commandStatus = document.getElementById("commandStatus");
  const taskFilterButton = document.getElementById("taskFilterButton");
  const taskFilterModes = ["all", "todo", "in_progress", "completed"];
  let taskFilterIndex = 0;
  let searchReturnFocus = null;
  let activeCommandIndex = 0;
  let currentCommands = [];
  let remoteSearchResults = [];
  let remoteSearchQuery = "";
  let remoteSearchLoading = false;
  let remoteSearchError = false;
  let remoteSearchTimer = 0;
  let remoteSearchController = null;
  let remoteSearchSequence = 0;

  function clearRemoteSearch() {
    window.clearTimeout(remoteSearchTimer);
    remoteSearchController?.abort();
    remoteSearchController = null;
    remoteSearchSequence += 1;
    remoteSearchResults = [];
    remoteSearchQuery = "";
    remoteSearchLoading = false;
    remoteSearchError = false;
  }

  function validateRemoteSearchResults(payload) {
    if (!payload || !Array.isArray(payload.results) || payload.results.length > 20) {
      throw new Error("Workspace search returned an invalid response");
    }

    const routeByType = {
      task: "/personal-os/",
      note: "/personal-os/",
      goal: "/personal-os/goals.php",
      habit: "/personal-os/habits.php",
      journal: "/personal-os/journal.php"
    };

    return payload.results.map((result) => {
      if (!result || typeof result !== "object"
        || !["task", "note", "goal", "habit", "journal"].includes(result.type)
        || typeof result.id !== "string"
        || typeof result.title !== "string"
        || typeof result.detail !== "string"
        || typeof result.href !== "string"
        || result.title.length > 200
        || result.detail.length > 240) {
        throw new Error("Workspace search returned an invalid result");
      }

      const match = result.id.match(/^(task|note|goal|habit|journal)-([1-9]\d*)$/);
      if (!match || match[1] !== result.type) {
        throw new Error("Workspace search returned an invalid record identifier");
      }

      let destination;
      try {
        destination = new URL(result.href, window.location.origin);
      } catch {
        throw new Error("Workspace search returned an invalid destination");
      }
      if (destination.origin !== window.location.origin
        || destination.pathname !== routeByType[result.type]
        || destination.username
        || destination.password) {
        throw new Error("Workspace search returned an unsafe destination");
      }

      const recordId = match[2];
      if (result.type === "task" || result.type === "note") {
        const parameter = result.type === "task" ? "focus_task" : "focus_note";
        if (destination.searchParams.get(parameter) !== recordId
          || destination.searchParams.size !== 1
          || destination.hash) {
          throw new Error("Workspace search returned an invalid dashboard destination");
        }
      } else if (result.type === "goal" || result.type === "habit") {
        const anchor = `${result.type}-${recordId}`;
        if (destination.hash !== `#${anchor}` || destination.search) {
          throw new Error("Workspace search returned an invalid section destination");
        }
      } else {
        const dates = destination.searchParams.getAll("date");
        if (dates.length !== 1
          || !/^\d{4}-\d{2}-\d{2}$/.test(dates[0])
          || destination.searchParams.size !== 1
          || destination.hash) {
          throw new Error("Workspace search returned an invalid journal destination");
        }
      }

      return {
        id: result.id,
        kind: "remote",
        icon: { task: "✓", note: "✎", goal: "◎", habit: "↻", journal: "▤" }[result.type],
        label: result.title,
        hint: result.detail,
        href: destination.href,
        searchText: `${result.title} ${result.detail}`
      };
    });
  }

  function scheduleRemoteSearch(value) {
    window.clearTimeout(remoteSearchTimer);
    remoteSearchController?.abort();
    remoteSearchController = null;
    remoteSearchResults = [];
    remoteSearchQuery = value.trim();
    remoteSearchError = false;
    const requestId = ++remoteSearchSequence;
    remoteSearchLoading = remoteSearchQuery.length >= 2;

    if (!remoteSearchLoading) {
      renderSearchResults();
      return;
    }

    renderSearchResults();
    remoteSearchTimer = window.setTimeout(async () => {
      const controller = new AbortController();
      remoteSearchController = controller;
      try {
        const response = await fetch(`${apiBasePath}/search.php`, {
          method: "POST",
          headers: { ...csrfHeaders(), Accept: "application/json" },
          body: JSON.stringify({ query: remoteSearchQuery }),
          cache: "no-store",
          signal: controller.signal
        });
        let payload;
        try {
          payload = await response.json();
        } catch {
          throw new Error("Workspace search returned an invalid response");
        }
        if (!response.ok) {
          throw new Error(typeof payload.error === "string" ? payload.error : "Workspace search failed");
        }

        const validatedResults = validateRemoteSearchResults(payload);
        if (requestId !== remoteSearchSequence) return;
        remoteSearchResults = validatedResults;
      } catch (error) {
        if (error.name === "AbortError" || requestId !== remoteSearchSequence) return;
        remoteSearchError = true;
        console.error("Workspace search failed.", error);
      } finally {
        if (requestId === remoteSearchSequence) {
          remoteSearchLoading = false;
          remoteSearchController = null;
          renderSearchResults();
        }
      }
    }, 180);
  }

  function normalizeSearch(value) {
    return String(value || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase()
      .trim();
  }

  function applyContentFilters() {
    const query = normalizeSearch(globalSearch?.value);
    const taskMode = taskFilterModes[taskFilterIndex];
    document.querySelectorAll(".task-row").forEach((row) => {
      const matchesStatus = taskMode === "all" || row.dataset.status === taskMode;
      const matchesQuery = !query || normalizeSearch(row.textContent).includes(query);
      row.hidden = !matchesStatus || !matchesQuery;
    });
    document.querySelectorAll(".note-card").forEach((card) => {
      card.hidden = Boolean(query) && !normalizeSearch(card.textContent).includes(query);
    });
  }

  function getCommandItems(query) {
    const actions = [
      {
        id: "create-task",
        kind: "action",
        icon: "+",
        label: "Create a task",
        hint: "Quick action",
        searchText: "new add quick capture task",
        run: (returnTarget) => openModal(returnTarget)
      },
      {
        id: "create-note",
        kind: "action",
        icon: "✎",
        label: "Create a note",
        hint: "Quick action",
        searchText: "new add capture thought note",
        run: (returnTarget) => openNoteModal(returnTarget)
      }
    ];
    const navigation = Array.from(document.querySelectorAll(".nav-item")).map((link, index) => {
      const label = link.querySelector("span")?.textContent.trim() || link.textContent.trim();
      const href = link.getAttribute("href") || "";
      return {
        id: `navigate-${index}`,
        kind: "navigate",
        icon: "↗",
        label,
        hint: href.startsWith("#") ? "Jump to section" : "Open page",
        href,
        searchText: `${label} ${href}`
      };
    });
    const taskMode = taskFilterModes[taskFilterIndex];
    const tasks = Array.from(document.querySelectorAll(".task-row"))
      .filter((row) => taskMode === "all" || row.dataset.status === taskMode)
      .map((row, index) => {
        const label = row.querySelector("h3")?.textContent.trim() || "Untitled task";
        const category = row.querySelector(".category")?.textContent.trim() || "Task";
        const status = (row.dataset.status || "todo").replace(/_/g, " ");
        return {
          id: `task-${row.dataset.taskId || index}`,
          kind: "item",
          icon: "✓",
          label,
          hint: `${category} · ${status}`,
          searchText: `${label} ${category} ${status}`,
          element: row
        };
      });
    const notes = Array.from(document.querySelectorAll(".note-card[data-note-id]")).map((card) => {
      const label = card.querySelector("h3")?.textContent.trim() || "Untitled note";
      const content = card.querySelector("p")?.textContent.trim() || "";
      return {
        id: `note-${card.dataset.noteId}`,
        kind: "item",
        icon: "✎",
        label,
        hint: content.slice(0, 72) || "Note",
        searchText: `${label} ${content}`,
        element: card
      };
    });
    const tokens = normalizeSearch(query).split(/\s+/).filter(Boolean);

    return [...actions, ...navigation, ...tasks, ...notes].filter((item) => {
      const searchable = normalizeSearch(`${item.label} ${item.hint} ${item.searchText}`);
      return tokens.every((token) => searchable.includes(token));
    });
  }

  function updateActiveCommand() {
    const options = commandResults?.querySelectorAll(".command-result") || [];
    options.forEach((option, index) => {
      option.setAttribute("aria-selected", String(index === activeCommandIndex));
    });
    if (options[activeCommandIndex]) {
      globalSearch?.setAttribute("aria-activedescendant", options[activeCommandIndex].id);
      if (searchOverlay?.classList.contains("open")) {
        options[activeCommandIndex].scrollIntoView({ block: "nearest" });
      }
    } else {
      globalSearch?.removeAttribute("aria-activedescendant");
    }
  }

  function activateCommand(index) {
    const command = currentCommands[index];
    if (!command) return;
    const returnTarget = searchReturnFocus instanceof HTMLElement ? searchReturnFocus : globalSearchButton;
    hideSearch({ restoreFocus: false });

    if (command.kind === "action") {
      command.run(returnTarget);
      return;
    }
    if (command.kind === "remote") {
      window.location.assign(command.href);
      return;
    }
    if (command.kind === "navigate" && !command.href.startsWith("#")) {
      window.location.assign(command.href);
      return;
    }

    const target = command.kind === "navigate"
      ? document.getElementById(command.href.slice(1))
      : command.element;
    if (!target || !target.isConnected) {
      showToast("That result is no longer available");
      return;
    }
    focusCommandTarget(target);
  }

  function focusCommandTarget(target) {
    target.setAttribute("tabindex", "-1");
    target.scrollIntoView({
      behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth",
      block: "center"
    });
    target.focus({ preventScroll: true });
    target.classList.add("command-target");
    window.setTimeout(() => target.classList.remove("command-target"), 1500);
  }

  function focusRequestedDashboardItem() {
    const params = new URLSearchParams(window.location.search);
    const taskId = params.get("focus_task");
    const noteId = params.get("focus_note");
    if (taskId === null && noteId === null) return;

    const focusId = taskId && /^[1-9]\d*$/.test(taskId)
      ? `task-${taskId}`
      : noteId && /^[1-9]\d*$/.test(noteId) ? `note-${noteId}` : "";
    params.delete("focus_task");
    params.delete("focus_note");
    const query = params.toString();
    window.history.replaceState(
      window.history.state,
      "",
      `${window.location.pathname}${query ? `?${query}` : ""}${window.location.hash}`
    );

    if (focusId) {
      window.requestAnimationFrame(() => {
        const target = document.getElementById(focusId);
        if (target) focusCommandTarget(target);
      });
    }
  }

  function renderSearchResults() {
    if (!globalSearch || !commandResults || !commandEmpty || !commandStatus) return;
    const query = globalSearch.value.trim();
    const localCommands = getCommandItems(globalSearch.value);
    const localRecordIds = new Set(
      localCommands.filter((command) => command.kind === "item").map((command) => command.id)
    );
    const remoteCommands = remoteSearchQuery === query
      ? remoteSearchResults.filter((command) => !localRecordIds.has(command.id))
      : [];
    currentCommands = [...localCommands, ...remoteCommands];
    activeCommandIndex = Math.min(activeCommandIndex, Math.max(0, currentCommands.length - 1));
    commandResults.replaceChildren();
    commandEmpty.hidden = currentCommands.length > 0 || remoteSearchLoading;
    commandEmpty.textContent = remoteSearchError
      ? "Workspace search is unavailable. Try again."
      : "No matches. Try a page name, task, note, goal, habit, or journal date.";
    commandStatus.textContent = remoteSearchLoading
      ? "Searching your workspace…"
      : remoteSearchError
        ? "Workspace search unavailable; local results shown"
        : remoteSearchQuery === query && query.length >= 2
          ? `${currentCommands.length} workspace result${currentCommands.length === 1 ? "" : "s"}`
          : currentCommands.length
            ? `${currentCommands.length} result${currentCommands.length === 1 ? "" : "s"}`
            : "Search or jump anywhere";

    currentCommands.forEach((command, index) => {
      const option = document.createElement("button");
      option.type = "button";
      option.className = "command-result";
      option.id = `command-option-${index}`;
      option.setAttribute("role", "option");
      option.setAttribute("aria-selected", String(index === activeCommandIndex));
      option.tabIndex = -1;
      option.dataset.commandIndex = String(index);

      const icon = document.createElement("span");
      icon.className = "command-result-icon";
      icon.setAttribute("aria-hidden", "true");
      icon.textContent = command.icon;
      const copy = document.createElement("span");
      copy.className = "command-result-copy";
      const label = document.createElement("strong");
      label.textContent = command.label;
      const hint = document.createElement("small");
      hint.textContent = command.hint;
      copy.append(label, hint);
      option.append(icon, copy);
      option.addEventListener("click", () => activateCommand(index));
      commandResults.append(option);
    });
    updateActiveCommand();
  }

  function openSearch() {
    if (!searchOverlay || !globalSearch) return;
    if (searchOverlay.classList.contains("open")) {
      globalSearch.focus();
      return;
    }
    searchReturnFocus = document.activeElement;
    clearRemoteSearch();
    globalSearch.value = "";
    activeCommandIndex = 0;
    searchOverlay.classList.add("open");
    searchOverlay.setAttribute("aria-hidden", "false");
    globalSearch.setAttribute("aria-expanded", "true");
    globalSearchButton?.setAttribute("aria-expanded", "true");
    applyContentFilters();
    renderSearchResults();
    window.setTimeout(() => globalSearch.focus(), 120);
  }

  function hideSearch({ restoreFocus = true } = {}) {
    if (!searchOverlay) return;
    if (!searchOverlay.classList.contains("open")) return;
    searchOverlay.classList.remove("open");
    searchOverlay.setAttribute("aria-hidden", "true");
    globalSearch?.setAttribute("aria-expanded", "false");
    globalSearchButton?.setAttribute("aria-expanded", "false");
    clearRemoteSearch();
    if (globalSearch) globalSearch.value = "";
    activeCommandIndex = 0;
    applyContentFilters();
    renderSearchResults();
    const returnTarget = searchReturnFocus;
    searchReturnFocus = null;
    if (restoreFocus && returnTarget instanceof HTMLElement) returnTarget.focus();
  }

  if (globalSearchButton) globalSearchButton.addEventListener("click", openSearch);
  if (closeSearch) closeSearch.addEventListener("click", () => hideSearch());
  if (searchOverlay) {
    searchOverlay.addEventListener("click", (event) => {
      if (event.target === searchOverlay) hideSearch();
    });
  }

  if (globalSearch) {
    globalSearch.addEventListener("input", () => {
      activeCommandIndex = 0;
      applyContentFilters();
      renderSearchResults();
      scheduleRemoteSearch(globalSearch.value);
    });
  }

  focusRequestedDashboardItem();

  if (taskFilterButton) {
    taskFilterButton.addEventListener("click", () => {
      taskFilterIndex = (taskFilterIndex + 1) % taskFilterModes.length;
      const mode = taskFilterModes[taskFilterIndex];
      const labels = { all: "All tasks", todo: "To do", in_progress: "In progress", completed: "Completed" };
      taskFilterButton.textContent = `${labels[mode]} ⌄`;
      applyContentFilters();
      renderSearchResults();
    });
  }

  document.addEventListener("keydown", (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k") {
      event.preventDefault();
      if (modal?.classList.contains("open") || noteModal?.classList.contains("open")) return;
      if (searchOverlay?.classList.contains("open")) hideSearch();
      else openSearch();
      return;
    }
    if (!searchOverlay?.classList.contains("open")) return;

    if (event.key === "Escape") {
      event.preventDefault();
      hideSearch();
      return;
    }
    if (event.key === "Tab") {
      const focusable = Array.from(searchOverlay.querySelectorAll("input:not(:disabled), button:not(:disabled):not([tabindex='-1'])"));
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last?.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first?.focus();
      }
      return;
    }

    const isSearchControl = event.target === globalSearch || event.target.closest?.(".command-result");
    if (!isSearchControl) return;
    if (event.key === "ArrowDown" || event.key === "ArrowUp") {
      if (currentCommands.length === 0) return;
      event.preventDefault();
      const direction = event.key === "ArrowDown" ? 1 : -1;
      activeCommandIndex = (activeCommandIndex + direction + currentCommands.length) % currentCommands.length;
      updateActiveCommand();
    } else if (event.key === "Enter" && currentCommands.length > 0) {
      event.preventDefault();
      activateCommand(activeCommandIndex);
    }
  });

  if ("serviceWorker" in navigator) {
    window.addEventListener("load", () => {
      navigator.serviceWorker.register("/personal-os/sw.js").catch((error) => {
        console.error("Offline support could not be enabled.", error);
      });
    });
  }

  document.querySelectorAll(".calendar-day[data-date]").forEach((day) => {
    day.addEventListener("dragover", (event) => {
      event.preventDefault();
      day.classList.add("drag-over");
    });
    day.addEventListener("dragleave", () => day.classList.remove("drag-over"));
    day.addEventListener("drop", (event) => {
      event.preventDefault();
      day.classList.remove("drag-over");
      const taskId = event.dataTransfer.getData("text/plain");
      if (!taskId) return;
      const date = day.dataset.date;
      fetch(`${apiBasePath}/tasks.php`, {
        method: "PATCH",
        headers: csrfHeaders(),
        body: JSON.stringify({ id: Number(taskId), due_at: `${date} 09:00:00` })
      }).then((response) => {
        if (!response.ok) throw new Error("Could not reschedule task");
        window.location.reload();
      }).catch((error) => showToast(error.message));
    });
  });

  document.querySelectorAll(".calendar-task[data-task-id]").forEach((task) => {
    task.addEventListener("dragstart", (event) => {
      event.dataTransfer.setData("text/plain", task.dataset.taskId);
      event.dataTransfer.effectAllowed = "move";
    });
  });

  let installPrompt = null;
  window.addEventListener("beforeinstallprompt", (event) => {
    event.preventDefault();
    installPrompt = event;
    installButton.hidden = false;
  });
  const installButton = document.createElement("button");
  installButton.className = "primary-button install-app-button";
  installButton.type = "button";
  installButton.textContent = "Install app";
  installButton.hidden = true;
  document.querySelector(".topbar-actions")?.appendChild(installButton);
  window.addEventListener("appinstalled", () => {
    installButton.hidden = true;
    showToast("Personal OS installed");
  });
  installButton.addEventListener("click", async () => {
    if (!installPrompt) return;
    installPrompt.prompt();
    await installPrompt.userChoice;
    installPrompt = null;
  });
  if (installPrompt) installButton.hidden = false;

  const refreshStatsButton = document.getElementById("refreshStatsButton");
  if (refreshStatsButton) {
    refreshStatsButton.addEventListener("click", () => {
      refreshStatsButton.animate(
        [{transform:"rotate(0deg)"},{transform:"rotate(360deg)"}],
        {duration:420,easing:"ease-out"}
      );
      window.setTimeout(() => window.location.reload(), 250);
    });
  }

  const focusTimer = document.getElementById("focusTimer");
  const focusForm = document.getElementById("focusForm");
  const resetFocus = document.getElementById("resetFocus");
  if (focusTimer && focusForm) {
    let remaining = 25 * 60;
    let ticker = null;
    let sessionId = null;
    const durationSelect = focusForm.querySelector("[name='duration']");
    const taskSelect = focusForm.querySelector("[name='task_id']");
    const renderTime = () => {
      const minutes = Math.floor(remaining / 60);
      const seconds = remaining % 60;
      focusTimer.textContent = `${String(minutes).padStart(2,"0")}:${String(seconds).padStart(2,"0")}`;
      document.title = remaining > 0 ? `${String(minutes).padStart(2,"0")}:${String(seconds).padStart(2,"0")} - Personal OS` : "Session complete - Personal OS";
    };
    const savedFocus = (() => {
      try { return JSON.parse(sessionStorage.getItem("personal-os-focus") || "null"); } catch (_) { return null; }
    })();
    if (savedFocus && savedFocus.endsAt) {
      sessionId = savedFocus.sessionId;
      remaining = Math.max(0, Math.ceil((savedFocus.endsAt - Date.now()) / 1000));
      if (remaining === 0) sessionStorage.removeItem("personal-os-focus");
      if (remaining > 0) {
        ticker = window.setInterval(() => {
          remaining -= 1;
          renderTime();
          if (remaining <= 0) {
            window.clearInterval(ticker);
            ticker = null;
            sessionStorage.removeItem("personal-os-focus");
            if (sessionId) fetch(`${apiBasePath}/focus.php`,{method:"PATCH",headers:csrfHeaders(),body:JSON.stringify({id:sessionId,status:"completed"})}).catch(()=>{});
          }
        }, 1000);
      }
    }
    renderTime();
    durationSelect?.addEventListener("change", () => {
      if (!ticker) {
        remaining = Number(durationSelect.value) * 60;
        renderTime();
      }
    });
    resetFocus?.addEventListener("click", () => {
      window.clearInterval(ticker);
      ticker = null;
      if (sessionId) fetch(`${apiBasePath}/focus.php`,{method:"PATCH",headers:csrfHeaders(),body:JSON.stringify({id:sessionId,status:"cancelled"})}).catch(()=>{});
      sessionStorage.removeItem("personal-os-focus");
      remaining = Number(durationSelect?.value || 25) * 60;
      renderTime();
      document.title = "Personal OS Focus";
    });
    focusForm.addEventListener("submit", (event) => {
      event.preventDefault();
      if (ticker) return;
      remaining = Number(durationSelect?.value || 25) * 60;
      renderTime();
      fetch(`${apiBasePath}/focus.php`, {
        method:"POST",
        headers:csrfHeaders(),
        body:JSON.stringify({duration_minutes:Number(durationSelect?.value || 25),task_id:Number(taskSelect?.value || 0)})
      }).then(async (response) => {
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.error || "Could not start focus session");
        sessionId = payload.id;
        sessionStorage.setItem("personal-os-focus", JSON.stringify({sessionId, endsAt: Date.now() + remaining * 1000}));
        ticker = window.setInterval(() => {
          remaining -= 1;
          renderTime();
          if (remaining <= 0) {
            window.clearInterval(ticker);
            ticker = null;
            if (sessionId) fetch(`${apiBasePath}/focus.php`,{method:"PATCH",headers:csrfHeaders(),body:JSON.stringify({id:sessionId,status:"completed"})}).catch(()=>{});
            if ("Notification" in window && Notification.permission === "granted") {
              new Notification("Focus session complete",{body:"Take a short break."});
            }
          }
        },1000);
      }).catch((error) => showToast(error.message));
    });
  }

  const notificationButton = document.getElementById("notificationButton");
  if (notificationButton) {
    notificationButton.addEventListener("click", async () => {
      if (!("Notification" in window)) {
        showToast("Notifications are not supported by this browser");
        return;
      }
      const permission = await Notification.requestPermission();
      if (permission !== "granted") {
        showToast("Notification permission was not granted");
        return;
      }
      showToast("Browser reminders enabled");
      const checkReminders = async () => {
        try {
          const response = await fetch(`${apiBasePath}/reminders.php`);
          if (!response.ok) return;
          const payload = await response.json();
          const now = Date.now();
          (payload.reminders || []).forEach((reminder) => {
            const due = new Date(reminder.remind_at).getTime();
            if (!reminder.is_sent && due <= now) {
              new Notification("Personal OS reminder", { body: reminder.title });
              fetch(`${apiBasePath}/reminders.php`, {
                method: "PATCH",
                headers: csrfHeaders(),
                body: JSON.stringify({ id: reminder.id })
              }).catch(() => {});
            }
          });
        } catch (_) {
          return;
        }
      };
      checkReminders();
      window.setInterval(checkReminders, 60 * 1000);
    });
  }

  // =========================================================
  // NLP SMART TASK PARSER
  // =========================================================

  const smartInput = document.getElementById("smartInput");
  const parsePreview = document.getElementById("parsePreview");
  const taskCategory = document.getElementById("taskCategory");
  const taskPriority = document.getElementById("taskPriority");
  const taskDueAt = document.getElementById("taskDueAt");
  const taskRecurrence = document.getElementById("taskRecurrence");

  const WEEKDAYS = ["sunday","monday","tuesday","wednesday","thursday","friday","saturday"];
  const MONTH_NAMES = {
    jan:0,feb:1,mar:2,apr:3,may:4,jun:5,jul:6,aug:7,sep:8,oct:9,nov:10,dec:11,
    january:0,february:1,march:2,april:3,june:5,july:6,august:7,september:8,
    october:9,november:10,december:11
  };

  function parseNaturalLanguageTask(raw) {
    let text = raw;
    const result = { title: "", category: null, priority: null, dueAt: null, recurrence: null };

    // Extract #category
    const catMatch = text.match(/#([a-z0-9_-]+)/i);
    if (catMatch) {
      result.category = catMatch[1].charAt(0).toUpperCase() + catMatch[1].slice(1).toLowerCase();
      text = text.replace(catMatch[0], "").trim();
    }

    // Extract !priority or priority keywords
    const prioMap = { urgent: "urgent", high: "high", medium: "medium", med: "medium", low: "low" };
    const bangMatch = text.match(/!(urgent|high|medium|med|low)/i);
    if (bangMatch) {
      result.priority = prioMap[bangMatch[1].toLowerCase()] || "medium";
      text = text.replace(bangMatch[0], "").trim();
    } else {
      const wordPrio = text.match(/\b(urgent|critical|asap|high.priority|low.priority)\b/i);
      if (wordPrio) {
        const w = wordPrio[1].toLowerCase();
        result.priority = w === "urgent" || w === "critical" || w === "asap" ? "urgent"
          : w.startsWith("high") ? "high" : "low";
        text = text.replace(wordPrio[0], "").trim();
      }
    }

    // Extract recurrence
    const recurPatterns = [
      { re: /\b(every\s+day|daily)\b/i, val: "daily" },
      { re: /\b(every\s+week|weekly)\b/i, val: "weekly" },
      { re: /\b(every\s+month|monthly)\b/i, val: "monthly" }
    ];
    for (const { re, val } of recurPatterns) {
      if (re.test(text)) {
        result.recurrence = val;
        text = text.replace(re, "").trim();
        break;
      }
    }

    // Extract time (e.g., "5pm", "at 17:00", "9am")
    let timeH = 9, timeM = 0, hasTime = false;
    const timeMatch = text.match(/\bat\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm)?\b|\b(\d{1,2})(?::(\d{2}))?\s*(am|pm)\b/i);
    if (timeMatch) {
      const hStr = timeMatch[1] || timeMatch[4];
      const mStr = timeMatch[2] || timeMatch[5];
      const meridiem = (timeMatch[3] || timeMatch[6] || "").toLowerCase();
      let h = parseInt(hStr, 10);
      const m = mStr ? parseInt(mStr, 10) : 0;
      if (meridiem === "pm" && h !== 12) h += 12;
      if (meridiem === "am" && h === 12) h = 0;
      timeH = h; timeM = m; hasTime = true;
      text = text.replace(timeMatch[0], "").trim();
    }

    function toIso(d) {
      const yr = d.getFullYear();
      const mo = String(d.getMonth() + 1).padStart(2, "0");
      const dy = String(d.getDate()).padStart(2, "0");
      const hh = String(timeH).padStart(2, "0");
      const mm = String(timeM).padStart(2, "0");
      return `${yr}-${mo}-${dy}T${hh}:${mm}`;
    }

    const now = new Date();

    // Strip leading "by " before date phrases
    text = text.replace(/\bby\s+/gi, "");

    // "today"
    if (/\btoday\b/i.test(text)) {
      result.dueAt = toIso(now);
      text = text.replace(/\btoday\b/i, "").trim();
    }
    // "tomorrow"
    else if (/\btomorrow\b/i.test(text)) {
      const d = new Date(now); d.setDate(d.getDate() + 1);
      result.dueAt = toIso(d); text = text.replace(/\btomorrow\b/i, "").trim();
    }
    // "next week"
    else if (/\bnext\s+week\b/i.test(text)) {
      const d = new Date(now); d.setDate(d.getDate() + 7);
      result.dueAt = toIso(d); text = text.replace(/\bnext\s+week\b/i, "").trim();
    }
    // "next month"
    else if (/\bnext\s+month\b/i.test(text)) {
      const d = new Date(now); d.setMonth(d.getMonth() + 1);
      result.dueAt = toIso(d); text = text.replace(/\bnext\s+month\b/i, "").trim();
    }
    // Weekday names e.g. "Friday"
    else {
      const dayMatch = text.match(/\b(monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/i);
      if (dayMatch) {
        const targetDay = WEEKDAYS.indexOf(dayMatch[1].toLowerCase());
        const d = new Date(now);
        const curDay = d.getDay();
        let diff = targetDay - curDay;
        if (diff <= 0) diff += 7;
        d.setDate(d.getDate() + diff);
        result.dueAt = toIso(d);
        text = text.replace(dayMatch[0], "").trim();
      }
      // Month + day e.g. "Dec 5", "December 15"
      else {
        const monthDayMatch = text.match(/\b(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:tember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\s+(\d{1,2})\b/i);
        if (monthDayMatch) {
          const monthIdx = MONTH_NAMES[monthDayMatch[1].toLowerCase().slice(0, 3)];
          const day = parseInt(monthDayMatch[2], 10);
          const d = new Date(now.getFullYear(), monthIdx, day, timeH, timeM);
          if (d < now) d.setFullYear(d.getFullYear() + 1);
          result.dueAt = toIso(d);
          text = text.replace(monthDayMatch[0], "").trim();
        }
        // ISO or MM/DD
        else {
          const isoMatch = text.match(/\b(\d{4}-\d{2}-\d{2}|\d{1,2}\/\d{1,2}(?:\/\d{2,4})?)\b/);
          if (isoMatch) {
            const d = new Date(isoMatch[1].replace(/(\d+)\/(\d+)(?:\/(\d+))?/, (_, m, day, yr) => {
              const y = yr ? (yr.length === 2 ? "20" + yr : yr) : now.getFullYear();
              return `${y}-${String(m).padStart(2,"0")}-${String(day).padStart(2,"0")}`;
            }));
            if (!isNaN(d.getTime())) { result.dueAt = toIso(d); }
            text = text.replace(isoMatch[0], "").trim();
          }
        }
      }
    }

    // If a time was given but no date matched yet, assume today
    if (hasTime && !result.dueAt) {
      result.dueAt = toIso(now);
    }

    result.title = text.replace(/\s{2,}/g, " ").trim();
    return result;
  }

  function formatParsedPreview(parsed) {
    const parts = [];
    if (parsed.dueAt) {
      const d = new Date(parsed.dueAt.replace("T", " "));
      const label = new Intl.DateTimeFormat([], { weekday:"short", month:"short", day:"numeric", hour:"2-digit", minute:"2-digit" }).format(d);
      parts.push(`📅 <strong>${label}</strong>`);
    }
    if (parsed.priority) parts.push(`⚡ <strong>${parsed.priority}</strong>`);
    if (parsed.category) parts.push(`# <strong>${parsed.category}</strong>`);
    if (parsed.recurrence) parts.push(`🔁 <strong>${parsed.recurrence}</strong>`);
    return parts.join(" &nbsp;·&nbsp; ");
  }

  function applyParsedToForm(parsed) {
    if (parsed.title && taskTitle) taskTitle.value = parsed.title;
    if (parsed.category && taskCategory) {
      const opt = Array.from(taskCategory.options).find(
        (o) => o.value.toLowerCase() === parsed.category.toLowerCase() ||
               o.text.toLowerCase() === parsed.category.toLowerCase()
      );
      if (opt) taskCategory.value = opt.value;
    }
    if (parsed.priority && taskPriority) taskPriority.value = parsed.priority;
    if (parsed.dueAt && taskDueAt) taskDueAt.value = parsed.dueAt;
    if (parsed.recurrence && taskRecurrence) taskRecurrence.value = parsed.recurrence;
  }

  if (smartInput && parsePreview) {
    smartInput.addEventListener("input", () => {
      const raw = smartInput.value.trim();
      if (!raw) { parsePreview.innerHTML = ""; return; }
      const parsed = parseNaturalLanguageTask(raw);
      const preview = formatParsedPreview(parsed);
      parsePreview.innerHTML = preview || "";
      applyParsedToForm(parsed);
    });
    // Clear smart input when modal closes
    document.addEventListener("keydown", (evt) => {
      if (evt.key === "Escape" && smartInput) { smartInput.value = ""; parsePreview.innerHTML = ""; }
    });
    document.querySelectorAll(".modal-close").forEach((btn) => {
      btn.addEventListener("click", () => { if (smartInput) { smartInput.value = ""; parsePreview.innerHTML = ""; }});
    });
  }

  // =========================================================
  // KANBAN BOARD VIEW
  // =========================================================

  const listViewBtn = document.getElementById("listViewBtn");
  const boardViewBtn = document.getElementById("boardViewBtn");
  const kanbanBoard = document.getElementById("kanbanBoard");
  let draggedTaskId = null;

  const STATUS_COL_LABELS = { todo: "todo", in_progress: "in_progress", completed: "completed", archived: "archived" };

  function buildKanbanCard(row) {
    const title = row.querySelector("h3")?.textContent.trim() || "Untitled";
    const category = row.querySelector(".category")?.textContent.trim() || "";
    const priority = row.querySelector(".priority")?.textContent.trim() || "";
    const priorityClass = row.querySelector(".priority")?.className.replace("priority", "").trim() || "";
    const taskId = row.dataset.taskId || "";

    const card = document.createElement("article");
    card.className = `kanban-card${row.classList.contains("is-complete") ? " is-complete" : ""}`;
    card.setAttribute("draggable", "true");
    card.dataset.taskId = taskId;
    card.setAttribute("role", "listitem");
    card.setAttribute("aria-label", title);

    const h = document.createElement("p");
    h.className = "kanban-card-title";
    h.textContent = title;

    const meta = document.createElement("div");
    meta.className = "kanban-card-meta";

    if (category) {
      const cat = document.createElement("span");
      cat.className = "category";
      cat.textContent = category;
      meta.appendChild(cat);
    }
    if (priority) {
      const pri = document.createElement("span");
      pri.className = `priority ${priorityClass}`;
      pri.textContent = priority;
      meta.appendChild(pri);
    }

    card.appendChild(h);
    card.appendChild(meta);
    return card;
  }

  function updateKanbanColCounts() {
    ["todo","in_progress","completed","archived"].forEach((status) => {
      const col = document.getElementById(`col-${status}`);
      const count = document.getElementById(`kcount-${status}`);
      if (col && count) count.textContent = String(col.querySelectorAll(".kanban-card").length);
    });
  }

  function buildKanbanBoard() {
    if (!kanbanBoard) return;
    // Clear all columns
    ["todo","in_progress","completed","archived"].forEach((status) => {
      const col = document.getElementById(`col-${status}`);
      if (col) col.replaceChildren();
    });

    // Populate from task list rows
    const rows = Array.from(document.querySelectorAll("#taskList .task-row"));
    rows.forEach((row) => {
      const status = row.dataset.status || "todo";
      const colId = `col-${status}`;
      const col = document.getElementById(colId);
      if (!col) return;
      col.appendChild(buildKanbanCard(row));
    });

    updateKanbanColCounts();
    initKanbanDragDrop();
  }

  function updateKanbanCard(taskId, newStatus) {
    if (!kanbanBoard || kanbanBoard.hidden) return;
    const existing = kanbanBoard.querySelector(`.kanban-card[data-task-id="${taskId}"]`);
    if (!existing) return;
    const targetCol = document.getElementById(`col-${newStatus}`);
    if (!targetCol) return;
    existing.remove();
    // Re-build from the task row to get fresh data
    const row = document.querySelector(`#taskList .task-row[data-task-id="${taskId}"]`);
    if (row) targetCol.prepend(buildKanbanCard(row));
    updateKanbanColCounts();
    initKanbanDragDrop();
  }

  function initKanbanDragDrop() {
    if (!kanbanBoard) return;

    kanbanBoard.querySelectorAll(".kanban-card").forEach((card) => {
      card.addEventListener("dragstart", (evt) => {
        draggedTaskId = card.dataset.taskId;
        evt.dataTransfer.setData("text/plain", draggedTaskId);
        evt.dataTransfer.effectAllowed = "move";
        window.requestAnimationFrame(() => card.classList.add("is-dragging"));
      });
      card.addEventListener("dragend", () => {
        card.classList.remove("is-dragging");
        draggedTaskId = null;
      });
    });

    kanbanBoard.querySelectorAll(".kanban-col").forEach((col) => {
      const cards = col.querySelector(".kanban-cards");
      col.addEventListener("dragover", (evt) => {
        evt.preventDefault();
        evt.dataTransfer.dropEffect = "move";
        col.classList.add("drag-over");
      });
      col.addEventListener("dragleave", (evt) => {
        if (!col.contains(evt.relatedTarget)) col.classList.remove("drag-over");
      });
      col.addEventListener("drop", (evt) => {
        evt.preventDefault();
        col.classList.remove("drag-over");
        const id = evt.dataTransfer.getData("text/plain");
        if (!id) return;
        const newStatus = col.dataset.status;
        const card = kanbanBoard.querySelector(`.kanban-card[data-task-id="${id}"]`);
        if (card) {
          cards.appendChild(card);
          card.classList.remove("is-dragging");
        }
        updateKanbanColCounts();

        // Persist to API
        fetch(`${apiBasePath}/tasks.php`, {
          method: "PATCH",
          headers: csrfHeaders(),
          body: JSON.stringify({ id: Number(id), status: newStatus })
        }).then(async (response) => {
          const payload = await response.json();
          if (!response.ok) throw new Error(payload.error || "Could not update task status");
          // Sync the list view row
          const row = document.querySelector(`#taskList .task-row[data-task-id="${id}"]`);
          if (row) {
            row.dataset.status = newStatus;
            row.classList.toggle("is-complete", newStatus === "completed");
          }
          updateDashboardStats();
          showToast(`Moved to ${newStatus.replace("_", " ")}`);
        }).catch((error) => {
          showToast(error.message);
          buildKanbanBoard(); // Rebuild to reset position on error
        });
      });
    });
  }

  function switchToListView() {
    if (!kanbanBoard || !taskList) return;
    kanbanBoard.hidden = true;
    taskList.hidden = false;
    listViewBtn?.classList.add("active");
    listViewBtn?.setAttribute("aria-pressed", "true");
    boardViewBtn?.classList.remove("active");
    boardViewBtn?.setAttribute("aria-pressed", "false");
    localStorage.setItem("personal-os-task-view", "list");
  }

  function switchToBoardView() {
    if (!kanbanBoard || !taskList) return;
    taskList.hidden = true;
    kanbanBoard.hidden = false;
    boardViewBtn?.classList.add("active");
    boardViewBtn?.setAttribute("aria-pressed", "true");
    listViewBtn?.classList.remove("active");
    listViewBtn?.setAttribute("aria-pressed", "false");
    buildKanbanBoard();
    localStorage.setItem("personal-os-task-view", "board");
  }

  if (listViewBtn) listViewBtn.addEventListener("click", switchToListView);
  if (boardViewBtn) boardViewBtn.addEventListener("click", switchToBoardView);

  // Restore saved view preference
  if (localStorage.getItem("personal-os-task-view") === "board") {
    switchToBoardView();
  }

})();
