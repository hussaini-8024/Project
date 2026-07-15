(() => {
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

  const state = {
    base: "",
    token: localStorage.getItem("au_agent_token") || "",
    user: JSON.parse(localStorage.getItem("au_agent_user") || "null"),
    serverIp: localStorage.getItem("au_agent_ip") || "127.0.0.1",
    serverPort: localStorage.getItem("au_agent_port") || "8765",
    view: "home",
    timer: null,
  };

  const titles = {
    home: ["My Panel", "Your authenticated agent workspace"],
    search: ["Search Cards", "Query loan cards on the server"],
    inventory: ["Inventory", "Browse inventory on the server"],
    upload: ["Upload", "Send PDF/HTML cards to the server"],
    create: ["New Card", "Generate a loan card on the server"],
  };

  const roleLabels = {
    administrator: "Administrator",
    allocation_officer: "Allocation Officer",
    user: "User",
    viewer: "Viewer",
  };

  function esc(s) {
    return String(s ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;");
  }

  function toast(msg, type = "success") {
    const el = $("#toast");
    el.hidden = false;
    el.className = `toast ${type}`;
    el.textContent = msg;
    clearTimeout(toast._t);
    toast._t = setTimeout(() => { el.hidden = true; }, 3000);
  }

  function can(p) {
    return !!(state.user && (state.user.permissions || []).includes(p));
  }

  function applyPerms() {
    $$("[data-perm]").forEach((el) => { el.hidden = !can(el.dataset.perm); });
  }

  async function api(path, options = {}) {
    const headers = Object.assign({}, options.headers || {});
    if (state.token) headers.Authorization = `Bearer ${state.token}`;
    if (options.json) {
      headers["Content-Type"] = "application/json";
      options.body = JSON.stringify(options.json);
      delete options.json;
    }
    const res = await fetch(`${state.base}${path}`, { ...options, headers });
    const ct = res.headers.get("content-type") || "";
    const data = ct.includes("application/json") ? await res.json() : null;
    if (res.status === 401) {
      disconnect(true);
      throw new Error("Session expired");
    }
    if (!res.ok) throw new Error((data && data.error) || `Error ${res.status}`);
    return data;
  }

  function showConnect() {
    $("#agent-connect").hidden = false;
    $("#agent-app").hidden = true;
    if (state.timer) clearInterval(state.timer);
    const form = $("#connect-form");
    form.server_ip.value = state.serverIp;
    form.server_port.value = state.serverPort;
  }

  function showApp() {
    $("#agent-connect").hidden = true;
    $("#agent-app").hidden = false;
    $("#agent-user").innerHTML = `<strong>${esc(state.user.full_name)}</strong><br>${esc(roleLabels[state.user.role] || state.user.role)}`;
    $("#agent-server").textContent = `Server ${state.serverIp}:${state.serverPort}`;
    $("#welcome-name").textContent = `Welcome, ${state.user.full_name}`;
    $("#home-role").textContent = roleLabels[state.user.role] || state.user.role;
    applyPerms();
    startHeartbeat();
    showView("home");
  }

  function disconnect(silent = false) {
    const token = state.token;
    const base = state.base;
    state.token = "";
    state.user = null;
    localStorage.removeItem("au_agent_token");
    localStorage.removeItem("au_agent_user");
    showConnect();
    if (token && base) {
      fetch(`${base}/api/auth/logout`, {
        method: "POST",
        headers: { Authorization: `Bearer ${token}` },
      }).catch(() => {});
    }
    if (!silent) toast("Disconnected");
  }

  function startHeartbeat() {
    if (state.timer) clearInterval(state.timer);
    const beat = async () => {
      try {
        await api("/api/auth/heartbeat", {
          method: "POST",
          json: {
            view: state.view,
            activity: `Agent panel: ${titles[state.view]?.[0] || state.view}`,
          },
        });
        $("#agent-status").textContent = "Online";
      } catch {
        $("#agent-status").textContent = "Reconnecting…";
      }
    };
    beat();
    state.timer = setInterval(beat, 15000);
  }

  async function showView(name) {
    const nav = $(`.nav-item[data-view="${name}"]`);
    if (nav?.dataset.perm && !can(nav.dataset.perm)) {
      toast("Permission denied", "error");
      return;
    }
    state.view = name;
    $$(".view").forEach((v) => v.classList.remove("active"));
    $$(".nav-item").forEach((n) => n.classList.toggle("active", n.dataset.view === name));
    $(`#view-${name}`)?.classList.add("active");
    const [t, s] = titles[name] || ["Panel", ""];
    $("#agent-title").textContent = t;
    $("#agent-sub").textContent = s;
    if (name === "home") await loadHome();
    if (name === "search") await runSearch();
    if (name === "inventory") await runInv();
  }

  async function loadHome() {
    if (!can("loan_view")) return;
    const stats = await api("/api/stats");
    $("#home-cards").textContent = stats.total_cards;
    $("#home-inv").textContent = stats.inventory_total;
    $("#home-avail").textContent = stats.inventory_available;
  }

  async function runSearch() {
    const p = new URLSearchParams();
    const map = {
      name: "#a-name", designation: "#a-desig", department: "#a-dept",
      tel_extension: "#a-tel", item_name: "#a-item",
      issue_date_from: "#a-from", issue_date_to: "#a-to", q: "#a-q",
    };
    Object.entries(map).forEach(([k, sel]) => {
      const v = $(sel)?.value?.trim();
      if (v) p.set(k, v);
    });
    const data = await api(`/api/search?${p}`);
    $("#a-count").textContent = `${data.count} results`;
    $("#a-search-tbody").innerHTML = data.results.map((c) => `<tr>
      <td><strong>${esc(c.name)}</strong></td>
      <td>${esc(c.department || "—")}</td>
      <td>${esc(c.tel_extension || "—")}</td>
      <td>${esc(c.issue_date || "—")}</td>
      <td>${esc(c.equipment_summary || "—")}</td>
      <td><button class="btn primary" data-open="${c.id}">Open</button></td>
    </tr>`).join("") || `<tr><td colspan="6" class="empty">No results</td></tr>`;
  }

  async function runInv() {
    const p = new URLSearchParams();
    const map = {
      item_name: "#ai-name", allocation_officer: "#ai-officer",
      allocated_to: "#ai-to", status: "#ai-status",
      added_date_from: "#ai-added-from", allocation_date_from: "#ai-alloc-from",
    };
    Object.entries(map).forEach(([k, sel]) => {
      const v = $(sel)?.value?.trim();
      if (v) p.set(k, v);
    });
    const data = await api(`/api/inventory?${p}`);
    $("#ai-count").textContent = `${data.count} items`;
    $("#ai-tbody").innerHTML = data.results.map((i) => `<tr>
      <td><strong>${esc(i.item_name)}</strong></td>
      <td>${esc(i.status)}</td>
      <td>${esc(i.allocation_officer || "—")}</td>
      <td>${esc(i.allocated_to || "—")}</td>
      <td>${esc(i.added_date || "—")}</td>
      <td>${esc(i.issue_date || "—")}</td>
      <td>${esc(i.allocation_date || "—")}</td>
    </tr>`).join("") || `<tr><td colspan="7" class="empty">No items</td></tr>`;
  }

  function addItem() {
    const row = document.createElement("div");
    row.className = "item-row";
    row.innerHTML = `
      <input placeholder="Item" data-k="item_name" />
      <input placeholder="Serial" data-k="serial_number" />
      <input placeholder="Qty" data-k="quantity" value="1" />
      <input placeholder="Remarks" data-k="remarks" />
      <button type="button">&times;</button>`;
    row.querySelector("button").onclick = () => row.remove();
    $("#a-items").appendChild(row);
  }

  $("#connect-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const ip = String(fd.get("server_ip")).trim();
    const port = String(fd.get("server_port")).trim();
    state.base = `http://${ip}:${port}`;
    state.serverIp = ip;
    state.serverPort = port;
    localStorage.setItem("au_agent_ip", ip);
    localStorage.setItem("au_agent_port", port);
    $("#connect-error").hidden = true;
    try {
      const health = await fetch(`${state.base}/api/health`).then((r) => r.json());
      if (!health.ok) throw new Error("Server not available");
      const data = await fetch(`${state.base}/api/auth/login`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          username: fd.get("username"),
          password: fd.get("password"),
          client_type: "agent",
          hostname: location.hostname || "agent-pc",
        }),
      }).then(async (r) => {
        const j = await r.json();
        if (!r.ok) throw new Error(j.error || "Login failed");
        return j;
      });
      state.token = data.token;
      state.user = data.user;
      localStorage.setItem("au_agent_token", data.token);
      localStorage.setItem("au_agent_user", JSON.stringify(data.user));
      showApp();
      toast("Connected to server");
    } catch (err) {
      $("#connect-error").hidden = false;
      $("#connect-error").textContent = err.message || "Cannot reach server. Check IP and that the server EXE is running.";
    }
  });

  $$(".nav-item").forEach((b) => b.addEventListener("click", () => showView(b.dataset.view)));
  $("#agent-logout").addEventListener("click", () => disconnect());
  $("#a-search-btn").addEventListener("click", () => runSearch().catch((e) => toast(e.message, "error")));
  $("#ai-search-btn").addEventListener("click", () => runInv().catch((e) => toast(e.message, "error")));

  document.addEventListener("click", async (e) => {
    const btn = e.target.closest("[data-open]");
    if (!btn) return;
    const card = await api(`/api/cards/${btn.dataset.open}`);
    $("#ad-cardno").textContent = card.card_number || "";
    $("#ad-name").textContent = card.name || "";
    $("#ad-meta").innerHTML = [
      ["Designation", card.designation],
      ["Department", card.department],
      ["Extension", card.tel_extension],
      ["Issue Date", card.issue_date],
    ].map(([k, v]) => `<div><span>${esc(k)}</span><strong>${esc(v || "—")}</strong></div>`).join("");
    $("#ad-open").onclick = () => {
      window.open(`${state.base}/api/cards/${card.id}/file?token=${encodeURIComponent(state.token)}`, "_blank");
    };
    $("#a-detail").showModal();
  });
  $("#ad-close").addEventListener("click", () => $("#a-detail").close());

  $("#a-pick").addEventListener("click", () => $("#a-files").click());
  $("#a-files").addEventListener("change", async () => {
    const files = [...$("#a-files").files];
    if (!files.length) return;
    const fd = new FormData();
    files.forEach((f) => fd.append("files", f));
    const status = $("#a-upload-status");
    status.hidden = false;
    try {
      const data = await api("/api/upload", { method: "POST", body: fd });
      status.innerHTML = `<p>Uploaded ${data.count} file(s).</p>`;
      toast("Upload complete");
    } catch (e) {
      status.innerHTML = `<p>${esc(e.message)}</p>`;
      toast(e.message, "error");
    }
  });

  $("#a-add-item").addEventListener("click", addItem);
  addItem();
  $("#a-issue").value = new Date().toISOString().slice(0, 10);
  $("#a-create-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const payload = Object.fromEntries(fd.entries());
    payload.items = $$("#a-items .item-row").map((row) => {
      const o = {};
      $$("input", row).forEach((i) => { o[i.dataset.k] = i.value.trim(); });
      return o;
    }).filter((i) => i.item_name);
    try {
      await api("/api/cards", { method: "POST", json: payload });
      toast("Loan card created on server");
      e.target.reset();
      $("#a-items").innerHTML = "";
      addItem();
      $("#a-issue").value = new Date().toISOString().slice(0, 10);
    } catch (err) {
      toast(err.message, "error");
    }
  });

  // If opened from same server origin, prefill
  if (location.hostname && location.hostname !== "localhost") {
    state.serverIp = location.hostname;
  } else {
    state.serverIp = localStorage.getItem("au_agent_ip") || "127.0.0.1";
  }
  state.serverPort = localStorage.getItem("au_agent_port") || location.port || "8765";
  state.base = `http://${state.serverIp}:${state.serverPort}`;

  if (state.token && state.user) {
    // Validate token
    api("/api/auth/me").then((d) => {
      state.user = d.user;
      showApp();
    }).catch(() => showConnect());
  } else {
    showConnect();
  }
})();
