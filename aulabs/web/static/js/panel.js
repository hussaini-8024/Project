const state = {
  user: window.AULABS_USER || {},
  view: "overview",
  catalog: [],
};

async function api(path, options = {}) {
  const opts = {
    headers: { "Content-Type": "application/json", ...(options.headers || {}) },
    credentials: "same-origin",
    ...options,
  };
  const res = await fetch(`/api${path}`, opts);
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    const detail = data.detail;
    const msg = typeof detail === "string" ? detail : detail?.[0]?.msg || `Request failed (${res.status})`;
    throw new Error(msg);
  }
  return data;
}

function fmtBytes(n) {
  if (n == null) return "—";
  const u = ["B", "KB", "MB", "GB", "TB"];
  let i = 0;
  let v = Number(n);
  while (v >= 1024 && i < u.length - 1) {
    v /= 1024;
    i += 1;
  }
  return `${v.toFixed(i === 0 ? 0 : 1)} ${u[i]}`;
}

function fmtUptime(sec) {
  const d = Math.floor(sec / 86400);
  const h = Math.floor((sec % 86400) / 3600);
  const m = Math.floor((sec % 3600) / 60);
  return `${d}d ${h}h ${m}m`;
}

function hasPerm(perm) {
  if (state.user.role === "admin") return true;
  return (state.user.permissions || []).includes(perm);
}

function setView(name) {
  state.view = name;
  document.querySelectorAll(".view").forEach((el) => el.classList.toggle("active", el.id === `view-${name}`));
  document.querySelectorAll(".nav-item").forEach((el) => el.classList.toggle("active", el.dataset.view === name));
  const titles = {
    overview: ["Overview", "Host status, users, and active environments"],
    users: ["Users", "Isolated accounts with private directories"],
    storage: ["Storage", "Quotas, usage, and per-user file trees"],
    sessions: ["Sessions", "Independent working environments on the same Linux host"],
    permissions: ["Permissions", "Fine-grained access for each panel user"],
    shell: ["Shell", "Run commands inside your isolated environment"],
    system: ["Master OS", "Linux host managed from the web panel"],
    agents: ["Agents", "Connected AU Labs Agent hosts reporting into the panel"],
    audit: ["Audit", "Panel activity history"],
  };
  const [title, sub] = titles[name] || ["Panel", ""];
  document.getElementById("view-title").textContent = title;
  document.getElementById("view-sub").textContent = sub;
  loadView(name);
}

async function loadView(name) {
  try {
    if (name === "overview") await loadOverview();
    if (name === "users") await loadUsers();
    if (name === "storage") await loadStorage();
    if (name === "sessions") await loadSessions();
    if (name === "permissions") await loadPermissions();
    if (name === "system") await loadSystem();
    if (name === "agents") await loadAgents();
    if (name === "audit") await loadAudit();
  } catch (err) {
    console.error(err);
  }
}

function metric(label, value, hint = "") {
  return `<div class="metric"><div class="label">${label}</div><div class="value">${value}</div>${hint ? `<div class="hint">${hint}</div>` : ""}</div>`;
}

async function loadOverview() {
  const [meStorage, sessions] = await Promise.all([
    api("/storage/me"),
    api("/sessions"),
  ]);
  let system = null;
  if (hasPerm("system.manage")) {
    try { system = await api("/system/overview"); } catch (_) {}
  }
  const metrics = document.getElementById("overview-metrics");
  const bits = [
    metric("You", state.user.username, state.user.role),
    metric("Your storage", `${meStorage.used_mb} / ${meStorage.quota_mb} MB`, `${meStorage.percent_used}% used`),
    metric("Your sessions", String((sessions.sessions || []).filter((s) => s.user_id === state.user.id && s.status !== "terminated").length)),
  ];
  if (system) {
    bits.push(metric("CPU", `${system.runtime.cpu_percent}%`, `${system.runtime.cpu_count} cores`));
    bits.push(metric("Memory", `${system.memory.percent}%`, fmtBytes(system.memory.used)));
    bits.push(metric("Uptime", fmtUptime(system.runtime.uptime_seconds), system.os.hostname));
  }
  metrics.innerHTML = bits.join("");

  const sessEl = document.getElementById("overview-sessions");
  const active = (sessions.sessions || []).filter((s) => s.status !== "terminated").slice(0, 8);
  sessEl.innerHTML = active.length
    ? active.map((s) => `
      <div class="list-item">
        <div>
          <div class="title">${s.username} · ${s.session_type}</div>
          <div class="meta mono">${s.working_dir}</div>
        </div>
        <span class="badge ${s.alive ? "ok" : "warn"}">${s.status}</span>
      </div>`).join("")
    : `<div class="list-item"><div class="meta">No active sessions yet.</div></div>`;

  const storEl = document.getElementById("overview-storage");
  if (hasPerm("storage.manage")) {
    const summary = await api("/storage/summary");
    storEl.innerHTML = (summary.users || []).slice(0, 8).map((u) => `
      <div class="list-item">
        <div>
          <div class="title">${u.username}</div>
          <div class="meta">${u.used_mb} / ${u.quota_mb} MB · ${u.home_dir}</div>
        </div>
        <span class="badge ${u.over_quota ? "danger" : "ok"}">${u.percent_used}%</span>
      </div>`).join("");
  } else {
    storEl.innerHTML = `
      <div class="list-item">
        <div>
          <div class="title">${meStorage.username}</div>
          <div class="meta">${meStorage.home_dir}</div>
        </div>
        <span class="badge">${meStorage.used_mb} MB</span>
      </div>`;
  }
}

