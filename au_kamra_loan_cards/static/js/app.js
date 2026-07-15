(() => {
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
  const TOKEN_KEY = "au_kamra_token";
  const USER_KEY = "au_kamra_user";

  const state = {
    token: localStorage.getItem(TOKEN_KEY) || "",
    user: JSON.parse(localStorage.getItem(USER_KEY) || "null"),
    currentId: null,
    currentView: "dashboard",
    heartbeatTimer: null,
  };

  const titles = {
    dashboard: ["Dashboard", "Loan cards, inventory, and live network activity."],
    search: ["Search Cards", "Filter by name, designation, department, dates, equipment, or extension."],
    inventory: ["Inventory", "Add, edit, delete, and allocate inventory items."],
    upload: ["Upload Files", "Import PDF or HTML loan cards."],
    create: ["New Loan Card", "Generate a loan card in the AU-Kamra format."],
    presence: ["Online Users", "See who is online and what they are doing on their panel."],
    users: ["Users & Roles", "Administrator assigns roles to each user."],
    records: ["Activity Log", "Uploads, edits, backups, logins, and imports."],
    backup: ["Backup / Import", "Protect and migrate your records."],
  };

  const roleLabels = {
    administrator: "Administrator",
    allocation_officer: "Allocation Officer",
    user: "User",
    viewer: "Viewer",
  };

  function toast(message, type = "success") {
    const el = $("#toast");
    el.hidden = false;
    el.className = `toast ${type}`;
    el.textContent = message;
    clearTimeout(toast._t);
    toast._t = setTimeout(() => { el.hidden = true; }, 3200);
  }

  function can(perm) {
    return !!(state.user && (state.user.permissions || []).includes(perm));
  }

  function applyPermissions() {
    $$("[data-perm]").forEach((el) => {
      el.hidden = !can(el.dataset.perm);
    });
  }

  async function api(url, options = {}) {
    const headers = Object.assign({}, options.headers || {});
    if (state.token) headers["Authorization"] = `Bearer ${state.token}`;
    if (options.json) {
      headers["Content-Type"] = "application/json";
      options.body = JSON.stringify(options.json);
      delete options.json;
    }
    const res = await fetch(url, { ...options, headers });
    const ct = res.headers.get("content-type") || "";
    const data = ct.includes("application/json") ? await res.json() : null;
    if (res.status === 401) {
      logout(true);
      throw new Error((data && data.error) || "Session expired");
    }
    if (!res.ok) throw new Error((data && data.error) || `Request failed (${res.status})`);
    return data;
  }

  function saveSession(token, user) {
    state.token = token;
    state.user = user;
    localStorage.setItem(TOKEN_KEY, token);
    localStorage.setItem(USER_KEY, JSON.stringify(user));
  }

  function clearSession() {
    state.token = "";
    state.user = null;
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
  }

  function showLogin() {
    $("#login-screen").hidden = false;
    $("#app-shell").hidden = true;
    if (state.heartbeatTimer) clearInterval(state.heartbeatTimer);
  }

  function showApp() {
    $("#login-screen").hidden = true;
    $("#app-shell").hidden = false;
    $("#user-chip").innerHTML = `<strong>${esc(state.user.full_name)}</strong><br>${esc(roleLabels[state.user.role] || state.user.role)}`;
    applyPermissions();
    startHeartbeat();
    showView("dashboard");
  }

  async function tryRestoreSession() {
    if (!state.token) {
      showLogin();
      return;
    }
    try {
      const data = await api("/api/auth/me");
      state.user = data.user;
      localStorage.setItem(USER_KEY, JSON.stringify(data.user));
      showApp();
    } catch {
      clearSession();
      showLogin();
    }
  }

  function logout(silent = false) {
    const token = state.token;
    clearSession();
    showLogin();
    if (token) {
      fetch("/api/auth/logout", {
        method: "POST",
        headers: { Authorization: `Bearer ${token}` },
      }).catch(() => {});
    }
    if (!silent) toast("Signed out");
  }

  function startHeartbeat() {
    if (state.heartbeatTimer) clearInterval(state.heartbeatTimer);
    const beat = () => {
      api("/api/auth/heartbeat", {
        method: "POST",
        json: {
          view: state.currentView,
          activity: `Viewing ${titles[state.currentView]?.[0] || state.currentView}`,
        },
      }).catch(() => {});
      if (can("presence_view")) refreshOnlinePill();
    };
    beat();
    state.heartbeatTimer = setInterval(beat, 20000);
  }

  async function refreshOnlinePill() {
    try {
      const data = await api("/api/presence");
      $("#online-pill").textContent = `${data.count} online`;
      $("#stat-online") && ($("#stat-online").textContent = data.count);
      if (state.currentView === "presence") renderPresence(data);
    } catch {
      /* ignore */
    }
  }

  function showView(name) {
    if (!can($$(`.nav-item[data-view="${name}"]`)[0]?.dataset.perm || "loan_view") && name !== "dashboard") {
      // still allow if nav item has no perm requirement matched
    }
    const nav = $(`.nav-item[data-view="${name}"]`);
    if (nav?.dataset.perm && !can(nav.dataset.perm)) {
      toast("Permission denied", "error");
      return;
    }
    state.currentView = name;
    $$(".view").forEach((v) => v.classList.remove("active"));
    $$(".nav-item").forEach((n) => n.classList.toggle("active", n.dataset.view === name));
    $(`#view-${name}`)?.classList.add("active");
    const [title, sub] = titles[name] || ["AU-Kamra", ""];
    $("#view-title").textContent = title;
    $("#view-subtitle").textContent = sub;
    const loaders = {
      dashboard: loadDashboard,
      search: runSearch,
      inventory: runInvSearch,
      records: loadActivity,
      backup: loadDataPath,
      presence: loadPresence,
      users: loadUsers,
    };
    loaders[name]?.().catch((e) => toast(e.message, "error"));
    api("/api/auth/heartbeat", {
      method: "POST",
      json: { view: name, activity: `Opened ${title}` },
    }).catch(() => {});
  }

  function esc(s) {
    return String(s ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;");
  }

  function cardActions(card) {
    return `<div class="row-actions" onclick="event.stopPropagation()">
      <button class="btn ghost" data-open="${card.id}">Open</button>
      <button class="btn primary" data-view-card="${card.id}">Details</button>
    </div>`;
  }

  async function loadDashboard() {
    const stats = await api("/api/stats");
    $("#stat-total").textContent = stats.total_cards;
    $("#stat-inv").textContent = stats.inventory_total;
    $("#stat-avail").textContent = stats.inventory_available;
    $("#stat-online").textContent = stats.users_online;
    $("#online-pill").textContent = `${stats.users_online} online`;

    const cards = await api("/api/search");
    const recent = (cards.results || []).slice(0, 6);
    $("#recent-tbody").innerHTML = recent.length
      ? recent.map((c) => `<tr data-id="${c.id}">
          <td><strong>${esc(c.name)}</strong></td>
          <td>${esc(c.department || "—")}</td>
          <td>${esc(c.tel_extension || "—")}</td>
          <td>${esc(c.issue_date || "—")}</td>
          <td>${esc(c.equipment_summary || "—")}</td>
          <td>${cardActions(c)}</td>
        </tr>`).join("")
      : `<tr><td colspan="6" class="empty">No loan cards yet.</td></tr>`;

    if (can("inventory_view")) {
      const inv = await api("/api/inventory");
      const rows = (inv.results || []).slice(0, 6);
      $("#dash-inv-tbody").innerHTML = rows.length
        ? rows.map((i) => `<tr>
            <td><strong>${esc(i.item_name)}</strong></td>
            <td>${esc(i.status)}</td>
            <td>${esc(i.allocation_officer || "—")}</td>
            <td>${esc(i.allocated_to || "—")}</td>
            <td>${esc(i.allocation_date || "—")}</td>
          </tr>`).join("")
        : `<tr><td colspan="5" class="empty">No inventory items yet.</td></tr>`;
    }
  }

  function filterParams(map) {
    const p = new URLSearchParams();
    Object.entries(map).forEach(([k, sel]) => {
      const v = $(sel)?.value?.trim();
      if (v) p.set(k, v);
    });
    return p;
  }

  async function runSearch() {
    const p = filterParams({
      q: "#f-q", name: "#f-name", designation: "#f-designation", department: "#f-department",
      tel_extension: "#f-tel", item_name: "#f-item", issue_date_from: "#f-from", issue_date_to: "#f-to",
    });
    const data = await api(`/api/search?${p}`);
    $("#result-count").textContent = `${data.count} result${data.count === 1 ? "" : "s"}`;
    $("#search-tbody").innerHTML = data.results.length
      ? data.results.map((c) => `<tr data-id="${c.id}">
          <td>${esc(c.card_number || "—")}</td>
          <td><strong>${esc(c.name)}</strong></td>
          <td>${esc(c.designation || "—")}</td>
          <td>${esc(c.department || "—")}</td>
          <td>${esc(c.tel_extension || "—")}</td>
          <td>${esc(c.issue_date || "—")}</td>
          <td>${esc(c.equipment_summary || "—")}</td>
          <td>${cardActions(c)}</td>
        </tr>`).join("")
      : `<tr><td colspan="8" class="empty">No matching loan cards.</td></tr>`;
  }

  async function runInvSearch() {
    const p = filterParams({
      q: "#inv-q", item_name: "#inv-name", allocation_officer: "#inv-officer",
      allocated_to: "#inv-to", status: "#inv-status",
      added_date_from: "#inv-added-from", added_date_to: "#inv-added-to",
      issue_date_from: "#inv-issue-from", issue_date_to: "#inv-issue-to",
      allocation_date_from: "#inv-alloc-from", allocation_date_to: "#inv-alloc-to",
    });
    const data = await api(`/api/inventory?${p}`);
    $("#inv-count").textContent = `${data.count} item${data.count === 1 ? "" : "s"}`;
    $("#inv-tbody").innerHTML = data.results.length
      ? data.results.map((i) => `<tr>
          <td><strong>${esc(i.item_name)}</strong></td>
          <td>${esc(i.serial_number || "—")}</td>
          <td>${esc(i.category || "—")}</td>
          <td>${esc(i.status)}</td>
          <td>${esc(i.quantity || "1")}</td>
          <td>${esc(i.allocation_officer || "—")}</td>
          <td>${esc(i.allocated_to || "—")}</td>
          <td>${esc(i.added_date || "—")}</td>
          <td>${esc(i.issue_date || "—")}</td>
          <td>${esc(i.allocation_date || "—")}</td>
          <td><div class="row-actions">
            ${can("inventory_manage") ? `<button class="btn ghost" data-inv-edit="${i.id}">Edit</button>` : ""}
            ${can("inventory_allocate") ? `<button class="btn primary" data-inv-alloc="${i.id}">Allocate</button>` : ""}
            ${can("inventory_manage") ? `<button class="btn danger" data-inv-del="${i.id}">Del</button>` : ""}
          </div></td>
        </tr>`).join("")
      : `<tr><td colspan="11" class="empty">No inventory items found.</td></tr>`;
  }

  async function openDetails(id) {
    const card = await api(`/api/cards/${id}`);
    state.currentId = id;
    $("#modal-cardno").textContent = card.card_number || "Loan Card";
    $("#modal-name").textContent = card.name || "—";
    $("#modal-meta").innerHTML = [
      ["Designation", card.designation], ["Department", card.department],
      ["Tel. Extension", card.tel_extension], ["Issue Date", card.issue_date],
      ["File Type", (card.file_type || "").toUpperCase()], ["Source", card.source],
      ["Original File", card.original_filename], ["Notes", card.notes],
    ].map(([k, v]) => `<div><span>${esc(k)}</span><strong>${esc(v || "—")}</strong></div>`).join("");
    const items = card.items || [];
    $("#modal-items").innerHTML = items.length
      ? items.map((it) => `<tr><td>${esc(it.item_name)}</td><td>${esc(it.serial_number || "—")}</td><td>${esc(it.quantity || "1")}</td><td>${esc(it.remarks || "—")}</td></tr>`).join("")
      : `<tr><td colspan="4" class="empty">No equipment items</td></tr>`;
    applyPermissions();
    $("#detail-modal").showModal();
  }

  function openFile(id) {
    window.open(`/api/cards/${id}/file?token=${encodeURIComponent(state.token)}`, "_blank");
  }

  function downloadFile(id) {
    window.open(`/api/cards/${id}/download?token=${encodeURIComponent(state.token)}`, "_blank");
  }

  async function loadActivity() {
    const rows = await api("/api/activity?limit=150");
    $("#activity-list").innerHTML = rows.length
      ? rows.map((r) => `<li>
          <span class="when">${esc(r.created_at)}</span>
          <span class="action">${esc(r.action)}</span>
          <span>${esc(r.username || "")} ${esc(r.details || "")} ${r.client_type ? "(" + esc(r.client_type) + ")" : ""}</span>
        </li>`).join("")
      : `<li class="empty">No activity yet.</li>`;
  }

  async function loadDataPath() {
    const info = await api("/api/data-path");
    $("#data-path").textContent = info.data_dir;
  }

  function renderPresence(data) {
    $("#presence-count").textContent = `${data.count} online`;
    $("#presence-tbody").innerHTML = data.users.length
      ? data.users.map((u) => `<tr>
          <td><strong>${esc(u.full_name)}</strong><br><small>${esc(u.username)}</small></td>
          <td>${esc(roleLabels[u.role] || u.role)}</td>
          <td>${esc(u.client_type)}</td>
          <td>${esc(u.client_ip || "—")}</td>
          <td>${esc(u.client_hostname || "—")}</td>
          <td>${esc(u.current_view || "—")}</td>
          <td>${esc(u.current_activity || "—")}</td>
          <td>${esc(u.last_seen)}</td>
        </tr>`).join("")
      : `<tr><td colspan="8" class="empty">No users online.</td></tr>`;
  }

  async function loadPresence() {
    renderPresence(await api("/api/presence"));
  }

  async function loadUsers() {
    const data = await api("/api/users");
    $("#users-tbody").innerHTML = data.users.map((u) => `<tr>
      <td>${esc(u.username)}</td>
      <td>${esc(u.full_name)}</td>
      <td>${esc(roleLabels[u.role] || u.role)}</td>
      <td>${u.is_active ? "Active" : "Disabled"}</td>
      <td>${esc(u.created_at)}</td>
      <td><div class="row-actions">
        <button class="btn ghost" data-user-edit="${u.id}">Edit</button>
        <button class="btn danger" data-user-del="${u.id}">Delete</button>
      </div></td>
    </tr>`).join("");
  }

  async function uploadFiles(fileList) {
    const files = [...fileList];
    if (!files.length) return;
    const status = $("#upload-status");
    status.hidden = false;
    status.innerHTML = `<p>Uploading ${files.length} file(s)…</p>`;
    const fd = new FormData();
    files.forEach((f) => fd.append("files", f));
    try {
      const data = await api("/api/upload", { method: "POST", body: fd });
      status.innerHTML = `<p><strong>${data.count}</strong> card(s) imported.</p>`;
      toast(`${data.count} loan card(s) uploaded`);
    } catch (e) {
      status.innerHTML = `<p class="empty">${esc(e.message)}</p>`;
      toast(e.message, "error");
    }
  }

  function addItemRow(item = {}) {
    const row = document.createElement("div");
    row.className = "item-row";
    row.innerHTML = `
      <input placeholder="Item / Equipment" value="${esc(item.item_name || "")}" data-k="item_name" />
      <input placeholder="Serial" value="${esc(item.serial_number || "")}" data-k="serial_number" />
      <input placeholder="Qty" value="${esc(item.quantity || "1")}" data-k="quantity" />
      <input placeholder="Remarks" value="${esc(item.remarks || "")}" data-k="remarks" />
      <button type="button">&times;</button>`;
    row.querySelector("button").addEventListener("click", () => row.remove());
    $("#items-list").appendChild(row);
  }

  function collectItems() {
    return $$("#items-list .item-row").map((row) => {
      const obj = {};
      $$("input", row).forEach((inp) => { obj[inp.dataset.k] = inp.value.trim(); });
      return obj;
    }).filter((it) => it.item_name);
  }

  function openInvModal(item = null) {
    const form = $("#inv-form");
    form.reset();
    form.id.value = item?.id || "";
    $("#inv-modal-title").textContent = item ? "Edit Inventory" : "Add Inventory";
    if (item) {
      ["item_name", "serial_number", "category", "status", "quantity", "allocation_officer",
        "allocated_to", "added_date", "issue_date", "allocation_date", "notes"].forEach((k) => {
        if (form[k]) form[k].value = item[k] || "";
      });
    } else {
      form.added_date.value = new Date().toISOString().slice(0, 10);
      form.status.value = "available";
      form.quantity.value = "1";
    }
    applyPermissions();
    $("#inv-modal").showModal();
  }

  function openUserModal(user = null) {
    const form = $("#user-form");
    form.reset();
    form.id.value = user?.id || "";
    $("#user-modal-title").textContent = user ? "Edit User" : "Add User";
    form.username.disabled = !!user;
    if (user) {
      form.username.value = user.username;
      form.full_name.value = user.full_name;
      form.role.value = user.role;
      form.is_active.value = String(user.is_active ? 1 : 0);
      form.password.required = false;
    } else {
      form.password.required = true;
      form.role.value = "user";
      form.is_active.value = "1";
    }
    $("#user-modal").showModal();
  }

  // Events
  $("#login-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    $("#login-error").hidden = true;
    try {
      const data = await fetch("/api/auth/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          username: fd.get("username"),
          password: fd.get("password"),
          client_type: "server",
          hostname: location.hostname,
        }),
      }).then(async (r) => {
        const j = await r.json();
        if (!r.ok) throw new Error(j.error || "Login failed");
        return j;
      });
      saveSession(data.token, data.user);
      showApp();
      toast(`Welcome, ${data.user.full_name}`);
    } catch (err) {
      $("#login-error").hidden = false;
      $("#login-error").textContent = err.message;
    }
  });

  $("#btn-logout").addEventListener("click", () => logout());
  $$(".nav-item").forEach((btn) => btn.addEventListener("click", () => showView(btn.dataset.view)));
  $$("[data-goto]").forEach((btn) => btn.addEventListener("click", () => showView(btn.dataset.goto)));
  $("#btn-refresh").addEventListener("click", () => {
    showView(state.currentView);
    toast("Refreshed");
  });
  $("#btn-quick-upload")?.addEventListener("click", () => {
    showView("upload");
    $("#file-input").click();
  });
  $("#btn-search").addEventListener("click", () => runSearch().catch((e) => toast(e.message, "error")));
  $("#btn-clear-filters").addEventListener("click", () => {
    ["#f-q", "#f-name", "#f-designation", "#f-department", "#f-tel", "#f-item", "#f-from", "#f-to"]
      .forEach((s) => { $(s).value = ""; });
    runSearch();
  });
  $("#btn-inv-search").addEventListener("click", () => runInvSearch().catch((e) => toast(e.message, "error")));
  $("#btn-inv-clear").addEventListener("click", () => {
    ["#inv-q", "#inv-name", "#inv-officer", "#inv-to", "#inv-status", "#inv-added-from", "#inv-added-to",
      "#inv-issue-from", "#inv-issue-to", "#inv-alloc-from", "#inv-alloc-to"]
      .forEach((s) => { $(s).value = ""; });
    runInvSearch();
  });
  $("#btn-inv-add")?.addEventListener("click", () => openInvModal());

  document.addEventListener("click", async (e) => {
    const openBtn = e.target.closest("[data-open]");
    const viewBtn = e.target.closest("[data-view-card]");
    const invEdit = e.target.closest("[data-inv-edit]");
    const invDel = e.target.closest("[data-inv-del]");
    const invAlloc = e.target.closest("[data-inv-alloc]");
    const userEdit = e.target.closest("[data-user-edit]");
    const userDel = e.target.closest("[data-user-del]");
    const tr = e.target.closest("#search-tbody tr[data-id], #recent-tbody tr[data-id]");

    if (openBtn) return openFile(openBtn.dataset.open);
    if (viewBtn) return openDetails(viewBtn.dataset.viewCard).catch((err) => toast(err.message, "error"));
    if (tr && !e.target.closest(".row-actions")) return openDetails(tr.dataset.id).catch((err) => toast(err.message, "error"));

    if (invEdit) {
      const item = await api(`/api/inventory/${invEdit.dataset.invEdit}`);
      openInvModal(item);
    }
    if (invAlloc) {
      const item = await api(`/api/inventory/${invAlloc.dataset.invAlloc}`);
      openInvModal(item);
      $("#inv-form").status.value = "allocated";
      if (!$("#inv-form").allocation_officer.value) {
        $("#inv-form").allocation_officer.value = state.user.full_name;
      }
      if (!$("#inv-form").allocation_date.value) {
        $("#inv-form").allocation_date.value = new Date().toISOString().slice(0, 10);
      }
    }
    if (invDel) {
      if (!confirm("Delete this inventory item?")) return;
      await api(`/api/inventory/${invDel.dataset.invDel}`, { method: "DELETE" });
      toast("Inventory deleted");
      runInvSearch();
    }
    if (userEdit) {
      const users = await api("/api/users");
      const user = users.users.find((u) => String(u.id) === userEdit.dataset.userEdit);
      if (user) openUserModal(user);
    }
    if (userDel) {
      if (!confirm("Delete this user?")) return;
      try {
        await api(`/api/users/${userDel.dataset.userDel}`, { method: "DELETE" });
        toast("User deleted");
        loadUsers();
      } catch (err) {
        toast(err.message, "error");
      }
    }
  });

  $("#modal-close").addEventListener("click", () => $("#detail-modal").close());
  $("#modal-open").addEventListener("click", () => state.currentId && openFile(state.currentId));
  $("#modal-download").addEventListener("click", () => state.currentId && downloadFile(state.currentId));
  $("#modal-delete").addEventListener("click", async () => {
    if (!state.currentId || !confirm("Delete this loan card?")) return;
    await api(`/api/cards/${state.currentId}`, { method: "DELETE" });
    $("#detail-modal").close();
    toast("Deleted");
    showView(state.currentView);
  });

  $("#inv-modal-close").addEventListener("click", () => $("#inv-modal").close());
  $("#inv-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const payload = Object.fromEntries(fd.entries());
    const id = payload.id;
    delete payload.id;
    try {
      if (id) await api(`/api/inventory/${id}`, { method: "PUT", json: payload });
      else await api("/api/inventory", { method: "POST", json: payload });
      $("#inv-modal").close();
      toast("Inventory saved");
      runInvSearch();
      if (state.currentView === "dashboard") loadDashboard();
    } catch (err) {
      toast(err.message, "error");
    }
  });
  $("#inv-allocate-btn")?.addEventListener("click", async () => {
    const form = $("#inv-form");
    const id = form.id.value;
    if (!id) return toast("Save the item first, then allocate", "error");
    try {
      await api(`/api/inventory/${id}/allocate`, {
        method: "POST",
        json: {
          allocation_officer: form.allocation_officer.value,
          allocated_to: form.allocated_to.value,
          allocation_date: form.allocation_date.value,
          issue_date: form.issue_date.value,
          status: "allocated",
        },
      });
      $("#inv-modal").close();
      toast("Item allocated");
      runInvSearch();
    } catch (err) {
      toast(err.message, "error");
    }
  });

  $("#user-modal-close").addEventListener("click", () => $("#user-modal").close());
  $("#btn-user-add").addEventListener("click", () => openUserModal());
  $("#user-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const payload = Object.fromEntries(fd.entries());
    const id = payload.id;
    delete payload.id;
    payload.is_active = payload.is_active === "1";
    try {
      if (id) {
        if (!payload.password) delete payload.password;
        await api(`/api/users/${id}`, { method: "PUT", json: payload });
      } else {
        await api("/api/users", { method: "POST", json: payload });
      }
      $("#user-modal").close();
      toast("User saved");
      loadUsers();
    } catch (err) {
      toast(err.message, "error");
    }
  });

  const dz = $("#dropzone");
  const fileInput = $("#file-input");
  $("#btn-pick-files").addEventListener("click", () => fileInput.click());
  fileInput.addEventListener("change", () => uploadFiles(fileInput.files));
  ["dragenter", "dragover"].forEach((ev) => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.add("dragover"); }));
  ["dragleave", "drop"].forEach((ev) => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.remove("dragover"); }));
  dz.addEventListener("drop", (e) => uploadFiles(e.dataTransfer.files));

  $("#btn-add-item").addEventListener("click", () => addItemRow());
  addItemRow();
  addItemRow();
  const issue = $("#create-issue-date");
  if (issue) issue.value = new Date().toISOString().slice(0, 10);
  $("#create-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const payload = Object.fromEntries(fd.entries());
    payload.items = collectItems();
    try {
      const data = await api("/api/cards", { method: "POST", json: payload });
      toast("Loan card generated");
      e.target.reset();
      $("#items-list").innerHTML = "";
      addItemRow();
      addItemRow();
      if (issue) issue.value = new Date().toISOString().slice(0, 10);
      if (data.id) openDetails(data.id);
    } catch (err) {
      toast(err.message, "error");
    }
  });

  $("#btn-backup").addEventListener("click", async () => {
    try {
      const data = await api("/api/backup", { method: "POST" });
      toast(`Backup created: ${data.filename}`);
    } catch (e) { toast(e.message, "error"); }
  });
  $("#btn-download-backup").addEventListener("click", (e) => {
    e.preventDefault();
    window.open(`/api/backup/download?token=${encodeURIComponent(state.token)}`, "_blank");
  });
  $("#btn-export-json").addEventListener("click", () => {
    window.open(`/api/export/json?token=${encodeURIComponent(state.token)}`, "_blank");
  });
  $("#btn-export-csv").addEventListener("click", () => {
    window.open(`/api/export/csv?token=${encodeURIComponent(state.token)}`, "_blank");
  });
  $("#btn-restore").addEventListener("click", () => $("#restore-input").click());
  $("#restore-input").addEventListener("change", async () => {
    const file = $("#restore-input").files[0];
    if (!file || !confirm("Restore will replace current data. Continue?")) return;
    const fd = new FormData();
    fd.append("file", file);
    try {
      await api("/api/restore", { method: "POST", body: fd });
      toast("Backup restored");
      showView("dashboard");
    } catch (e) { toast(e.message, "error"); }
  });
  $("#btn-import").addEventListener("click", () => $("#import-input").click());
  $("#import-input").addEventListener("change", async () => {
    const file = $("#import-input").files[0];
    if (!file) return;
    const fd = new FormData();
    fd.append("file", file);
    try {
      const data = await api("/api/import/json", { method: "POST", body: fd });
      const n = data.imported?.loan_cards ?? data.imported ?? 0;
      toast(`Imported ${n} loan card(s)`);
      showView("dashboard");
    } catch (e) { toast(e.message, "error"); }
  });

  tryRestoreSession();
})();
