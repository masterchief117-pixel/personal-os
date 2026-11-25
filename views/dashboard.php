<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#090b10">
  <title><?= htmlspecialchars($pageTitle) ?> - Dashboard</title>
  <link rel="manifest" href="/personal-os/manifest.webmanifest">
  <link rel="stylesheet" href="/personal-os/public/css/style.css">
</head>
<body>
  <a class="skip-link" href="#mainContent">Skip to main content</a>
  <input type="hidden" id="csrfToken" value="<?= htmlspecialchars(csrfToken()) ?>">
  <div class="ambient ambient-one"></div>
  <div class="ambient ambient-two"></div>

  <aside class="sidebar" id="sidebar" aria-label="Primary navigation">
    <a class="brand" href="/personal-os/" aria-label="Personal OS home">
      <span class="brand-mark">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 21 7v10l-9 5-9-5V7l9-5Zm0 4.2L6.8 9l5.2 2.9L17.2 9 12 6.2ZM6 11.2v4.2l5 2.8v-4.2l-5-2.8Zm12 0-5 2.8v4.2l5-2.8v-4.2Z"/></svg>
      </span>
      <span>Personal OS</span>
    </a>

    <nav class="nav-list">
      <a class="nav-item active" href="/personal-os/" aria-current="page">
        <svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10Zm0 8h8v-6H3v6Zm10 0h8V11h-8v10Zm0-18v6h8V3h-8Z"/></svg>
        <span>Overview</span>
      </a>
      <a class="nav-item" href="#tasks">
        <svg viewBox="0 0 24 24"><path d="M9 11 12 14l9-9M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        <span>Tasks</span><span class="nav-count" id="sidebarTaskCount"><?= (int) $stats['pending_tasks'] ?></span>
      </a>
      <a class="nav-item" href="#notes">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Zm0 2.5L17.5 8H14V4.5ZM8 13h8v2H8v-2Zm0 4h8v2H8v-2Z"/></svg>
        <span>Notes</span>
      </a>
      <a class="nav-item" href="#system">
        <svg viewBox="0 0 24 24"><path d="M4 4h16v16H4V4Zm4 4v8m4-12v12m4-8v8"/></svg>
        <span>System</span>
      </a>
      <a class="nav-item" href="/personal-os/calendar.php">
        <svg viewBox="0 0 24 24"><path d="M3 5h18v16H3V5Zm0 5h18M7 3v4m10-4v4"/></svg>
        <span>Calendar</span>
      </a>
      <a class="nav-item" href="/personal-os/settings.php">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-1.8 1.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-2.6V20a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L7.6 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 6.4 14h-.2v-2.6h.2a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1L9.4 6l.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.6v-.2H15v.2a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1 1.8 1.8-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2V14H21a1.7 1.7 0 0 0-1.6 1z"/></svg>
        <span>Settings</span>
      </a>
      <a class="nav-item" href="/personal-os/team.php">
        <svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2"/><path d="M3 21v-2a6 6 0 0 1 12 0v2m1-7a4 4 0 0 1 5 4v3"/></svg>
        <span>Team</span>
      </a>
      <a class="nav-item" href="/personal-os/habits.php">
        <svg viewBox="0 0 24 24"><path d="M4 12h4l2-6 4 12 2-6h4"/></svg>
        <span>Habits</span>
      </a>
      <a class="nav-item" href="/personal-os/workspaces.php">
        <svg viewBox="0 0 24 24"><path d="M3 6h18v12H3V6Zm4 3h10M7 15h6"/></svg>
        <span>Workspaces</span>
      </a>
      <a class="nav-item" href="/personal-os/planning.php">
        <svg viewBox="0 0 24 24"><path d="M4 20V10m5 10V4m5 16v-7m5 7V7"/></svg>
        <span>Planning</span>
      </a>
      <a class="nav-item" href="/personal-os/templates.php">
        <svg viewBox="0 0 24 24"><path d="M4 4h16v16H4V4Zm4 4h8M8 12h8M8 16h5"/></svg>
        <span>Templates</span>
      </a>
      <a class="nav-item" href="/personal-os/analytics.php">
        <svg viewBox="0 0 24 24"><path d="M4 20V10h4v10H4Zm6 0V4h4v16h-4Zm6 0v-7h4v7h-4Z"/></svg>
        <span>Analytics</span>
      </a>
      <a class="nav-item" href="/personal-os/vault.php">
        <svg viewBox="0 0 24 24"><rect x="4" y="6" width="16" height="14" rx="2"/><path d="M8 6V4h8v2m-6 7h4m-2-2v4"/></svg>
        <span>Vault</span>
      </a>
      <a class="nav-item" href="/personal-os/automations.php">
        <svg viewBox="0 0 24 24"><path d="m13 2-9 12h7l-1 8 10-13h-7V2Z"/></svg>
        <span>Automations</span>
      </a>
      <a class="nav-item" href="/personal-os/maintenance.php">
        <svg viewBox="0 0 24 24"><path d="M14.7 6.3a4 4 0 0 0-5-5l2.1 2.1-3.5 3.5-2.1-2.1a4 4 0 0 0 5 5L19 17.6 17.6 19l-7.8-7.8a4 4 0 0 0-5-5"/></svg>
        <span>Maintenance</span>
      </a>
      <a class="nav-item" href="/personal-os/goals.php">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="m12 12 7-7"/></svg>
        <span>Goals</span>
      </a>
      <a class="nav-item" href="/personal-os/journal.php">
        <svg viewBox="0 0 24 24"><path d="M5 3h14v18H5V3Zm3 5h8m-8 4h8m-8 4h5"/></svg>
        <span>Journal</span>
      </a>
      <a class="nav-item" href="/personal-os/focus.php">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l3 2M9 2h6"/></svg>
        <span>Focus</span>
      </a>
    </nav>

    <div class="sidebar-footer">
      <button class="quick-add" type="button" id="sidebarAddTask">
        <span>+</span> Quick add
      </button>
      <div class="profile">
        <div class="avatar"><?= htmlspecialchars(strtoupper(substr($user['name'], 0, 1))) ?></div>
        <div class="profile-copy">
          <strong><?= htmlspecialchars($user['name']) ?></strong>
          <span><?= htmlspecialchars($user['role']) ?></span>
        </div>
        <a class="icon-button" href="/personal-os/logout.php" aria-label="Sign out" title="Sign out">↪</a>
      </div>
    </div>
  </aside>

  <main class="main-shell" id="mainContent">
    <header class="topbar">
      <button class="menu-button" id="menuButton" type="button" aria-label="Toggle menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
      <div>
        <p class="eyebrow" id="greeting">Good morning</p>
        <h1>Command Center</h1>
      </div>
      <div class="topbar-actions">
        <button class="icon-button search-button" type="button" aria-label="Open command palette" aria-haspopup="dialog" aria-expanded="false" title="Search or jump (Ctrl+K)" id="globalSearchButton">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
        </button>
        <button class="icon-button notification-button" type="button" aria-label="Notifications" id="notificationButton">
          <svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4"/></svg>
          <span class="notification-dot"></span>
        </button>
        <button class="primary-button" type="button" id="addTaskButton">
          <span>+</span> New task
        </button>
      </div>
    </header>

    <?php if ($errorMessage): ?>
      <div class="alert" role="alert"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>

    <div class="dashboard-grid">
      <section class="glass-panel clock-panel" aria-labelledby="clock-heading">
        <div class="panel-heading">
          <div>
            <p class="eyebrow">Right now</p>
            <h2 id="clock-heading" class="date-label"><span id="dateLabel">Today</span></h2>
          </div>
          <span class="live-pill"><i></i> Live</span>
        </div>
        <div class="clock-wrap">
          <time class="live-clock" id="liveClock">00:00:00</time>
          <span class="clock-zone" id="clockZone">Local time</span>
        </div>
        <div class="focus-strip">
          <span class="focus-icon">◎</span>
          <div><span>Today’s focus</span><strong>Make progress on what matters</strong></div>
        </div>
      </section>

      <section class="glass-panel life-panel" aria-labelledby="life-heading">
        <div class="panel-heading">
          <div><p class="eyebrow">Daily direction</p><h2 id="life-heading">Goals and journal</h2></div>
          <span class="live-pill"><i></i> <?= (int) $journal['streak'] ?> day streak</span>
        </div>
        <div class="life-widget-grid">
          <a class="life-widget" href="/personal-os/goals.php">
            <span class="stat-icon violet">◎</span>
            <strong><?= count($goals) ?></strong>
            <small>Active goals</small>
          </a>
          <a class="life-widget" href="/personal-os/journal.php">
            <span class="stat-icon cyan">✎</span>
            <strong><?= $journal['today'] ? 'Saved' : 'Open' ?></strong>
            <small>Today’s journal</small>
          </a>
          <a class="life-widget" href="/personal-os/focus.php">
            <span class="stat-icon amber">◷</span>
            <strong><?= (int) $focusSummary['today'] ?></strong>
            <small>Focus sessions</small>
          </a>
        </div>
        <div class="weather-widget" id="weatherWidget" role="button" tabindex="0" title="Click to toggle °C / °F" aria-live="polite">
          <span class="weather-icon" id="weatherIcon" aria-hidden="true">🌤️</span>
          <div class="weather-copy">
            <strong id="weatherTemperature">--°</strong>
            <span id="weatherConditions">Loading weather…</span>
            <small id="weatherLocation">London</small>
          </div>
        </div>
      </section>

      <section class="glass-panel task-panel" id="tasks" aria-labelledby="tasks-heading">
        <div class="panel-heading">
          <div>
            <p class="eyebrow">Action list</p>
            <h2 id="tasks-heading">Tasks <span id="taskHeaderCount"><?= count($tasks) ?></span></h2>
          </div>
          <div class="task-panel-controls">
            <button class="text-button" type="button" id="taskFilterButton">All tasks <span>⌄</span></button>
            <div class="view-toggle" role="group" aria-label="Task view">
              <button class="view-btn active" id="listViewBtn" aria-pressed="true" title="List view" type="button">☰</button>
              <button class="view-btn" id="boardViewBtn" aria-pressed="false" title="Board view" type="button">⊞</button>
            </div>
          </div>
        </div>

        <div class="task-list" id="taskList">
          <?php if (empty($tasks)): ?>
            <div class="empty-state">
              <span>✓</span>
              <strong>No tasks yet</strong>
              <p>Add your first task to start the day.</p>
            </div>
          <?php else: ?>
            <?php foreach ($tasks as $task): ?>
              <article class="task-row <?= $task['status'] === 'completed' ? 'is-complete' : '' ?>" id="task-<?= (int) $task['id'] ?>" data-task-id="<?= (int) $task['id'] ?>" data-status="<?= htmlspecialchars($task['status']) ?>" data-category="<?= htmlspecialchars($task['category']) ?>" data-priority="<?= htmlspecialchars($task['priority']) ?>">
                <button class="task-check" type="button" aria-label="Toggle <?= htmlspecialchars($task['title']) ?>">
                  <svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg>
                </button>
                <div class="task-copy">
                  <h3><?= htmlspecialchars($task['title']) ?></h3>
                  <div class="task-meta">
                    <span class="category"><?= htmlspecialchars($task['category']) ?></span>
                    <?php if (!empty($task['due_at'])): ?>
                      <span class="due-time">◷ <?= date('M j, g:i A', strtotime($task['due_at'])) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
                <span class="priority priority-<?= htmlspecialchars($task['priority']) ?>"><?= htmlspecialchars($task['priority']) ?></span>
                <button class="task-menu" type="button" aria-label="Task options">•••</button>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>

      <section class="glass-panel notes-panel" id="notes" aria-labelledby="notes-heading">
        <div class="panel-heading">
          <div>
            <p class="eyebrow">Knowledge</p>
            <h2 id="notes-heading">Note canvas</h2>
          </div>
          <button class="icon-button small" type="button" aria-label="Add note" id="addNoteButton">+</button>
        </div>
        <div class="notes-grid">
          <?php if (empty($notes)): ?>
            <article class="note-card note-violet"><h3>Blank canvas</h3><p>Create a note and capture your thoughts.</p></article>
          <?php else: ?>
            <?php foreach ($notes as $note): ?>
              <article class="note-card note-<?= htmlspecialchars($note['color']) ?>" id="note-<?= (int) $note['id'] ?>" data-note-id="<?= (int) $note['id'] ?>">
                <?php if ($note['is_pinned']): ?><span class="pin" aria-label="Pinned">◆</span><?php endif; ?>
                <button class="note-delete" type="button" aria-label="Delete note" title="Delete note">×</button>
                <h3><?= htmlspecialchars($note['title']) ?></h3>
                <p><?= htmlspecialchars($note['content']) ?></p>
                <time datetime="<?= htmlspecialchars($note['updated_at']) ?>">Edited <?= date('M j', strtotime($note['updated_at'])) ?></time>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="kanban-board" id="kanbanBoard" hidden aria-label="Kanban board">
          <div class="kanban-col" data-status="todo">
            <div class="kanban-col-header">
              <span class="kanban-col-dot violet"></span>
              <h3>To Do</h3>
              <span class="kanban-col-count" id="kcount-todo">0</span>
            </div>
            <div class="kanban-cards" id="col-todo" role="list" aria-label="To Do tasks"></div>
          </div>
          <div class="kanban-col" data-status="in_progress">
            <div class="kanban-col-header">
              <span class="kanban-col-dot cyan"></span>
              <h3>In Progress</h3>
              <span class="kanban-col-count" id="kcount-in_progress">0</span>
            </div>
            <div class="kanban-cards" id="col-in_progress" role="list" aria-label="In Progress tasks"></div>
          </div>
          <div class="kanban-col" data-status="completed">
            <div class="kanban-col-header">
              <span class="kanban-col-dot green"></span>
              <h3>Done</h3>
              <span class="kanban-col-count" id="kcount-completed">0</span>
            </div>
            <div class="kanban-cards" id="col-completed" role="list" aria-label="Done tasks"></div>
          </div>
          <div class="kanban-col" data-status="archived">
            <div class="kanban-col-header">
              <span class="kanban-col-dot muted"></span>
              <h3>Archived</h3>
              <span class="kanban-col-count" id="kcount-archived">0</span>
            </div>
            <div class="kanban-cards" id="col-archived" role="list" aria-label="Archived tasks"></div>
          </div>
        </div>
      </section>

      <section class="glass-panel stats-panel" id="system" aria-labelledby="stats-heading">
        <div class="panel-heading">
          <div>
            <p class="eyebrow">Progress</p>
            <h2 id="stats-heading">System stats</h2>
          </div>
          <button class="icon-button small" type="button" aria-label="Refresh stats" id="refreshStatsButton">↻</button>
        </div>
        <div class="completion-ring" id="completionRing" style="--progress: <?= (int) $stats['completion_rate'] ?>">
          <div class="ring-inner">
            <strong id="completionRateText"><?= (int) $stats['completion_rate'] ?>%</strong>
            <span>Complete</span>
          </div>
        </div>
        <div class="stat-grid">
          <div><span class="stat-icon violet">✓</span><strong id="statCompletedTasks"><?= (int) $stats['completed_tasks'] ?></strong><small>Completed</small></div>
          <div><span class="stat-icon cyan">◷</span><strong id="statActiveTasks"><?= (int) $stats['active_tasks'] ?></strong><small>In progress</small></div>
          <div><span class="stat-icon amber">▤</span><strong id="statTotalNotes"><?= (int) $stats['total_notes'] ?></strong><small>Notes</small></div>
        </div>
        <div class="weekly-chart" id="weeklyChart" aria-label="Seven-day task activity">
          <?php foreach ($weeklyActivity as $day): ?>
            <div class="chart-column" title="<?= (int) $day['completed'] ?> completed">
              <span class="chart-bar" style="--bar-height: <?= min(100, 15 + ((int) $day['completed'] * 22)) ?>%"></span>
              <small><?= htmlspecialchars($day['label']) ?></small>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="system-vitals" aria-live="polite">
          <div class="vitals-heading"><strong>Disk usage</strong><span id="diskUsageText">Loading disk metrics…</span></div>
          <div class="bar-track" id="diskUsageProgress" role="progressbar" aria-label="Disk usage" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
            <i id="diskUsageBar" style="width: 0%;"></i>
          </div>
          <small id="serverInfoText">Loading server information…</small>
        </div>
      </section>

      <section class="glass-panel activity-panel" aria-labelledby="activity-heading">
        <div class="panel-heading">
          <div>
            <p class="eyebrow">Last 24 hours</p>
            <h2 id="activity-heading">Recent activity</h2>
          </div>
          <span class="health-status"><i></i> Healthy</span>
        </div>
        <div class="activity-list">
          <?php foreach ($logs as $log): ?>
            <div class="activity-row">
              <span class="activity-dot <?= htmlspecialchars($log['level']) ?>"></span>
              <div><strong><?= htmlspecialchars($log['message']) ?></strong><small><?= htmlspecialchars($log['event_type']) ?></small></div>
              <time><?= date('g:i A', strtotime($log['created_at'])) ?></time>
            </div>
          <?php endforeach; ?>
          <?php if (empty($logs)): ?>
            <div class="empty-state compact"><strong>No recent activity</strong></div>
          <?php endif; ?>
        </div>

        <div class="kanban-board" id="kanbanBoard" hidden aria-label="Kanban board">
          <div class="kanban-col" data-status="todo">
            <div class="kanban-col-header">
              <span class="kanban-col-dot violet"></span>
              <h3>To Do</h3>
              <span class="kanban-col-count" id="kcount-todo">0</span>
            </div>
            <div class="kanban-cards" id="col-todo" role="list" aria-label="To Do tasks"></div>
          </div>
          <div class="kanban-col" data-status="in_progress">
            <div class="kanban-col-header">
              <span class="kanban-col-dot cyan"></span>
              <h3>In Progress</h3>
              <span class="kanban-col-count" id="kcount-in_progress">0</span>
            </div>
            <div class="kanban-cards" id="col-in_progress" role="list" aria-label="In Progress tasks"></div>
          </div>
          <div class="kanban-col" data-status="completed">
            <div class="kanban-col-header">
              <span class="kanban-col-dot green"></span>
              <h3>Done</h3>
              <span class="kanban-col-count" id="kcount-completed">0</span>
            </div>
            <div class="kanban-cards" id="col-completed" role="list" aria-label="Done tasks"></div>
          </div>
          <div class="kanban-col" data-status="archived">
            <div class="kanban-col-header">
              <span class="kanban-col-dot muted"></span>
              <h3>Archived</h3>
              <span class="kanban-col-count" id="kcount-archived">0</span>
            </div>
            <div class="kanban-cards" id="col-archived" role="list" aria-label="Archived tasks"></div>
          </div>
        </div>
      </section>
    </div>
  </main>

  <div class="search-overlay" id="searchOverlay" aria-hidden="true">
    <section class="command-palette" role="dialog" aria-modal="true" aria-label="Command palette">
      <div class="search-box">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
        <input type="search" id="globalSearch" role="combobox" aria-label="Search pages, tasks, notes, goals, habits, and journal entries" aria-autocomplete="list" aria-controls="commandResults" aria-expanded="false" placeholder="Search your workspace or type a command..." autocomplete="off">
        <button type="button" id="closeSearch" aria-label="Close command palette">Esc</button>
      </div>
      <div class="command-results" id="commandResults" role="listbox" aria-label="Command results"></div>
      <p class="command-empty" id="commandEmpty" role="status" hidden>No matches. Try a page name, task, note, or action.</p>
      <div class="command-footer">
        <span id="commandStatus" role="status" aria-live="polite">Search or jump anywhere</span>
        <span class="command-keys"><kbd>↑</kbd><kbd>↓</kbd> navigate <kbd>↵</kbd> open <kbd>Esc</kbd> close</span>
      </div>
    </section>
  </div>

  <div class="modal-backdrop" id="noteModal" aria-hidden="true">
    <section class="task-modal" role="dialog" aria-modal="true" aria-labelledby="noteModalTitle">
      <div class="panel-heading">
        <div><p class="eyebrow">Capture thought</p><h2 id="noteModalTitle">Create new note</h2></div>
        <button class="icon-button small note-modal-close" type="button" aria-label="Close">×</button>
      </div>
      <form id="noteForm">
        <label>Note title<input type="text" name="title" id="noteTitle" placeholder="Title" required></label>
        <label>Content<textarea name="content" id="noteContent" rows="5" placeholder="Write your thought..."></textarea></label>
        <label>Color
          <select name="color">
            <option value="violet">Violet</option>
            <option value="cyan">Cyan</option>
            <option value="amber">Amber</option>
            <option value="rose">Rose</option>
          </select>
        </label>
        <label class="pin-option"><input type="checkbox" name="is_pinned" value="1"> Pin this note</label>
        <div class="modal-actions">
          <button class="secondary-button note-modal-close" type="button">Cancel</button>
          <button class="primary-button" type="submit">Save note</button>
        </div>
      </form>
    </section>
  </div>

  <div class="modal-backdrop" id="taskModal" aria-hidden="true">
    <section class="task-modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
      <div class="panel-heading">
        <div><p class="eyebrow">Capture action</p><h2 id="modalTitle">Create new task</h2></div>
        <button class="icon-button small modal-close" type="button" aria-label="Close">×</button>
      </div>
      <form id="taskForm">
        <div class="smart-input-wrap">
          <label class="smart-label">⚡ Smart input <span class="smart-badge">NLP</span>
            <input type="text" id="smartInput" placeholder='e.g. "File taxes by Friday 5pm #finance !urgent"' autocomplete="off" spellcheck="false">
          </label>
          <p class="parse-preview" id="parsePreview" aria-live="polite"></p>
        </div>
        <label>Task name<input type="text" name="title" id="taskTitle" placeholder="What needs to be done?" required></label>
        <label>Category<select name="category" id="taskCategory"><option>Personal</option><option>Work</option><option>Design</option><option>Health</option><option>Finance</option><option>Strategy</option></select></label>
        <label>Priority<select name="priority" id="taskPriority"><option value="medium">Medium</option><option value="low">Low</option><option value="high">High</option><option value="urgent">Urgent</option></select></label>
        <label>Due date<input type="datetime-local" name="due_at" id="taskDueAt"></label>
        <label>Repeat<select name="recurrence" id="taskRecurrence"><option value="none">Does not repeat</option><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select></label>
        <div class="modal-actions">
          <button class="secondary-button modal-close" type="button">Cancel</button>
          <button class="primary-button" type="submit">Add task</button>
        </div>
      </form>
    </section>
  </div>

  <div class="toast" id="toast" role="status" aria-live="polite">Task added</div>
  <script src="/personal-os/public/js/app.js"></script>
</body>
</html>