async function loadUsers() {
  const data = await api("/users");
  const el = document.getElementById("users-table");
  el.innerHTML = `
    <div class="row head"><div>User</div><div>Home</div><div>Quota</div><div>Role</div><div></div></div>
    ${(data.users || []).map((u) => `
      <div class="row">
        <div>
          <div class="title">${u.display_name || u.username}</div>
          <div class="meta">${u.username}${u.enabled ? "" : " · disabled"}</div>
        </div>
        <div class="meta mono">${u.home_dir}</div>
        <div>${u.storage_quota_mb} MB</div>
        <div><span class="badge">${u.role}</span></div>
        <div>
          ${u.username === "admin" ? "" : `<button class="btn danger" data-del-user="${u.id}">Delete</button>`}
        </div>
      </div>`).join("")}
  `;
  el.querySelectorAll("[data-del-user]").forEach((btn) => {
    btn.addEventListener("click", async () => {
      if (!confirm("Delete this user and keep their files?")) return;
      await api(`/users/${btn.dataset.delUser}?purge_home=false`, { method: "DELETE" });
      await loadUsers();
    });
  });
}

async function loadStorage() {
  const panel = document.getElementById("storage-panel");
  if (hasPerm("storage.manage")) {
    const summary = await api("/storage/summary");
    panel.innerHTML = `
      <div class="panel-block">
        <h2>Panel storage root</h2>
        <p class="muted mono">${summary.panel.data_root} · ${summary.panel.used_mb} MB used</p>
      </div>
      ${(summary.users || []).map((u) => `
        <div class="list-item">
          <div>
            <div class="title">${u.username}</div>
            <div class="meta mono">${u.home_dir}</div>
          </div>
          <div>
            <span class="badge ${u.over_quota ? "danger" : ""}">${u.used_mb} / ${u.quota_mb} MB</span>
          </div>
        </div>`).join("")}
    `;
  } else {
    const me = await api("/storage/me");
    panel.innerHTML = `
      <div class="list-item">
        <div>
          <div class="title">${me.username}</div>
          <div class="meta mono">${me.home_dir}</div>
        </div>
        <span class="badge">${me.used_mb} / ${me.quota_mb} MB</span>
      </div>`;
  }
  await refreshFiles();
}

async function refreshFiles() {
  const path = document.getElementById("files-path").value.trim();
  const data = await api(`/storage/files?path=${encodeURIComponent(path)}`);
  const el = document.getElementById("files-list");
  el.innerHTML = (data.entries || []).length
    ? data.entries.map((f) => `
      <div class="list-item">
        <div>
          <div class="title">${f.is_dir ? "[dir]" : "[file]"} ${f.name}</div>
          <div class="meta mono">${f.path} · mode ${f.mode}</div>
        </div>
        <span class="badge">${f.is_dir ? "dir" : fmtBytes(f.size)}</span>
      </div>`).join("")
    : `<div class="list-item"><div class="meta">Empty directory</div></div>`;
}

