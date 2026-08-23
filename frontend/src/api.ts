const TOKEN_KEY = "cr_token";

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token: string | null) {
  if (token) localStorage.setItem(TOKEN_KEY, token);
  else localStorage.removeItem(TOKEN_KEY);
}

export async function api<T = unknown>(path: string, init: RequestInit = {}): Promise<T> {
  const headers = new Headers(init.headers);
  if (!headers.has("Content-Type") && init.body && !(init.body instanceof FormData)) {
    headers.set("Content-Type", "application/json");
  }
  const token = getToken();
  if (token) headers.set("Authorization", `Bearer ${token}`);
  const res = await fetch(path, { ...init, headers });
  if (res.status === 401) {
    setToken(null);
    if (!path.includes("/auth/login")) window.location.href = "/login";
  }
  if (!res.ok) {
    let detail = res.statusText;
    try {
      const body = await res.json();
      detail = body.detail || JSON.stringify(body);
    } catch {
      /* ignore */
    }
    throw new Error(typeof detail === "string" ? detail : JSON.stringify(detail));
  }
  if (res.status === 204) return undefined as T;
  return res.json();
}

export type User = {
  id: string;
  public_id: string;
  username: string;
  email: string;
  full_name: string;
  role: string;
  status: string;
  course: string;
  mfa_enabled: boolean;
  lab_id: string | null;
  quota: string | null;
  group_id?: string | null;
  group?: string | null;
  groups?: { id: string; name: string; kind: string; public_id: string }[];
  group_ids?: string[];
};

export type GroupMember = {
  id: string;
  public_id: string;
  username: string;
  full_name: string;
  role: string;
  status: string;
  lab_id: string | null;
};

export type Group = {
  id: string;
  public_id: string;
  name: string;
  kind: "student" | "instructor";
  description: string;
  internet_policy: "enabled" | "disabled" | "unset";
  max_machines: number | null;
  inactivity_alert_days: number;
  member_count: number;
  members: GroupMember[];
  created_at: string;
};

export type InactivityAlert = {
  user_id: string;
  public_id: string;
  username: string;
  full_name: string;
  group: string | null;
  last_activity: string;
  idle_days: number;
  threshold_days: number;
};

export type Machine = {
  id: string;
  public_id: string;
  name: string;
  kind: "container" | "vm";
  status: string;
  vcpu: number;
  ram_mb: number;
  disk_gb: number;
  internet: boolean;
  isolated: boolean;
  ephemeral: boolean;
  template: string | null;
  template_name: string | null;
  vulnerable: boolean;
  warning_label: string;
  ip: string | null;
  mac: string | null;
  network_id: string | null;
  cidr: string | null;
  queue_position: number | null;
  queue_reason: string;
  error: string;
  node: string | null;
  created_at: string;
};

export type Notification = {
  id: string;
  title: string;
  body: string;
  kind: string;
  link: string;
  ref_id: string;
  read: boolean;
  created_at: string;
};

export type NotificationFeed = {
  unread: number;
  items: Notification[];
};

export type Announcement = {
  id: string;
  public_id: string;
  title: string;
  body: string;
  kind: "announcement" | "assignment";
  scope: "group" | "all";
  group_id: string | null;
  group: string | null;
  author_id: string | null;
  author: string;
  created_at: string;
  delivered?: number;
};

export type CommandEntry = {
  tool: string;
  command: string;
  description: string;
  category: string;
  tags: string[];
};

export type CommandSearchResult = {
  query: string;
  count: number;
  total: number;
  categories: string[];
  results: CommandEntry[];
};

export type AukcResponse = {
  configured: boolean;
  model: string;
  answer: string;
  error?: string;
};

export type Template = {
  slug: string;
  name: string;
  environment: string;
  recommended_kind: string;
  description: string;
  category: string;
  default_vcpu: number;
  default_ram_mb: number;
  default_disk_gb: number;
  is_vulnerable_target: boolean;
  requires_full_os: boolean;
  tools: string[];
  warning_label: string;
  container_first: boolean;
};
