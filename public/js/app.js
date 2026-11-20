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

  async function fetchWeather() {
    if (!weatherTemperature || !weatherConditions || !weatherIcon) return;
    try {
      const response = await fetch("/personal-os/api/weather.php", {
        headers: { Accept: "application/json" },
        cache: "no-store"
      });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.error || "Weather unavailable");

      const icons = {
        Clear: "☀️",
        "Partly cloudy": "🌤️",
        Overcast: "☁️",
        Fog: "🌫️",
        Drizzle: "🌦️",
        Rain: "🌧️",
        Snow: "❄️",
        Thunderstorm: "⛈️",
        Unknown: "🌡️"
      };
      weatherTemperature.textContent = `${Number(payload.temperature_c).toFixed(1)}°C`;
      weatherConditions.textContent = `${payload.conditions} · ${Number(payload.wind_speed_kmh).toFixed(0)} km/h wind`;
      weatherIcon.textContent = icons[payload.conditions] || icons.Unknown;
      if (weatherLocation && payload.location) weatherLocation.textContent = payload.location.replace(/_/g, " ");
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
    if (!diskUsageProgress || !diskUsageBar || !diskUsageText || !serverInfoText) return;
    try {
      const response = await fetch("/personal-os/api/sysinfo.php", {
        headers: { Accept: "application/json" },
        cache: "no-store"
      });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.error || "System metrics unavailable");

      const freeGb = Number(payload.disk_free_bytes) / (1024 ** 3);
      const totalGb = Number(payload.disk_total_bytes) / (1024 ** 3);
      const usedPercent = Math.max(0, Math.min(100, Number(payload.disk_used_percent)));
      diskUsageBar.style.width = `${usedPercent}%`;
      diskUsageProgress.setAttribute("aria-valuenow", usedPercent.toFixed(1));
      diskUsageProgress.setAttribute("aria-valuetext", `${usedPercent.toFixed(1)}% used`);
      diskUsageText.textContent = `${freeGb.toFixed(1)} GB free / ${totalGb.toFixed(1)} GB total`;
      serverInfoText.textContent = `PHP ${payload.php_version} · ${payload.server_os}`;
    } catch (error) {
      diskUsageText.textContent = error.message || "System metrics unavailable";
      serverInfoText.textContent = "Server information unavailable";
    }
  }

  if (weatherTemperature) {
    fetchWeather();
    window.setInterval(fetchWeather, 30 * 60 * 1000);
  }
  if (diskUsageProgress) {
    fetchSystemVitals();
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

      fetch("/personal-os/api/tasks.php", {
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
          if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = "Add task";
          }
        })
        .catch((error) => {
          row.remove();
          showToast(error.message);
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
      if (row.dataset.taskId) {
        fetch("/personal-os/api/tasks.php", {
          method: "PATCH",
          headers: csrfHeaders(),
          body: JSON.stringify({ id: Number(row.dataset.taskId), status: nextStatus })
        }).then(async (response) => {
          const payload = await response.json();
          if (!response.ok) throw new Error(payload.error || "Could not save task status");
        }).catch((error) => {
          row.classList.toggle("is-complete", wasComplete);
          row.dataset.status = previousStatus;
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
        fetch("/personal-os/api/tasks.php", {
          method: "DELETE",
          headers: csrfHeaders(),
          body: JSON.stringify({ id: Number(row.dataset.taskId) })
        }).then((response) => {
          if (!response.ok) throw new Error("Could not delete task");
          row.remove();
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
      const payload = {
        title: String(data.get("title") || "").trim(),
        content: String(data.get("content") || "").trim(),
        color: String(data.get("color") || "violet"),
        is_pinned: data.has("is_pinned")
      };
      fetch("/personal-os/api/notes.php", {
        method: "POST",
        headers: csrfHeaders(),
        body: JSON.stringify(payload)
      })
        .then(async (response) => {
          const result = await response.json();
          if (!response.ok) throw new Error(result.error || "Could not add note");
          noteForm.reset();
          closeNoteModal();
          window.location.reload();
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
        const response = await fetch("/personal-os/api/search.php", {
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
      fetch("/personal-os/api/tasks.php", {
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
            sessionStorage.removeItem("personal-os-focus");
            if (sessionId) fetch("/personal-os/api/focus.php",{method:"PATCH",headers:csrfHeaders(),body:JSON.stringify({id:sessionId,status:"completed"})}).catch(()=>{});
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
      if (sessionId) fetch("/personal-os/api/focus.php",{method:"PATCH",headers:csrfHeaders(),body:JSON.stringify({id:sessionId,status:"cancelled"})}).catch(()=>{});
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
      fetch("/personal-os/api/focus.php", {
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
            if (sessionId) fetch("/personal-os/api/focus.php",{method:"PATCH",headers:csrfHeaders(),body:JSON.stringify({id:sessionId,status:"completed"})}).catch(()=>{});
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
          const response = await fetch("/personal-os/api/reminders.php");
          if (!response.ok) return;
          const payload = await response.json();
          const now = Date.now();
          (payload.reminders || []).forEach((reminder) => {
            const due = new Date(reminder.remind_at).getTime();
            if (!reminder.is_sent && due <= now) {
              new Notification("Personal OS reminder", { body: reminder.title });
              fetch("/personal-os/api/reminders.php", {
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
})();