async function loadSessions() {
  const data = await api("/sessions");
  const el = document.getElementById("sessions-list");
  el.innerHTML = (data.sessions || []).length
    ? data.sessions.map((s) => `
      <div class="list-item">
        <div>
          <div class="title">${s.username} · ${s.session_type} · <span class="mono">${s.id.slice(0, 8)}</span></div>
          <div class="meta mono">${s.working_dir}${s.pid ? ` · pid ${s.pid}` : ""}</div>
        </div>
        <div style="display:flex;gap:0.4rem;align-items:center">
          <span class="badge ${s.status === "active" && s.alive ? "ok" : "warn"}">${s.status}</span>
          ${s.status !== "terminated" ? `<button class="btn danger" data-kill="${s.id}">End</button>` : ""}
        </div>
      </div>`).join("")
    : `<div class="list-item"><div class="meta">No sessions. Create one to start an isolated environment.</div></div>`;
  el.querySelectorAll("[data-kill]").forEach((btn) => {
    btn.addEventListener("click", async () => {
      await api(`/sessions/${btn.dataset.kill}/terminate`, { method: "POST" });
      await loadSessions();
    });
  });
}

async function loadPermissions() {
  const users = hasPerm("users.manage") ? (await api("/users")).users : [state.user];
  const select = document.getElementById("perm-user-select");
  select.innerHTML = users.map((u) => `<option value="${u.id}">${u.username}</option>`).join("");
  if (!state.catalog.length) {
    state.catalog = (await api("/users/permissions/catalog")).permissions || [];
  }
  select.onchange = () => renderPermGrid(Number(select.value));
  await renderPermGrid(Number(select.value));
}

async function renderPermGrid(userId) {
  const summary = await api(`/users/${userId}/permissions`);
  const grid = document.getElementById("permissions-grid");
  grid.innerHTML = (summary.permissions || []).map((p) => `
    <div class="perm-item">
      <input type="checkbox" id="perm-${p.id}" data-perm="${p.id}" ${p.granted ? "checked" : ""} ${summary.role === "admin" && p.id.startsWith("") ? "" : ""} />
      <div>
        <label for="perm-${p.id}">${p.label}</label>
        <p>${p.description}</p>
      </div>
    </div>`).join("");
}

async function loadSystem() {
  const data = await api("/system/overview");
  document.getElementById("system-overview").innerHTML = [
    metric("Hostname", data.os.hostname, `${data.os.system} ${data.os.release}`),
    metric("CPU", `${data.runtime.cpu_percent}%`, `${data.runtime.cpu_count} cores · load ${data.runtime.load_avg.map((n) => n.toFixed(2)).join(", ")}`),
    metric("Memory", `${data.memory.percent}%`, `${fmtBytes(data.memory.used)} / ${fmtBytes(data.memory.total)}`),
    metric("Disk", `${data.disk.percent}%`, `${fmtBytes(data.disk.used)} / ${fmtBytes(data.disk.total)}`),
    metric("Processes", String(data.processes), `uptime ${fmtUptime(data.runtime.uptime_seconds)}`),
    metric("Panel", `v${data.panel.version}`, `${data.panel.host}:${data.panel.port}`),
  ].join("");
  document.getElementById("system-details").textContent = JSON.stringify(data, null, 2);
}

async function loadAgents() {
  const data = await api("/agents");
  const el = document.getElementById("agents-list");
  el.innerHTML = (data.agents || []).length
    ? data.agents.map((a) => `
      <div class="list-item">
        <div>
          <div class="title">${a.agent_name} · ${a.hostname || "—"}</div>
          <div class="meta mono">${a.agent_id.slice(0, 12)} · ${a.platform?.system || "?"} ${a.platform?.release || ""} · v${a.version || "?"}</div>
          <div class="meta">CPU ${a.metrics?.cpu_percent ?? "—"}% · RAM ${a.metrics?.memory?.percent ?? "—"}% · Disk ${a.metrics?.disk?.percent ?? "—"}%</div>
        </div>
        <div style="display:flex;gap:0.4rem;align-items:center">
          <span class="badge ok">${a.status}</span>
          <button class="btn" data-agent-session="${a.agent_id}">New session</button>
        </div>
      </div>`).join("")
    : `<div class="list-item"><div class="meta">No agents connected. Install AU Labs Agent and point it at this panel.</div></div>`;
  el.querySelectorAll("[data-agent-session]").forEach((btn) => {
    btn.addEventListener("click", async () => {
      await api(`/agents/${btn.dataset.agentSession}/command`, {
        method: "POST",
        body: JSON.stringify({ action: "create_session", label: "panel" }),
      });
      alert("Session create command queued for agent");
    });
  });
}

