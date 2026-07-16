async function api(path, options = {}) {
  const opts = {
    headers: { "Content-Type": "application/json", ...(options.headers || {}) },
    credentials: "same-origin",
    ...options,
  };
  const res = await fetch(`/api${path}`, opts);
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    throw new Error(data.detail || data.message || `Request failed (${res.status})`);
  }
  return data;
}

document.getElementById("login-form")?.addEventListener("submit", async (e) => {
  e.preventDefault();
  const form = e.currentTarget;
  const err = document.getElementById("login-error");
  err.hidden = true;
  const body = {
    username: form.username.value.trim(),
    password: form.password.value,
  };
  try {
    await api("/login", { method: "POST", body: JSON.stringify(body) });
    window.location.href = "/";
  } catch (ex) {
    err.textContent = ex.message;
    err.hidden = false;
  }
});
