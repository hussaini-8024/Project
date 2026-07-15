(() => {
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

  const state = {
    currentId: null,
  };

  const titles = {
    dashboard: ["Dashboard", "Overview of loan cards and recent activity."],
    search: ["Search & Filter", "Find cards by name, designation, department, issue date, equipment, or extension."],
    upload: ["Upload Files", "Import PDF or HTML loan cards. Data is indexed for fast search."],
    create: ["New Loan Card", "Generate a loan card in the same AU-Kamra format."],
    records: ["Activity Log", "A record of uploads, edits, backups, and imports."],
    backup: ["Backup / Import", "Protect your data with backup, restore, import, and export."],
  };

  function toast(message, type = "success") {
    const el = $("#toast");
    el.hidden = false;
    el.className = `toast ${type}`;
    el.textContent = message;
    clearTimeout(toast._t);
    toast._t = setTimeout(() => { el.hidden = true; }, 3200);
  }

  async function api(url, options = {}) {
    const res = await fetch(url, options);
    const ct = res.headers.get("content-type") || "";
    const data = ct.includes("application/json") ? await res.json() : null;
    if (!res.ok) {
      throw new Error((data && data.error) || `Request failed (${res.status})`);
    }
    return data;
  }

  function showView(name) {
    $$(".view").forEach((v) => v.classList.remove("active"));
    $$(".nav-item").forEach((n) => n.classList.toggle("active", n.dataset.view === name));
    const view = $(`#view-${name}`);
    if (view) view.classList.add("active");
    const [title, sub] = titles[name] || ["AU-Kamra", ""];
    $("#view-title").textContent = title;
    $("#view-subtitle").textContent = sub;
    if (name === "dashboard") loadDashboard();
    if (name === "search") runSearch();
    if (name === "records") loadActivity();
    if (name === "backup") loadDataPath();
  }

  function esc(s) {
    return String(s ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;");
  }

  function rowHtml(card, compact = false) {
    const equip = card.equipment_summary || "—";
    const actions = `
      <div class="row-actions" onclick="event.stopPropagation()">
        <button class="btn ghost" data-open="${card.id}">Open</button>
        <button class="btn primary" data-view-card="${card.id}">Details</button>
      </div>`;
    if (compact) {
      return `<tr data-id="${card.id}">
        <td><strong>${esc(card.name)}</strong></td>
        <td>${esc(card.designation || "—")}</td>
        <td>${esc(card.department || "—")}</td>
        <td>${esc(card.tel_extension || "—")}</td>
        <td>${esc(card.issue_date || "—")}</td>
        <td>${esc(equip)}</td>
        <td>${actions}</td>
      </tr>`;
    }
    return `<tr data-id="${card.id}">
      <td>${esc(card.card_number || "—")}</td>
      <td><strong>${esc(card.name)}</strong></td>
      <td>${esc(card.designation || "—")}</td>
      <td>${esc(card.department || "—")}</td>
      <td>${esc(card.tel_extension || "—")}</td>
      <td>${esc(card.issue_date || "—")}</td>
      <td>${esc(equip)}</td>
      <td>${actions}</td>
    </tr>`;
  }

  async function loadDashboard() {
    const stats = await api("/api/stats");
    $("#stat-total").textContent = stats.total_cards;
    $("#stat-depts").textContent = stats.departments;
    $("#stat-items").textContent = stats.equipment_items;
    $("#stat-week").textContent = stats.added_this_week;
    const data = await api("/api/search");
    const recent = (data.results || []).slice(0, 8);
    const tbody = $("#recent-tbody");
    tbody.innerHTML = recent.length
      ? recent.map((c) => rowHtml(c, true)).join("")
      : `<tr><td colspan="7" class="empty">No loan cards yet. Upload PDF/HTML files to get started.</td></tr>`;
  }

  function filterParams() {
    const p = new URLSearchParams();
    const map = {
      q: "#f-q",
      name: "#f-name",
      designation: "#f-designation",
      department: "#f-department",
      tel_extension: "#f-tel",
      item_name: "#f-item",
      issue_date_from: "#f-from",
      issue_date_to: "#f-to",
    };
    Object.entries(map).forEach(([k, sel]) => {
      const v = $(sel)?.value?.trim();
      if (v) p.set(k, v);
    });
    return p;
  }

  async function runSearch() {
    const p = filterParams();
    const data = await api(`/api/search?${p.toString()}`);
    $("#result-count").textContent = `${data.count} result${data.count === 1 ? "" : "s"}`;
    const tbody = $("#search-tbody");
    tbody.innerHTML = data.results.length
      ? data.results.map((c) => rowHtml(c)).join("")
      : `<tr><td colspan="8" class="empty">No matching loan cards.</td></tr>`;
  }

  async function openDetails(id) {
    const card = await api(`/api/cards/${id}`);
    state.currentId = id;
    $("#modal-cardno").textContent = card.card_number || "Loan Card";
    $("#modal-name").textContent = card.name || "—";
    $("#modal-meta").innerHTML = [
      ["Designation", card.designation],
      ["Department", card.department],
      ["Tel. Extension", card.tel_extension],
      ["Issue Date", card.issue_date],
      ["File Type", (card.file_type || "").toUpperCase()],
      ["Source", card.source],
      ["Original File", card.original_filename],
      ["Notes", card.notes],
    ].map(([k, v]) => `<div><span>${esc(k)}</span><strong>${esc(v || "—")}</strong></div>`).join("");

    const items = card.items || [];
    $("#modal-items").innerHTML = items.length
      ? items.map((it) => `<tr>
          <td>${esc(it.item_name)}</td>
          <td>${esc(it.serial_number || "—")}</td>
          <td>${esc(it.quantity || "1")}</td>
          <td>${esc(it.remarks || "—")}</td>
        </tr>`).join("")
      : `<tr><td colspan="4" class="empty">No equipment items</td></tr>`;

    $("#modal-download").href = `/api/cards/${id}/download`;
    $("#detail-modal").showModal();
  }

  function openFile(id) {
    window.open(`/api/cards/${id}/file`, "_blank");
  }

  async function loadActivity() {
    const rows = await api("/api/activity?limit=150");
    $("#activity-list").innerHTML = rows.length
      ? rows.map((r) => `<li>
          <span class="when">${esc(r.created_at)}</span>
          <span class="action">${esc(r.action)}</span>
          <span>${esc(r.details || "")}</span>
        </li>`).join("")
      : `<li class="empty">No activity yet.</li>`;
  }

  async function loadDataPath() {
    const info = await api("/api/data-path");
    $("#data-path").textContent = info.data_dir;
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
      const ok = data.created?.length || 0;
      const err = data.errors?.length || 0;
      status.innerHTML = `
        <p><strong>${ok}</strong> card(s) imported successfully${err ? `, <strong>${err}</strong> failed` : ""}.</p>
        ${err ? `<ul>${data.errors.map((e) => `<li>${esc(e.file)}: ${esc(e.error)}</li>`).join("")}</ul>` : ""}
      `;
      toast(`${ok} loan card(s) uploaded`);
      loadDashboard();
    } catch (e) {
      status.innerHTML = `<p class="empty">${esc(e.message)}</p>`;
      toast(e.message, "error");
    }
  }

  function addItemRow(item = {}) {
    const row = document.createElement("div");
    row.className = "item-row";
    row.innerHTML = `
      <input placeholder="Item / Equipment name" value="${esc(item.item_name || "")}" data-k="item_name" />
      <input placeholder="Serial number" value="${esc(item.serial_number || "")}" data-k="serial_number" />
      <input placeholder="Qty" value="${esc(item.quantity || "1")}" data-k="quantity" />
      <input placeholder="Remarks" value="${esc(item.remarks || "")}" data-k="remarks" />
      <button type="button" title="Remove">&times;</button>
    `;
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

  // Events
  $$(".nav-item").forEach((btn) => btn.addEventListener("click", () => showView(btn.dataset.view)));
  $$("[data-goto]").forEach((btn) => btn.addEventListener("click", () => showView(btn.dataset.goto)));
  $("#btn-refresh").addEventListener("click", () => {
    const active = $(".nav-item.active")?.dataset.view || "dashboard";
    showView(active);
    toast("Refreshed");
  });
  $("#btn-quick-upload").addEventListener("click", () => {
    showView("upload");
    $("#file-input").click();
  });
  $("#btn-search").addEventListener("click", () => runSearch().catch((e) => toast(e.message, "error")));
  $("#btn-clear-filters").addEventListener("click", () => {
    ["#f-q", "#f-name", "#f-designation", "#f-department", "#f-tel", "#f-item", "#f-from", "#f-to"]
      .forEach((s) => { $(s).value = ""; });
    runSearch();
  });
  ["#f-q", "#f-name", "#f-designation", "#f-department", "#f-tel", "#f-item"].forEach((sel) => {
    $(sel).addEventListener("keydown", (e) => {
      if (e.key === "Enter") runSearch();
    });
  });

  document.addEventListener("click", (e) => {
    const openBtn = e.target.closest("[data-open]");
    const viewBtn = e.target.closest("[data-view-card]");
    const tr = e.target.closest("tbody tr[data-id]");
    if (openBtn) {
      openFile(openBtn.dataset.open);
      return;
    }
    if (viewBtn) {
      openDetails(viewBtn.dataset.viewCard).catch((err) => toast(err.message, "error"));
      return;
    }
    if (tr && !e.target.closest(".row-actions")) {
      openDetails(tr.dataset.id).catch((err) => toast(err.message, "error"));
    }
  });

  $("#modal-close").addEventListener("click", () => $("#detail-modal").close());
  $("#modal-open").addEventListener("click", () => {
    if (state.currentId) openFile(state.currentId);
  });
  $("#modal-delete").addEventListener("click", async () => {
    if (!state.currentId) return;
    if (!confirm("Delete this loan card record?")) return;
    try {
      await api(`/api/cards/${state.currentId}`, { method: "DELETE" });
      $("#detail-modal").close();
      toast("Loan card deleted");
      showView($(".nav-item.active")?.dataset.view || "dashboard");
    } catch (e) {
      toast(e.message, "error");
    }
  });

  // Upload
  const dz = $("#dropzone");
  const fileInput = $("#file-input");
  $("#btn-pick-files").addEventListener("click", () => fileInput.click());
  fileInput.addEventListener("change", () => uploadFiles(fileInput.files));
  ["dragenter", "dragover"].forEach((ev) => {
    dz.addEventListener(ev, (e) => {
      e.preventDefault();
      dz.classList.add("dragover");
    });
  });
  ["dragleave", "drop"].forEach((ev) => {
    dz.addEventListener(ev, (e) => {
      e.preventDefault();
      dz.classList.remove("dragover");
    });
  });
  dz.addEventListener("drop", (e) => uploadFiles(e.dataTransfer.files));

  // Create form
  $("#btn-add-item").addEventListener("click", () => addItemRow());
  addItemRow();
  addItemRow();
  const issue = $("#create-issue-date");
  if (issue && !issue.value) {
    issue.value = new Date().toISOString().slice(0, 10);
  }
  $("#create-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const payload = Object.fromEntries(fd.entries());
    payload.items = collectItems();
    try {
      const data = await api("/api/cards", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
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

  // Backup / import
  $("#btn-backup").addEventListener("click", async () => {
    try {
      const data = await api("/api/backup", { method: "POST" });
      toast(`Backup created: ${data.filename}`);
    } catch (e) {
      toast(e.message, "error");
    }
  });
  $("#btn-restore").addEventListener("click", () => $("#restore-input").click());
  $("#restore-input").addEventListener("change", async () => {
    const file = $("#restore-input").files[0];
    if (!file) return;
    if (!confirm("Restore will replace current data. Continue?")) return;
    const fd = new FormData();
    fd.append("file", file);
    try {
      await api("/api/restore", { method: "POST", body: fd });
      toast("Backup restored");
      showView("dashboard");
    } catch (e) {
      toast(e.message, "error");
    }
  });
  $("#btn-import").addEventListener("click", () => $("#import-input").click());
  $("#import-input").addEventListener("change", async () => {
    const file = $("#import-input").files[0];
    if (!file) return;
    const fd = new FormData();
    fd.append("file", file);
    try {
      const data = await api("/api/import/json", { method: "POST", body: fd });
      toast(`Imported ${data.imported} record(s)`);
      showView("dashboard");
    } catch (e) {
      toast(e.message, "error");
    }
  });

  // Boot
  showView("dashboard");
})();