async function loadAudit() {
  const data = await api("/system/audit?limit=80");
  const el = document.getElementById("audit-list");
  el.innerHTML = (data.entries || []).map((e) => `
    <div class="list-item">
      <div>
        <div class="title">${e.action}</div>
        <div class="meta">${e.actor} → ${e.target || "—"} ${e.details ? `· ${e.details}` : ""}</div>
      </div>
      <span class="badge">${new Date(e.created_at).toLocaleString()}</span>
    </div>`).join("") || `<div class="list-item"><div class="meta">No audit entries</div></div>`;
}

function wireNav() {
  document.querySelectorAll(".nav-item").forEach((btn) => {
    const need = btn.dataset.need;
    if (need && !hasPerm(need)) {
      btn.style.display = "none";
      return;
    }
    btn.addEventListener("click", () => setView(btn.dataset.view));
  });
}

document.getElementById("logout-btn")?.addEventListener("click", async () => {
  await api("/logout", { method: "POST" });
  window.location.href = "/login";
});

document.getElementById("open-create-user")?.addEventListener("click", () => {
  document.getElementById("create-user-dialog").showModal();
});
document.getElementById("close-create-user")?.addEventListener("click", () => {
  document.getElementById("create-user-dialog").close();
});
document.getElementById("create-user-form")?.addEventListener("submit", async (e) => {
  e.preventDefault();
  const form = e.currentTarget;
  const err = document.getElementById("create-user-error");
  err.hidden = true;
  try {
    await api("/users", {
      method: "POST",
      body: JSON.stringify({
        username: form.username.value.trim(),
        display_name: form.display_name.value.trim(),
        password: form.password.value,
        storage_quota_mb: Number(form.storage_quota_mb.value || 1024),
      }),
    });
    form.reset();
    document.getElementById("create-user-dialog").close();
    await loadUsers();
  } catch (ex) {
    err.textContent = ex.message;
    err.hidden = false;
  }
});

document.getElementById("refresh-files")?.addEventListener("click", () => refreshFiles().catch(alert));
document.getElementById("mkdir-btn")?.addEventListener("click", async () => {
  const name = prompt("New folder name (relative to home)");
  if (!name) return;
  await api("/storage/mkdir", { method: "POST", body: JSON.stringify({ name }) });
  await refreshFiles();
});

document.getElementById("new-web-session")?.addEventListener("click", async () => {
  await api("/sessions", { method: "POST", body: JSON.stringify({ session_type: "web" }) });
  await loadSessions();
});
document.getElementById("new-shell-session")?.addEventListener("click", async () => {
  await api("/sessions", { method: "POST", body: JSON.stringify({ session_type: "shell" }) });
  await loadSessions();
});

document.getElementById("save-permissions")?.addEventListener("click", async () => {
  const userId = Number(document.getElementById("perm-user-select").value);
  const permissions = [...document.querySelectorAll("#permissions-grid input[data-perm]:checked")].map((el) => el.dataset.perm);
  await api(`/users/${userId}/permissions`, {
    method: "PUT",
    body: JSON.stringify({ permissions }),
  });
  alert("Permissions saved");
});

document.getElementById("refresh-agents")?.addEventListener("click", () => loadAgents().catch(alert));

document.getElementById("shell-form")?.addEventListener("submit", async (e) => {
  e.preventDefault();
  const input = document.getElementById("shell-input");
  const out = document.getElementById("shell-output");
  const cmd = input.value.trim();
  if (!cmd) return;
  out.textContent += `\n$ ${cmd}\n`;
  try {
    const result = await api("/sessions/run", {
      method: "POST",
      body: JSON.stringify({ command: cmd }),
    });
    if (result.stdout) out.textContent += result.stdout;
    if (result.stderr) out.textContent += result.stderr;
    out.textContent += `\n[exit ${result.returncode} · ${result.cwd}]\n`;
  } catch (ex) {
    out.textContent += `Error: ${ex.message}\n`;
  }
  out.scrollTop = out.scrollHeight;
  input.value = "";
});

function tickClock() {
  const el = document.getElementById("clock");
  if (el) el.textContent = new Date().toLocaleString();
}

wireNav();
tickClock();
setInterval(tickClock, 1000);
setView("overview");
setInterval(() => {
  if (state.view === "overview") loadOverview().catch(() => {});
}, 15000);
