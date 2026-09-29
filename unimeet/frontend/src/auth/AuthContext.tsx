import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from "react";
import { api, setToken } from "../api/client";
import type { Role, User } from "../types";

interface AuthState {
  user: User | null;
  loading: boolean;
  login: (universityId: string, password: string, role: Role) => Promise<User>;
  logout: () => Promise<void>;
  refresh: () => Promise<void>;
}

const AuthContext = createContext<AuthState | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  const refresh = async () => {
    try {
      const data = await api<{ user: User }>("/api/auth/me");
      setUser(data.user);
    } catch {
      setUser(null);
      setToken(null);
    }
  };

  useEffect(() => {
    refresh().finally(() => setLoading(false));
  }, []);

  const value = useMemo<AuthState>(
    () => ({
      user,
      loading,
      login: async (universityId, password, role) => {
        const data = await api<{ token: string; user: User }>("/api/auth/login", {
          method: "POST",
          body: JSON.stringify({ universityId, password, role }),
        });
        setToken(data.token);
        setUser(data.user);
        return data.user;
      },
      logout: async () => {
        await api("/api/auth/logout", { method: "POST" }).catch(() => undefined);
        setToken(null);
        setUser(null);
      },
      refresh,
    }),
    [user, loading],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used inside AuthProvider");
  return ctx;
}
